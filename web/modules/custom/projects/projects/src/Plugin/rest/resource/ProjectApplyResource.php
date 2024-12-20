<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Drupal\user_types\Utility\Profile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides project apply resource.
 *
 * @RestResource(
 *   id = "project:apply",
 *   label = @Translation("Project Apply Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/apply"
 *   }
 * )
 */
class ProjectApplyResource extends ProjectActionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    $access_result = AccessResult::allowed();

    // The project may not be open to apply.
    if (!$project->isPublished() || !$project->lifecycle()->isOpen()) {
      $access_result = AccessResult::forbidden('The project is not open for applications.');
    }

    // The user may not be allowed to apply to this project.
    if (!Profile::isCreative($account) || $project->getOwner()->isManager($account)) {
      $access_result = AccessResult::forbidden('The user is not allowed to apply to this project.');
    }

    // The user maybe already applied to this project.
    if ($project->isApplicant($account)) {
      $access_result = AccessResult::forbidden('The user already applied to this project.');
    }

    return $access_result->addCacheableDependency($project)->cachePerUser();
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponseInterface {
    return new ModifiedResourceResponse('The user may apply to the project.', 200);
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent());
    /** @var \Drupal\creatives\Entity\Creative $applicant */
    $applicant = $this->currentUser->getAccount();

    try {
      $event = new ProjectApplyEvent($project, $applicant);
      $event->setMessage($content['message'] ?? '');
      $event->setPhoneNumber($content['phone'] ?? '');
      $this->eventDispatcher->dispatch($event);
    }
    catch (\Throwable) {
    }

    return new ModifiedResourceResponse('Application completed.');
  }

}
