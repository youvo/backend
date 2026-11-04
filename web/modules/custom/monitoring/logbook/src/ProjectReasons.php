<?php

namespace Drupal\logbook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;

/**
 * Reads why a project was declined or archived.
 *
 * The decline and archive subscribers keep the reason in the misc data of
 * their log, so the latest such log of a project holds the reason.
 */
class ProjectReasons {

  /**
   * Constructs the reader.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the reason of the latest decline of a project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Receives the cacheability of the lookup and the access check.
   *
   * @return \Drupal\projects\ProjectDeclineReason|null
   *   The reason, or NULL if there is none the current user may see.
   */
  public function getDeclineReason(ProjectInterface $project, RefinableCacheableDependencyInterface $cacheability): ?ProjectDeclineReason {
    return ProjectDeclineReason::tryFrom($this->getReason($project, 'project_decline', $cacheability));
  }

  /**
   * Gets the reason of the latest archiving of a project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Receives the cacheability of the lookup and the access check.
   *
   * @return \Drupal\projects\ProjectArchiveReason|null
   *   The reason, or NULL if there is none the current user may see.
   */
  public function getArchiveReason(ProjectInterface $project, RefinableCacheableDependencyInterface $cacheability): ?ProjectArchiveReason {
    return ProjectArchiveReason::tryFrom($this->getReason($project, 'project_archive', $cacheability));
  }

  /**
   * Gets the reason in the latest log of a type the current user may see.
   */
  protected function getReason(ProjectInterface $project, string $type, RefinableCacheableDependencyInterface $cacheability): string {
    $cacheability->addCacheTags(['log_list']);
    $storage = $this->entityTypeManager->getStorage('log');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->condition('project', $project->id())
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    $log = $ids ? $storage->load(reset($ids)) : NULL;
    if (!$log instanceof LogInterface) {
      return '';
    }
    $access = $log->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($access)->addCacheableDependency($log);
    return $access->isAllowed() ? (string) ($log->getMisc()['reason'] ?? '') : '';
  }

}
