<?php

namespace Drupal\postman_interface\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides postman variables resource.
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
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponseInterface {

    // Get some creative.
    try {
      $creative_ids = $this->entityQuery('user')
        ->condition('uid', 1, '!=')
        ->condition('roles', 'creative')
        ->execute();
      $creative = !empty($creative_ids) ?
        $this->entityLoad('user', reset($creative_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $creative = NULL;
    }

    // Get some manager.
    try {
      $manager_ids = $this->entityQuery('user')
        ->condition('uid', 1, '!=')
        ->condition('roles', 'manager')
        ->execute();
      $manager = !empty($manager_ids) ?
        $this->entityLoad('user', reset($manager_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $manager = NULL;
    }

    // Get some organization.
    try {
      $organization_ids = $this->entityQuery('user')
        ->condition('type', 'organization')
        ->condition('roles', 'organization')
        ->execute();
      $organization = !empty($organization_ids) ?
        $this->entityLoad('user', reset($organization_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $organization = NULL;
    }

    // Get some prospect.
    try {
      $prospect_ids = $this->entityQuery('user')
        ->condition('type', 'organization')
        ->condition('roles', 'prospect')
        ->execute();
      $prospect = !empty($prospect_ids) ?
        $this->entityLoad('user', reset($prospect_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $prospect = NULL;
    }

    // Get some course.
    try {
      $course_ids = $this->entityQuery('course')
        ->execute();
      $course = !empty($course_ids) ?
        $this->entityLoad('course', reset($course_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $course = NULL;
    }

    // Get some lecture.
    try {
      $lecture_ids = $this->entityQuery('lecture')
        ->execute();
      $lecture = !empty($lecture_ids) ?
        $this->entityLoad('lecture', reset($lecture_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $lecture = NULL;
    }

    // Get some textfield question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'textfield')
        ->execute();
      $question_textfield = !empty($question_ids) ?
        $this->entityLoad('question', reset($question_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_textfield = NULL;
    }

    // Get some textarea question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'textarea')
        ->execute();
      $question_textarea = !empty($question_ids) ?
        $this->entityLoad('question', reset($question_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_textarea = NULL;
    }

    // Get some textfield question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'checkboxes')
        ->execute();
      $question_checkboxes = !empty($question_ids) ?
        $this->entityLoad('question', reset($question_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_checkboxes = NULL;
    }

    // Get some radios question.
    try {
      $question_ids = $this->entityQuery('question')
        ->condition('bundle', 'radios')
        ->execute();
      $question_radios = !empty($question_ids) ?
        $this->entityLoad('question', reset($question_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $question_radios = NULL;
    }

    // Get a project that is a draft.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'draft')
        ->range(0, 1)
        ->execute();
      $project_draft = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_draft = NULL;
    }

    // Get a project that is pending.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'pending')
        ->range(0, 1)
        ->execute();
      $project_pending = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_pending = NULL;
    }

    // Get a project that is open.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'open')
        ->range(0, 1)
        ->execute();
      $project_open = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_open = NULL;
    }

    // Get a project that can mediate.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'open')
        ->condition('field_applicants.%delta', 1, '>=')
        ->range(0, 1)
        ->execute();
      $project_can_mediate = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_can_mediate = NULL;
    }

    // Get a project that is ongoing.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'ongoing')
        ->range(0, 1)
        ->execute();
      $project_ongoing = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_ongoing = NULL;
    }

    // Get a project that is completed.
    try {
      $project_ids = $this->entityQuery('project')
        ->condition('status', 1)
        ->condition('field_lifecycle', 'completed')
        ->range(0, 1)
        ->execute();
      $project_completed = !empty($project_ids) ?
        $this->entityLoad('project', reset($project_ids)) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $project_completed = NULL;
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => str_replace(':', '.', $this->pluginId),
      'data' => [
        'creative' => $creative?->uuid(),
        'manager' => $manager?->uuid(),
        'organization' => $organization?->uuid(),
        'prospect' => $prospect?->uuid(),
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

    // Add cacheable dependencies for content.
    $response->addCacheableDependency($creative);
    $response->addCacheableDependency($manager);
    $response->addCacheableDependency($organization);
    $response->addCacheableDependency($prospect);
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
   *   The entity type (for example, project) for which the query object should
   *   be returned.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function entityQuery(string $entity_type): QueryInterface {
    return $this->entityTypeManager
      ->getStorage($entity_type)
      ->getQuery()
      ->accessCheck();
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
  protected function entityLoad(string $entity_type, int $entity_id): EntityInterface {
    return $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);
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
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
