<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides project mediate resource.
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
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent());
    $this->validateRequestContent($content);

    try {
      $selected_creatives = $this->loadSelectedCreatives($content);
      $event = new ProjectMediateEvent($project);
      $event->setCreatives($selected_creatives);
      $this->eventDispatcher->dispatch(new ProjectMediateEvent($project));
    }
    catch (LifecycleTransitionException | InvalidPluginDefinitionException | PluginNotFoundException) {
      throw new UnprocessableEntityHttpException('Could not mediate project.');
    }
    catch (\Throwable) {
    }

    return new ResourceResponse('Project was mediated successfully.');
  }

  /**
   * Validates the request content.
   */
  protected function validateRequestContent(array $content): void {

    // The selected_creatives are required to process the request.
    if (!array_key_exists('selected_creatives', $content)) {
      throw new BadRequestHttpException('Request body does not specify selected_creatives.');
    }

    // Set preliminary selected_creatives variable.
    $selected_creatives = array_unique($content['selected_creatives'] ?? []);

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
  }

  /**
   * Loads the selected creatives from the request content.
   *
   * @param array $content
   *   The request content.
   *
   * @return \Drupal\creatives\Entity\Creative[]
   *   The selected creatives.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadSelectedCreatives(array $content): array {
    $selected_creatives_uuids = array_unique($content['selected_creatives'] ?? []);
    $selected_creatives_ids = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $selected_creatives_uuids, 'IN')
      ->execute();
    $selected_creatives_ids = array_map('intval', $selected_creatives_ids);
    /** @var \Drupal\creatives\Entity\Creative[] $selected_creatives */
    $selected_creatives = $this->entityTypeManager
      ->getStorage('user')
      ->loadMultiple($selected_creatives_ids);
    return $selected_creatives;
  }

}
