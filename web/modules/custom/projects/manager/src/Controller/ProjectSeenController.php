<?php

namespace Drupal\manager\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\manager\ProjectSeenTracker;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Confirms that a manager has seen the changes of projects.
 */
class ProjectSeenController extends ControllerBase {

  /**
   * Where the user returns to if the destination is not a local path.
   */
  protected const DEFAULT_DESTINATION = '/admin/dashboard';

  public function __construct(protected ProjectSeenTracker $tracker) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('manager.project_seen'));
  }

  /**
   * Checks if the user manages the organization of the project.
   */
  public function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {
    return AccessResult::allowedIf($this->tracker->isManagedBy($account, $project))
      ->addCacheContexts(['user'])
      ->addCacheableDependency($project);
  }

  /**
   * Confirms one project.
   */
  public function seen(Request $request, ProjectInterface $project): RedirectResponse {
    $this->tracker->markSeen($this->currentUser(), $project);
    return $this->back($request);
  }

  /**
   * Confirms all the unfinished projects of the user.
   */
  public function seenAll(Request $request): RedirectResponse {
    $this->tracker->markAllSeen($this->currentUser());
    return $this->back($request);
  }

  /**
   * Redirects to the destination if it is a local path.
   */
  protected function back(Request $request): RedirectResponse {
    $destination = (string) $request->query->get('destination', '');
    $local = str_starts_with($destination, '/')
      && !str_starts_with($destination, '//')
      && !preg_match('/[\x00-\x1F\x7F\\\\]/', $destination);
    return new RedirectResponse($local ? $destination : self::DEFAULT_DESTINATION);
  }

}
