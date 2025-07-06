<?php

namespace Drupal\questionnaire\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\questionnaire\Entity\Question;
use Drupal\questionnaire\Entity\QuestionSubmission;
use Drupal\questionnaire\SubmissionManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Question Submission Resource.
 *
 * @RestResource(
 *   id = "question:submission",
 *   label = @Translation("Question Submission Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/questions/{question}/submission"
 *   }
 * )
 */
class QuestionSubmissionResource extends ResourceBase {

  /**
   * The submission manager.
   */
  protected SubmissionManager $submissionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->submissionManager = $container->get('submission.manager');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(Question $question): ResourceResponseInterface {

    // Get the respective submission by question and current user.
    $submission = $this->getSubmission($question);

    // There is no submission for this question by this user.
    if ($submission === NULL) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Prepare and sanitize output.
    if ($question->bundle() === 'checkboxes' || $question->bundle() === 'task') {
      $value = explode(',', Html::escape($submission->get('value')->value));
    }
    else {
      $value = Html::escape($submission->get('value')->value);
    }

    // Fetch questions and answers.
    $response = new ResourceResponse([
      'resource' => str_replace(':', '.', $this->pluginId),
      'data' => [
        'type' => $submission->getEntityTypeId(),
        'value' => $value,
        'question' => [
          'type' => $question->bundle(),
          'uuid' => $question->uuid(),
        ],
      ],
      'post_required' => [
        'type' => 'Expected type of question.',
        'value' => 'String (textarea, textfield), index (radios) or array of indexes (checkboxes) for submission.',
      ],
    ]);

    // Add cacheable dependency to refresh response when submission is updated.
    $response->addCacheableDependency($submission);

    return $response;
  }

  /**
   * Responds to POST requests.
   */
  public function post(Question $question, Request $request): ResourceResponseInterface {

    // Decode content of the request.
    $request_content = Json::decode($request->getContent());

    // The type is required to process the request.
    if (!array_key_exists('type', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify type.');
    }

    // The value is required to process the request.
    if (!array_key_exists('value', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify value.');
    }

    // Check for matching type.
    if ($question->bundle() !== $request_content['type']) {
      throw new BadRequestHttpException('Question type mismatch.');
    }

    // Check if posted value has valid format.
    $v = $request_content['value'];
    if ($question->bundle() === 'checkboxes' || $question->bundle() === 'task') {
      $v = array_filter($v, static fn($s) => $s !== NULL && $s !== "");
    }
    $valid_type = match($question->bundle()) {
      'textarea' => is_string($v),
      'textfield' => is_string($v) && strlen($v) < 255,
      'radios' => is_string($v) && is_numeric($v) && (int) $v == $v,
      'checkboxes' => is_array($v) && !in_array(FALSE, array_map(static fn($s) => is_numeric($s) && (int) $s == $s, $v), TRUE),
      'task' => is_array($v) && !in_array(FALSE, array_map(static fn($s) => is_numeric($s) && (int) $s == $s, $v), TRUE) && count($v) <= 1,
      default => throw new BadRequestHttpException('Action for question type not specified.'),
    };

    if (!$valid_type) {
      throw new BadRequestHttpException('Malformed submission value.');
    }

    // Check if posted value is a valid option.
    if (in_array($question->bundle(), ['radios', 'checkboxes', 'task'])) {
      $valid_value = match($question->bundle()) {
        'radios' => array_key_exists($v, $question->get('options')->getValue()),
        'checkboxes' => !array_diff($v, array_keys($question->get('options')->getValue())),
        // @phpstan-ignore-next-line
        'task' => empty($v) || (int) $v[0] == 0,
        // @phpstan-ignore-next-line
        default => FALSE,
      };
      if (!$valid_value) {
        throw new BadRequestHttpException('Invalid submission value.');
      }
    }

    // Resolve value for respective type.
    $value = match ($question->bundle()) {
      'textarea', 'textfield', 'radios' => $request_content['value'],
      'checkboxes', 'task' => implode(',', $request_content['value']),
      default => throw new BadRequestHttpException('Action for question type not specified.'),
    };

    // Get the respective submission by question and current user.
    $submission = $this->getSubmission($question);

    // Create or update submission if value is not empty.
    if ($value === '0' || !empty($value)) {

      // There is no submission for this question by this user. Create new
      // submission.
      // @todo Pass langcode in which question was answered.
      // @todo Issue #11: Add revision id of question.
      if ($submission === NULL) {
        $submission = QuestionSubmission::create([
          'question' => $question->id(),
          'langcode' => 'de',
          'value' => $value,
        ]);
      }
      // We found a submission. Modify the last submission.
      else {
        $submission->set('value', $value);
      }

      // Save submission.
      try {
        $submission->save();
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, 'Internal Server Error', $e);
      }

      return new ModifiedResourceResponse(NULL, 201);
    }

    // Delete previous submission or do nothing if value is empty.
    if ($submission !== NULL) {
      try {
        $submission->delete();
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, 'Internal Server Error', $e);
      }
    }
    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\questionnaire\Access\QuestionSubmissionAccess::accessQuestionSubmission');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'question' => [
          'type' => 'entity:question',
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Gets the respective submission of the question by the current user.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The requested question.
   *
   * @returns \Drupal\questionnaire\Entity\QuestionSubmission|null
   *   The respective submission or NULL if no storage.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   */
  protected function getSubmission(Question $question) : ?QuestionSubmission {
    try {
      return $this->submissionManager->loadSubmission($question);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new UnprocessableEntityHttpException('The submission for the requested question has inconsistent persistent data.', $e);
    }
  }

}
