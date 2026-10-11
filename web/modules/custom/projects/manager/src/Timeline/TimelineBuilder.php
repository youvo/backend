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
    foreach ($logs as $log) {
      /** @var \Drupal\logbook\LogInterface $log */
      $rows[] = [
        'type' => 'log',
        'id' => (int) $log->id(),
        'time' => (int) $log->getCreatedTime(),
        'build' => $log_builder->view($log),
      ];
    }

    $entry_builder = $this->entityTypeManager->getViewBuilder('correspondence');
    $entries = $this->entityTypeManager->getStorage('correspondence')
      ->loadByProperties(['project' => $project->id()]);
    foreach ($entries as $entry) {
      if (!$entry instanceof CorrespondenceInterface || !$entry->access('view', $account)) {
        continue;
      }
      $rows[] = [
        'type' => $entry->isNote() ? 'note' : 'mail',
        'id' => (int) $entry->id(),
        'time' => $entry->getOccurredTime(),
        'build' => $entry_builder->view($entry),
      ];
    }

    // Newest first. Equal times are ordered by a fixed type order and then by
    // the newest entity id, so the result does not depend on the load order.
    $type_order = ['note' => 0, 'mail' => 1, 'log' => 2];
    usort($rows, static fn (array $a, array $b): int => [$b['time'], $type_order[$a['type']], $b['id']]
      <=> [$a['time'], $type_order[$b['type']], $a['id']]);
    return array_map(static function (array $row): array {
      unset($row['id']);
      return $row;
    }, $rows);
  }

}
