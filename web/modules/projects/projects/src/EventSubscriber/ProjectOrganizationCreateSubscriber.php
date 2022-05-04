<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Drupal\projects\Entity\Project;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\ProjectInterface;
use Drupal\youvo\Exception\FieldAwareHttpException;
use Drupal\youvo\Utility\FieldValidator;
use Drupal\youvo\Utility\RestContentShifter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Subscriber for the organization create event.
 *
 * @see \Drupal\organizations\Plugin\rest\resource\OrganizationManageResource
 */
class ProjectOrganizationCreateSubscriber implements EventSubscriberInterface {

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected Json $serializationJson;

  /**
   * Constructs a ProjectOrganizationCreateSubscriber object.
   *
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   */
  public function __construct(Json $serialization_json) {
    $this->serializationJson = $serialization_json;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    if (class_exists('Drupal\\organizations\\Event\\OrganizationCreateEvent')) {
      return [
        OrganizationCreateEvent::class => 'createProject',
      ];
    }
    return [];
  }

  /**
   * Creates new project.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createProject(OrganizationCreateEvent $event) {
    $project = Project::create(['type' => 'project']);
    $attributes = $this->validateAndShiftRequest($event->getRequest());
    $this->populateFields($attributes, $project);
    $project->setOwner($event->getOrganization());
    $project->setPublished();
    $project->save();
  }

  /**
   * Checks and distills values for projects.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The shifted project attributes.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  public function validateAndShiftRequest(Request $request) {

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Decline request body without project data.
    if (empty($content['data']) ||
      !in_array('project', array_column($content['data'], 'type'))) {
      throw new HttpException(400, 'Request body does not provide project data.');
    }

    // Get attributes from request content.
    $attributes = RestContentShifter::shiftAttributesByType($content, 'project');

    // Check if body is provided.
    if (empty($attributes['body'])) {
      throw new FieldAwareHttpException(400,
        'Need to provide body to create project.',
        'body');
    }

    return $attributes;
  }

  /**
   * Populates project fields with provided values with some validation.
   *
   * @param array $attributes
   *   Contains project attributes.
   * @param \Drupal\projects\ProjectInterface $project
   *   The project to populate.
   *
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  public function populateFields(array $attributes, ProjectInterface $project) {
    foreach ($attributes as $field_key => $value) {
      $field_name = $this->validateAndRenameField($field_key, $project);
      $field_definition = $project->getFieldDefinition($field_name);
      $this->checkFieldAccess($field_definition, $field_key);
      $this->validateFieldValue($field_definition, $field_key, $value);
      $project->set($field_name, $value);
    }
  }

  /**
   * Resolves the field name.
   */
  protected function validateAndRenameField(string $field_key, ProjectInterface $project) {
    if ($project->hasField($field_key)) {
      $field_name = $field_key;
    }
    else {
      $field_name = 'field_' . $field_key;
      if (!$project->hasField($field_name)) {
        throw new FieldAwareHttpException(400,
          'Malformed request body. Projects do not provide the field ' . $field_key,
          $field_key);
      }
    }
    return $field_name;
  }

  /**
   * Checks the field access with the help of ProjectFieldAccess.
   */
  protected function checkFieldAccess(FieldDefinitionInterface $field_definition, string $field_key) {
    if (!ProjectFieldAccess::isFieldOfGroup($field_definition,
      ProjectFieldAccess::UNRESTRICTED_FIELDS)) {
      throw new FieldAwareHttpException(403,
        'Access Denied. Not allowed to set ' . $field_key,
        $field_key);
    }
  }

  /**
   * Validates the field value with the help of the FieldValidator.
   */
  protected function validateFieldValue(FieldDefinitionInterface $field_definition, string $field_key, mixed $value) {
    if (!FieldValidator::validate($field_definition, $value)) {
      throw new FieldAwareHttpException(400,
        'Malformed request body. Unable to validate the project field ' . $field_key,
        $field_key);
    }
  }

}
