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
   * @return array<int, array{type: string, id: int, time: int, build: array, actions?: array}>
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
    return $rows;
  }

  /**
   * Builds the in-pane links to edit and delete a note for the account.
   *
   * The links open the note views of the logbook pane (data-pane-view with the
   * note id as parameter); the pane checks the access again.
   *
   * @return array<string, array>
   *   The link render arrays keyed by operation, without the not permitted.
   */
  protected function noteActions(CorrespondenceInterface $note, AccountInterface $account): array {
    if (!$this->access->noteEntryAccess($account, $note)->isAllowed()) {
      return [];
    }
    $operations = [
      'edit' => ['update', 'note_edit', $this->t('Edit note'), 'manager-icon-edit'],
      'delete' => ['delete', 'note_delete', $this->t('Delete note'), 'manager-icon-delete'],
    ];
    $actions = [];
    foreach ($operations as $key => [$operation, $view, $title, $icon]) {
      if (!$note->access($operation, $account)) {
        continue;
      }
      $actions[$key] = [
        '#type' => 'link',
        '#title' => [
          '#markup' => '<span class="' . $icon . '" aria-hidden="true"></span><span class="visually-hidden">' . $title . '</span>',
        ],
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $note->get('project')->target_id], [
          'query' => ['type' => 'logbook', 'view' => $view, 'id' => $note->id()],
        ]),
        '#attributes' => [
          'class' => ['logbook-timeline-action'],
          'title' => $title,
          'data-pane-view' => $view,
          'data-pane-row' => 'note-' . $note->id(),
          'data-pane-params' => http_build_query(['id' => $note->id()]),
        ],
      ];
    }
    return $actions;
  }

}
