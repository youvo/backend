<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Drupal\user_types\Utility\Profile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
class ProjectApplyResource extends ProjectResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't change, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // The user requires the permission to do this action.
    $access_result = AccessResult::allowedIfHasPermission($account, 'restful post project:apply');

    // Creatives apply to visible open projects.
    $access_project = AccessResult::allowedIf(!$project->isHidden() && $project->lifecycle()->isOpen())
      ->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this application are not met.');
    }
    $access_result = $access_result->andIf($access_project);

    // Creatives apply once, and not to projects they manage. We assume that
    // the user type can not change.
    $organization = $project->getOwner();
    $access_applicant = AccessResult::allowedIf(
      Profile::isCreative($account) &&
      !$organization->isManager($account) &&
      !$project->isApplicant($account)
    )
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
      ->cachePerUser();
    if ($access_applicant instanceof AccessResultReasonInterface) {
      $access_applicant->setReason('The applicant conditions for this application are not met. The creative may already applied.');
    }

    return $access_result->andIf($access_applicant);
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponseInterface {
    return new ModifiedResourceResponse('The user may apply to the project.');
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent()) ?? [];
    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }
    $message = $content['message'] ?? '';
    $phone = $content['phone'] ?? '';
    if (!is_string($message) || !is_string($phone)) {
      throw new BadRequestHttpException('The message or the phone number is not a string.');
    }

    // Safeguard against a current user that is not a creative.
    $applicant = Profile::account($this->currentUser);
    if (!Profile::isCreative($applicant)) {
      // @codeCoverageIgnoreStart
      return new ModifiedResourceResponse('The application is not possible for the current user.');
      // @codeCoverageIgnoreEnd
    }

    /** @var \Drupal\creatives\Entity\Creative $applicant */
    $event = new ProjectApplyEvent($project, $applicant);
    $event->setMessage($message);
    $event->setPhoneNumber($phone);

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project apply failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project applied.');
  }

}
