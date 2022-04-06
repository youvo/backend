<?php

namespace Drupal\projects;

use Drupal\Component\Serialization\Json;
use Drupal\projects\Entity\Project;
use Drupal\youvo\Exception\FieldAwareHttpException;
use Drupal\youvo\Utility\FieldValidator;
use Drupal\youvo\Utility\RestContentShifter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides project REST responder service.
 *
 */
class ProjectRestResponder {

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

  /**
   * Constructs a ProjectRestResponder service.
   *
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   */
  public function __construct(Json $serialization_json) {
    $this->serializationJson = $serialization_json;
  }

  /**
   * Create new project.
   *
   * @return \Drupal\projects\ProjectInterface
   *   The project.
   */
  public function createProject() {
    return Project::create(['type' => 'project']);
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
   * Create new project node with some validation.
   *
   * @param array $attributes
   *   Contains project attributes.
   * @param \Drupal\projects\ProjectInterface $project
   *   The project to populate.
   *
   * @return \Drupal\projects\ProjectInterface
   *   The project with populated fields.
   *
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  public function populateFields(array $attributes, ProjectInterface $project) {

    // Populate fields.
    foreach ($attributes as $field_key => $value) {

      // Validate that field is available.
      if ($project->hasField($field_key) && $field_key != 'name') {
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

      // Check access to edit field.
      if (!ProjectFieldAccess::isFieldOfGroup($field_name,
        ProjectFieldAccess::UNRESTRICTED_FIELDS)) {
        throw new FieldAwareHttpException(403,
          'Access Denied. Not allowed to set ' . $field_key,
          $field_key);
      }

      // Validate field value.
      $field_definition = $project->getFieldDefinition($field_name);
      if (!FieldValidator::validate($field_definition, $value)) {
        throw new FieldAwareHttpException(400,
          'Malformed request body. Unable to validate the project field ' . $field_key,
          $field_key);
      }

      // Set the field value.
      $project->get($field_name)->value = $value;
    }

    return $project;
  }

}
