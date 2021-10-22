<?php

namespace Drupal\postman_interface\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Postman Variables Resource.
 *
 * @RestResource(
 *   id = "postman:variables",
 *   label = @Translation("Postman Variables Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/postman"
 *   }
 * )
 */
class PostmanVariablesResource extends ResourceBase {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get() {

    // Get some creative.
    try {
      $creative_ids = $this->entityQuery('user')
        ->condition('uid', 1, '!=')
        ->condition('roles', 'creative')
        ->execute();
      $creative = $this->entityLoad('user', reset($creative_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $creative = NULL;
    }

    // Get some organisation.
    try {
      $organisation_ids = $this->entityQuery('user')
        ->condition('roles', 'organisation')
        ->execute();
      $organisation = $this->entityLoad('user', reset($organisation_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $organisation = NULL;
    }

    // Get some course.
    try {
      $course_ids = $this->entityQuery('course')
        ->execute();
      $course = $this->entityLoad('course', reset($course_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $course = NULL;
    }

    // Get some lecture.
    try {
      $lecture_ids = $this->entityQuery('lecture')
        ->execute();
      $lecture = $this->entityLoad('lecture', reset($lecture_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $lecture = NULL;
    }

    // Get some textfield question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'textfield')
        ->execute();
      $question_textfield = $this->entityLoad('question', reset($question_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_textfield = NULL;
    }

    // Get some textarea question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'textarea')
        ->execute();
      $question_textarea = $this->entityLoad('question', reset($question_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_textarea = NULL;
    }

    // Get some textfield question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'checkboxes')
        ->execute();
      $question_checkboxes = $this->entityLoad('question', reset($question_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_checkboxes = NULL;
    }

    // Get some radios question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'radios')
        ->execute();
      $question_radios = $this->entityLoad('question', reset($question_ids));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_radios = NULL;
    }

    // Get a project that is a draft.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'draft')
        ->range(0, 1)
        ->execute();
      $project_draft = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_draft = NULL;
    }

    // Get a project that is pending.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'pending')
        ->range(0, 1)
        ->execute();
      $project_pending = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_pending = NULL;
    }

    // Get a project that is open.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'open')
        ->range(0, 1)
        ->execute();
      $project_open = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_open = NULL;
    }

    // Get a project that can mediate.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'open')
        ->condition('field_applicants.%delta', 1, '>=')
        ->range(0, 1)
        ->execute();
      $project_can_mediate = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_can_mediate = NULL;
    }

    // Get a project that is ongoing.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'ongoing')
        ->range(0, 1)
        ->execute();
      $project_ongoing = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_ongoing = NULL;
    }

    // Get a project that is completed.
    try {
      $projects_id = $this->entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'completed')
        ->range(0, 1)
        ->execute();
      $project_completed = $this->entityLoad('node', reset($projects_id));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_completed = NULL;
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => strtr($this->pluginId, ':', '.'),
      'data' => [
        'creative' => $creative?->uuid(),
        'organisation' => $organisation?->uuid(),
        'course' => $course?->uuid(),
        'lecture' => $lecture?->uuid(),
        'question_textfield' => $question_textfield?->uuid(),
        'question_textarea' => $question_textarea?->uuid(),
        'question_checkboxes' => $question_checkboxes?->uuid(),
        'question_radios' => $question_radios?->uuid(),
        'project_draft' => $project_draft?->uuid(),
        'project_pending' => $project_pending?->uuid(),
        'project_open' => $project_open?->uuid(),
        'project_open_can_mediate' => $project_can_mediate?->uuid(),
        'project_ongoing' => $project_ongoing?->uuid(),
        'project_completed' => $project_completed?->uuid(),
      ],
    ]);

    // Prevent caching.
    $response->addCacheableDependency($creative);
    $response->addCacheableDependency($organisation);
    $response->addCacheableDependency($course);
    $response->addCacheableDependency($lecture);
    $response->addCacheableDependency($question_textfield);
    $response->addCacheableDependency($question_textarea);
    $response->addCacheableDependency($question_checkboxes);
    $response->addCacheableDependency($question_radios);
    $response->addCacheableDependency($project_draft);
    $response->addCacheableDependency($project_pending);
    $response->addCacheableDependency($project_open);
    $response->addCacheableDependency($project_can_mediate);
    $response->addCacheableDependency($project_ongoing);
    $response->addCacheableDependency($project_completed);

    return $response;
  }

  /**
   * Returns the entity query object for this entity type.
   *
   * @param string $entity_type
   *   The entity type (for example, node) for which the query object should be
   *   returned.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function entityQuery(string $entity_type) {
    return $this->entityTypeManager
      ->getStorage($entity_type)
      ->getQuery();
  }

  /**
   * Loads entity by id of respective type.
   *
   * @param string $entity_type
   *   The type of entity.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The query instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function entityLoad(string $entity_type, int $entity_id) {
    return $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);
  }

}
