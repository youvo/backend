<?php

namespace Drupal\questionnaire\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\questionnaire\Entity\Question;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds GET requests.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The referenced question.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get(Question $question) {

    // Get referenced submission.
    $submission_id = [];
    try {
      $query = $this->entityTypeManager
        ->getStorage('question_submission')
        ->getQuery();
      $submission_id = $query->condition('question', $question->id())
        ->condition('uid', $this->currentUser->id())
        ->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return new ResourceResponse('Could not query database.', 500);
    }

    // Something went wrong here.
    if (count($submission_id) > 1) {
      return new ResourceResponse('The submission for the requested question has inconsistent persistent data.', 417);
    }

    // There is no submission for this question by this user.
    if (empty($submission_id)) {
      return new ResourceResponse('There is no submission for this question by this user', 204);
    }

    // Load submission.
    try {
      /** @var \Drupal\questionnaire\Entity\QuestionSubmission $submission */
      $submission = $this->entityTypeManager
        ->getStorage('question_submission')
        ->load(reset($submission_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return new ResourceResponse('Could not load submission correctly.', 500);
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
        'uuid' => 'The Uuid of the question.',
        'type' => 'Expected type of question.',
        'values' => 'Array of values for submission.',
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
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function post(Question $question, Request $request) {

    // Decode content of the request.
    // $request_content = \Drupal::service('serialization.json')
    // ->decode($request->getContent());
    return new ResourceResponse('Hello POST.');
  }

  /**
   * Responds DELETE requests.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The referenced question.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function delete(Question $question) {
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

}
