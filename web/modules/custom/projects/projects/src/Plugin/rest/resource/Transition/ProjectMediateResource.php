<?php

namespace Drupal\projects\Plugin\rest\resource\Transition;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\creatives\Entity\Creative;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
class ProjectMediateResource extends ProjectResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't transition, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    if ($account->hasPermission(WorkflowPermissions::bypassTransition($workflow_id))) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerPermissions();
    }

    // The user requires the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Mediate->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The author and the manager mediate the project.
    $organization = $project->getOwner();
    $access_project = AccessResult::allowedIf($project->isAuthor($account) || $organization->isManager($account))
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
      ->cachePerUser();
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this transition are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to GET requests with the applicants, except the manager.
   */
  public function get(ProjectInterface $project): ResourceResponseInterface {

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
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent()) ?? [];
    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }
    $this->validateRequestContent($content);

    $event = new ProjectMediateEvent($project);
    $event->setCreatives($this->loadSelectedCreatives($content));

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be mediated.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project mediate failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project mediated.');
  }

  /**
   * Validates the request content.
   */
  protected function validateRequestContent(array $content): void {

    // The selected_creatives are required to process the request.
    if (!array_key_exists('selected_creatives', $content)) {
      throw new BadRequestHttpException('Request body does not specify selected_creatives.');
    }

    // Checked before array_unique(), which needs strings.
    $selected_creatives = $content['selected_creatives'];
    if (!is_array($selected_creatives) || array_filter($selected_creatives, static fn($uuid) => !is_string($uuid))) {
      throw new BadRequestHttpException('The selected_creatives in the request body are not an array of strings.');
    }
    $selected_creatives = array_unique($selected_creatives);

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

    // Safeguard against unintentionally loaded users that are not creatives.
    foreach ($selected_creatives as $uid => $creative) {
      if (!$creative instanceof Creative) {
        // @codeCoverageIgnoreStart
        unset($selected_creatives[$uid]);
        // @codeCoverageIgnoreEnd
      }
    }

    return $selected_creatives;
  }

}
