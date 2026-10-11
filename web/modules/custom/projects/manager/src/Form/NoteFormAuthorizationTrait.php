<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\projects\ProjectInterface;

/**
 * Re-checks, on the actual data, what a note form is allowed to change.
 *
 * A POST that carries a form_build_id restores the build info (project and
 * note) of an earlier build, so neither the serialized entities nor the
 * cached ids can be trusted. The using form must provide $entityTypeManager
 * and $access and extend FormBase.
 */
trait NoteFormAuthorizationTrait {

  /**
   * The name of the request attribute the pane puts its arguments in.
   */
  public const PANE_ATTRIBUTE = 'manager_pane_note';

  /**
   * Gets the project and the note from the (cached) build arguments.
   *
   * @return array{0: \Drupal\projects\ProjectInterface|null, 1: \Drupal\correspondence\CorrespondenceInterface|null}
   *   The project (add mode) and the entry (edit and delete mode).
   */
  protected function buildArguments(FormStateInterface $form_state): array {
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $project = $args[0] ?? NULL;
    $correspondence = $args[1] ?? NULL;
    return [
      $project instanceof ProjectInterface ? $project : NULL,
      $correspondence instanceof CorrespondenceInterface ? $correspondence : NULL,
    ];
  }

  /**
   * Gets the freshly loaded project and note if the user may act on them now.
   *
   * Reloads both from storage, requires the note to belong to the project and
   * checks the access on these actual values. If the pane put the project and
   * note ids of the current request on the request, they must match the cached
   * build arguments. Fails closed.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation
   *   One of 'create' (add a note), 'update' or 'delete' (the note in the
   *   build info).
   *
   * @return array{0: \Drupal\projects\ProjectInterface, 1: \Drupal\correspondence\CorrespondenceInterface|null}|null
   *   The project and the entry (NULL when creating), or NULL if not allowed.
   */
  protected function authorizedNote(FormStateInterface $form_state, string $operation): ?array {
    [$cached_project, $cached_entry] = $this->buildArguments($form_state);
    if (($operation === 'create') === ($cached_entry instanceof CorrespondenceInterface)) {
      return NULL;
    }
    $expected = $this->getRequest()->attributes->get(self::PANE_ATTRIBUTE);
    if (is_array($expected) && $expected !== [(string) $cached_project?->id(), (string) $cached_entry?->id()]) {
      return NULL;
    }
    $entry = NULL;
    if ($cached_entry instanceof CorrespondenceInterface) {
      $entry = $this->entityTypeManager->getStorage('correspondence')->load($cached_entry->id());
      if (!$entry instanceof CorrespondenceInterface || !$entry->isNote()) {
        return NULL;
      }
    }
    $project_id = $cached_project?->id() ?? $entry?->get('project')->target_id;
    $project = $project_id === NULL ? NULL : $this->entityTypeManager->getStorage('project')->load($project_id);
    if (!$project instanceof ProjectInterface) {
      return NULL;
    }
    $account = $this->currentUser();
    if ($entry === NULL) {
      // The create access is permission-based, so check the project as well.
      $allowed = $this->entityTypeManager->getAccessControlHandler('correspondence')->createAccess('note', $account)
        && $this->access->noteAccess($account, $project)->isAllowed();
    }
    else {
      // A note can only be changed together with its own project.
      $allowed = (string) $entry->get('project')->target_id === (string) $project->id()
        && $this->access->noteEntryAccess($account, $entry)->isAllowed()
        && $entry->access($operation, $account);
    }
    return $allowed ? [$project, $entry] : NULL;
  }

}
