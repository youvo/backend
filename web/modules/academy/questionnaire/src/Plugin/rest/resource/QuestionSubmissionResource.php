<?php

namespace Drupal\questionnaire\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Drupal\questionnaire\Entity\QuestionSubmission;
use Drupal\questionnaire\SubmissionManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
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
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $submissionManager;

  /**
   * Constructs a QuestionSubmissionResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   * @param \Drupal\questionnaire\SubmissionManager $submission_manager
   *   The submission manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Json $serialization_json, SubmissionManager $submission_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializationJson = $serialization_json;
    $this->submissionManager = $submission_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('serialization.json'),
      $container->get('submission.manager')
    );
  }

  /**
   * Responds GET requests.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The referenced question.
   *
   * @return \Drupal\rest\ModifiedResourceResponse|ResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(Question $question) {

    // Get the respective submission by question and current user.
    $submission = $this->getSubmission($question);

    // There is no submission for this question by this user.
    if (empty($submission)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Prepare and sanitize output.
    if ($question->bundle() == 'checkboxes') {
      $value = explode(',', Html::escape($submission->get('value')->value));
    }
    else {
      $value = Html::escape($submission->get('value')->value);
    }

    // Fetch questions and answers.
    $response = new ResourceResponse([
      'resource' => strtr($this->pluginId, ':', '.'),
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

    // Add cacheable dependency to refresh response when submission is udpated.
    $response->addCacheableDependency($submission);

    return $response;
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The referenced question.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(Question $question, Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson
      ->decode($request->getContent());

    // The type is required to process the request.
    if (!array_key_exists('type', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify type.');
    }

    // The value is required to process the request.
    if (!array_key_exists('value', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify value.');
    }

    // Check for matching type.
    if ($question->bundle() != $request_content['type']) {
      throw new BadRequestHttpException('Question type mismatch.');
    }

    // Check if posted value has valid format.
    $v = $request_content['value'];
    $valid_type = match($question->bundle()) {
      'textarea' => is_string($v),
      'textfield' => is_string($v) && strlen($v) < 255,
      'radios' => is_string($v) && is_numeric($v) && intval($v) == $v,
      'checkboxes' => is_array($v) && !in_array(FALSE, array_map(fn($s) => is_numeric($s) && intval($s) == $s, $v), TRUE),
      default => throw new BadRequestHttpException('Action for question type not specified.'),
    };

    if (!$valid_type) {
      throw new BadRequestHttpException('Malformed submission value.');
    }

    // Check if posted value is a valid option.
    if ($question->bundle() == 'radios' || $question->bundle() == 'checkboxes') {
      $valid_value = match($question->bundle()) {
        'radios' => in_array($v, array_keys($question->get('options')->getValue())),
        'checkboxes' => !array_diff($v, array_keys($question->get('options')->getValue())),
      };
      if (!$valid_value) {
        throw new BadRequestHttpException('Invalid submission value.');
      }
    }

    // Resolve value for respective type.
    $value = match ($question->bundle()) {
      'textarea', 'textfield', 'radios' => $request_content['value'],
      'checkboxes' => implode(',', $request_content['value']),
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
      if (empty($submission)) {
        $submission = QuestionSubmission::create([
          'question' => $question->id(),
          'langcode' => 'en',
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
    else {
      if (!empty($submission)) {
        try {
          $submission->delete();
        }
        catch (EntityStorageException $e) {
          throw new HttpException(500, 'Internal Server Error', $e);
        }
      }
      return new ModifiedResourceResponse(NULL, 204);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\questionnaire\Controller\QuestionSubmissionAccessController::accessQuestionSubmission');
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
