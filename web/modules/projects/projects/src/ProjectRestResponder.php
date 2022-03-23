<?php

namespace Drupal\projects;

use Drupal\Component\Serialization\Json;
use Drupal\projects\Entity\Project;
use Drupal\youvo\FieldValidator;
use Drupal\youvo\RestContentShifter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * Create new project node with some validation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\projects\Entity\Project
   */
  public function createProject(Request $request) {

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Decline request body without project data.
    if (empty($content['data']) ||
      !in_array('project', array_column($content['data'], 'type'))) {
      throw new BadRequestHttpException('Request body does not provide project data.');
    }

    // Get attributes from request content.
    $attributes = RestContentShifter::shiftAttributesByType($content, 'project');

    // Check if body is provided.
    if (empty($attributes['body'])) {
      throw new BadRequestHttpException('Need to provide body to create project.');
    }

    // Create new project node.
    $project = Project::create(['type' => 'project']);

    // Populate fields.
    foreach ($attributes as $field_key => $value) {

      // Validate if field is available.
      if (!$project->hasField($field_key)) {
        $field_key = 'field_' . $field_key;
        if (!$project->hasField($field_key)) {
          throw new BadRequestHttpException('Malformed request body. Projects do not provide the field ' . $field_key);
        }
      }

      // Validate field value.
      $field_definition = $project->getFieldDefinition($field_key);
      if (!FieldValidator::validate($field_definition, $value)) {
        throw new BadRequestHttpException('Malformed request body. Unable to validate the project field ' . $field_key);
      }

      // Set the field value.
      $project->get($field_key)->value = $value;
    }

    return $project;
  }

}
