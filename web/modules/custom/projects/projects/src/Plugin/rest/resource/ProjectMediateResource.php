<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "project:mediate",
 *   label = @Translation("Project Mediate Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/mediate"
 *   }
 * )
 */
class ProjectMediateResource extends ProjectTransitionResourceBase {

  protected const TRANSITION = 'mediate';

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
   * {@inheritdoc}
   */
  protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool {
    return $project->isAuthor($account) || $project->getOwner()->isManager($account);
  }

  /**
   * Responds to GET requests.
   */
  public function get(ProjectInterface $project): ResourceResponseInterface {

    // Fetch applicants in desired structure.
    $applicants = [];
    $manager = $project->getOwner()->getManager();
    foreach ($project->getApplicants() as $applicant) {
      if ($applicant->id() != $manager?->id()) {
        $applicants[] = [
          'type' => 'user',
          'id' => $applicant->uuid(),
          'name' => $applicant->getName(),
        ];
      }
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => str_replace(':', '.', $this->pluginId),
      'data' => [
        'type' => 'project',
        'id' => $project->uuid(),
        'title' => $project->getTitle(),
        'applicants' => $applicants,
      ],
      'post_required' => [
        'selected_creatives' => 'Array of uuid\'s of creatives.',
      ],
    ]);

    // Add cacheable dependency to refresh response when project is updated.
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    // Decode content of the request.
    $request_content = Json::decode($request->getContent());

    // The selected_creatives are required to process the request.
    if (!array_key_exists('selected_creatives', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify selected_creatives.');
    }

    // Set preliminary selected_creatives variable.
    $selected_creatives = array_unique($request_content['selected_creatives']);

    // Force at least one selected creative.
    if (empty($selected_creatives)) {
      throw new BadRequestHttpException('The selected_creatives array in the request body is empty.');
    }

    // The selected_creatives is expected to be delivered as a simple array.
    if (count(array_filter(array_keys($selected_creatives), 'is_string')) > 0) {
      throw new BadRequestHttpException('The selected_creatives array in the request body is malformed.');
    }

    // The entries of the selected creatives array are expected to be UUIDs.
    if (count(array_filter($selected_creatives, [Uuid::class, 'isValid'])) !== count($selected_creatives)) {
      throw new BadRequestHttpException('The entries of the selected_creatives array are not valid UUIDs.');
    }

    // Get applicants for current project and check if selected_creatives are
    // applicable.
    $applicants = $project->getApplicants();
    $applicants_uuids = array_map(static fn ($a) => $a->uuid(), $applicants);
    if (count(array_intersect($selected_creatives, $applicants_uuids)) !== count($selected_creatives)) {
      throw new UnprocessableEntityHttpException('Some selected creatives did not apply for this project.');
    }

    // Now we are finally sure to mediate the project. We get the UIDs by query.
    try {
      $selected_creatives_ids = $this->entityTypeManager
        ->getStorage('user')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('uuid', $selected_creatives, 'IN')
        ->execute();
      $selected_creatives_ids = array_map('intval', $selected_creatives_ids);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new UnprocessableEntityHttpException('Could not mediate project.', $e);
    }

    // Mediate project with participants.
    if (!empty($selected_creatives_ids) && $project->lifecycle()->mediate()) {

      $project->setPromoted(FALSE);
      $project->setParticipants($selected_creatives_ids);
      if ($manager = $project->getOwner()->getManager()) {
        $project->appendParticipant($manager, 'Manager');
      }
      $project->save();

      $this->eventDispatcher->dispatch(new ProjectMediateEvent($project));

      return new ResourceResponse('Project was mediated successfully.');
    }

    throw new UnprocessableEntityHttpException('Could not mediate project.');
  }

}
