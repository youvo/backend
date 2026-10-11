<?php

namespace Drupal\manager\Timeline;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectInterface;

/**
 * Merges logbook events, mails and notes of a project into one timeline.
 */
class TimelineBuilder {

  use StringTranslationTrait;

  /**
   * Constructs the builder.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ManagerMailAccess $access,
  ) {}

  /**
   * Builds the rows, newest first.
   *
   * @return array<int, array{type: string, time: int, build: array, actions?: array}>
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
      $row = [
        'type' => $entry->isNote() ? 'note' : 'mail',
        'id' => (int) $entry->id(),
        'time' => $entry->getOccurredTime(),
        'build' => $entry_builder->view($entry),
      ];
      // The links depend on the user: they are not part of the entry build,
      // which is render cached per entity without a user cache context.
      if ($entry->isNote() && $actions = $this->noteActions($entry, $account)) {
        $row['actions'] = $actions;
      }
      $rows[] = $row;
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

  /**
   * Builds the modal links to edit and delete a note for the account.
   *
   * @return array<string, array>
   *   The link render arrays keyed by operation, without the not permitted.
   */
  protected function noteActions(CorrespondenceInterface $note, AccountInterface $account): array {
    if (!$this->access->noteEntryAccess($account, $note)->isAllowed()) {
      return [];
    }
    $operations = [
      'edit' => ['update', 'manager.note_edit', $this->t('Edit note')],
      'delete' => ['delete', 'manager.note_delete', $this->t('Delete note')],
    ];
    $actions = [];
    foreach ($operations as $key => [$operation, $route, $title]) {
      if (!$note->access($operation, $account)) {
        continue;
      }
      $actions[$key] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute($route, ['correspondence' => $note->id()]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 760,
            'title' => (string) $title,
          ]),
        ],
      ];
    }
    return $actions;
  }

}
