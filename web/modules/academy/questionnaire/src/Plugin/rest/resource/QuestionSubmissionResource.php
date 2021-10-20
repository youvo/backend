<?php

namespace Drupal\questionnaire\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Drupal\questionnaire\Entity\QuestionSubmission;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Question Submission Resource.
 *
 * @RestResource(
 *   id = "question:submission",
 *   label = @Translation("Question Submission Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/questions/{question}/submission",
 *     "create" = "/api/questions/{question}/submission",
 *     "delete" = "/api/questions/{question}/submission/delete"
 *   }
 * )
 */
class QuestionSubmissionResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

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
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, Json $serialization_json) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->serializationJson = $serialization_json;
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
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('serialization.json')
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
    $submission = $this->getRespectiveSubmission($question);

    // There is no submission for this question by this user.
    if (empty($submission)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Fetch questions and answers.
    $response = new ResourceResponse([
      'type' => 'question.submission.resource',
      'question' => [
        'uuid' => $question->uuid(),
        'type' => $question->bundle(),
      ],
      'data' => [
        'value' => $submission->get('value')->value,
        'stale' => FALSE,
      ],
      'post_required' => [
        'type' => 'Expected type of question.',
        'value' => 'String or array of values for submission.',
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
   */
  public function post(Question $question, Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson
      ->decode($request->getContent());

    if ($question->bundle() != $request_content['type']) {
      throw new BadRequestHttpException('Question type mismatch.');
    }

    // Resolve value for respective type.
    $value = match ($question->bundle()) {
      'textarea', 'textfield' => $request_content['value'],
      'checkboxes', 'radios' => implode(',', $request_content['value']),
      default => throw new BadRequestHttpException('Action for question type not specified.'),
    };

    // Get the respective submission by question and current user.
    $submission = $this->getRespectiveSubmission($question);

    // There is no submission for this question by this user. Create new
    // submission.
    // @todo Pass langcode in which question was answered.
    if (empty($submission)) {
      $submission = QuestionSubmission::create([
        'question' => $question->id(),
        'uid' => $this->currentUser->id(),
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

    return new ModifiedResourceResponse('Submission saved.', 201);
  }

  /**
   * Responds DELETE requests.
   *
   * This method is temporary for development.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The referenced question.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(Question $question) {

    // Get the respective submission by question and current user.
    $submission = $this->getRespectiveSubmission($question);

    // There is no submission for this question by this user.
    if (empty($submission)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Delete the respective submission.
    $submission->delete();
    return new ModifiedResourceResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = new RouteCollection();

    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $create_path = $definition['uri_paths']['create'];
    $route_name = strtr($this->pluginId, ':', '.');

    $methods = $this->availableMethods();
    foreach ($methods as $method) {
      $path = $method === 'POST'
        ? $create_path
        : $canonical_path;
      $route = $this->getBaseRoute($path, $method);

      // Add custom access check.
      $route->setRequirement('_custom_access', '\Drupal\questionnaire\Controller\QuestionSubmissionAccessController::accessQuestionSubmission');

      // Add route entity context parameters.
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
   */
  protected function getRespectiveSubmission(Question $question) : ?QuestionSubmission {
    try {
      // Get referenced submission.
      $query = $this->entityTypeManager
        ->getStorage('question_submission')
        ->getQuery();
      $submission_id = $query->condition('question', $question->id())
        ->condition('uid', $this->currentUser->id())
        ->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    // Return nothing if there is no submission.
    if (empty($submission_id)) {
      return NULL;
    }

    // Something went wrong here.
    if (count($submission_id) > 1) {
      throw new HttpException(417, 'The submission for the requested question has inconsistent persistent data.');
    }

    try {
      // Return loaded submission.
      /** @var \Drupal\questionnaire\Entity\QuestionSubmission $submission */
      $submission = $this->entityTypeManager
        ->getStorage('question_submission')
        ->load(reset($submission_id));
      return $submission;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

}
