<?php

namespace Drupal\manager\Timeline;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\projects\ProjectInterface;

/**
 * Merges logbook events, mails and notes of a project into one timeline.
 */
class TimelineBuilder {

  /**
   * Constructs the builder.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Builds the rows, newest first.
   *
   * @return array<int, array{type: string, time: int, build: array}>
   *   The rows.
   */
  public function build(ProjectInterface $project, AccountInterface $account): array {
    $rows = [];

    $log_builder = $this->entityTypeManager->getViewBuilder('log');
    $logs = $this->entityTypeManager->getStorage('log')
      ->loadByProperties(['project' => $project->id()]);
    // Newest first, so that logs with equal times keep the previous order.
    foreach (array_reverse($logs) as $log) {
      /** @var \Drupal\logbook\LogInterface $log */
      $rows[] = [
        'type' => 'log',
        'time' => (int) $log->getCreatedTime(),
        'build' => $log_builder->view($log),
      ];
    }

    $entry_builder = $this->entityTypeManager->getViewBuilder('correspondence');
    $entries = $this->entityTypeManager->getStorage('correspondence')
      ->loadByProperties(['project' => $project->id()]);
    foreach (array_reverse($entries) as $entry) {
      if (!$entry instanceof CorrespondenceInterface || !$entry->access('view', $account)) {
        continue;
      }
      $rows[] = [
        'type' => $entry->isNote() ? 'note' : 'mail',
        'time' => $entry->getOccurredTime(),
        'build' => $entry_builder->view($entry),
      ];
    }

    // The sort is stable: equal times keep the order built above.
    usort($rows, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);
    return $rows;
  }

}
