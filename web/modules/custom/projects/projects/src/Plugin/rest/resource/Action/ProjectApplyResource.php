<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
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

    // The user requires the permission to do this action.
    $permission = 'restful post project:apply';
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The resource should define project-dependent access conditions.
    $project_condition = $project->isPublished() && $project->lifecycle()->isOpen();
    $access_project = AccessResult::allowedIf($project_condition)
      ->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this application are not met.');
    }
    $access_result = $access_result->andIf($access_project);

    // The resource should define applicant-dependent access conditions. We
    // assume that the user type can not change.
    $organization = $project->getOwner();
    $applicant_condition = Profile::isCreative($account) && !$organization->isManager($account) && !$project->isApplicant($account);
    $access_applicant = AccessResult::allowedIf($applicant_condition)
      ->addCacheableDependency($organization)
      ->addCacheableDependency($project);
    if ($access_applicant instanceof AccessResultReasonInterface) {
      $access_applicant->setReason('The applicant conditions for this application are not met. The creative may already applied.');
    }

    return $access_result->andIf($access_applicant);
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
    $applicant = $this->currentUser->getAccount();

    // Safeguard against a current user that is not a creative.
    if (!$applicant instanceof Creative) {
      // @codeCoverageIgnoreStart
      return new ModifiedResourceResponse('The application is not possible for the current user.');
      // @codeCoverageIgnoreEnd
    }

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
