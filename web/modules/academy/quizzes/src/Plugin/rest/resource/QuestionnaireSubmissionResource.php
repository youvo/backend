<?php

namespace Drupal\quizzes\Plugin\rest\resource;

use Drupal\quizzes\Entity\Quiz;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "questionnaire:submission",
 *   label = @Translation("Questionnaire Submission Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/questionnaire/{questionnaire}/submission",
 *     "create" = "/api/questionnaire/{questionnaire}/submission"
 *   }
 * )
 */
class QuestionnaireSubmissionResource extends ResourceBase {

  /**
   * Responds GET requests.
   *
   * @param \Drupal\quizzes\Entity\Quiz $questionnaire
   *   The referenced questionnaire.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function get(Quiz $questionnaire) {

    // Fetch questions and answers.
    $submission['type'] = $questionnaire->bundle();
    $submission['uuid'] = $questionnaire->uuid();
    $submission['questions'] = [];
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $questions_field */
    $questions_field = $questionnaire->get('questions');
    $questions = $questions_field->referencedEntities();
    /** @var \Drupal\quizzes\Entity\Question $question */
    foreach ($questions as $question) {
      $submission['questions'][] = [
        'type' => $question->bundle(),
        'id' => $question->uuid(),
        'body' => $question->get('body')->value,
        'answer' => 'Wurst',
        'result' => TRUE,
      ];
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => 'questionnaire.resource.submission',
      'submission_available' => TRUE,
      'submission_expired' => FALSE,
      'data' => $submission,
      'post_required' => [
        'submission' => 'Array of values per question.',
      ],
    ]);

    // Add cacheable dependency to refresh response when questionnaire is
    // udpated.
    $response->addCacheableDependency($questionnaire);

    return $response;
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\quizzes\Entity\Quiz $questionnaire
   *   The referenced questionnaire.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function post(Quiz $questionnaire, Request $request) {

    // Decode content of the request.
    $request_content = \Drupal::service('serialization.json')->decode($request->getContent());

    return new ResourceResponse('Hello POST.', 200);
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
      $route->setRequirement('_custom_access', '\Drupal\quizzes\Controller\QuestionnaireAccessController::accessQuestionnaire');

      // Add route entity context parameters.
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'questionnaire' => [
          'type' => 'entity:paragraph',
          'converter' => 'paramconverter.uuid',
        ],
      ]);

      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
