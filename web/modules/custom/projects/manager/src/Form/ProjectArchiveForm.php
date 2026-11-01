<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Lets a supervisor archive a project with an edited mail.
 *
 * Dispatches the archive event; the mailer module sends the mail.
 */
class ProjectArchiveForm extends ProjectMailForm {

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Whether the account may archive projects.
   */
  public static function mayArchive(AccountInterface $account): bool {
    return $account->hasPermission('archive projects') || $account->hasPermission('archive projects in any state');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, string $subtype = ''): array {
    $form = parent::buildForm($form, $form_state, $project, $subtype);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $this->t('Archive and send');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function acceptsSubtype(string $subtype): bool {
    return ManagerMailRegistry::archiveReason($subtype) !== NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Archiving also requires the archive permission.
   */
  protected function authorizedProject(FormStateInterface $form_state): ?ProjectInterface {
    $project = parent::authorizedProject($form_state);
    if ($project === NULL || !static::mayArchive($this->currentUser())) {
      return NULL;
    }
    return $project;
  }

  /**
   * {@inheritdoc}
   *
   * Archives the project; the mailer sends the edited mail afterwards.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // A repeated submitForm() call on this in-memory form state.
    if ($form_state->get('sent')) {
      return;
    }
    $form_state->set('send_failed', FALSE);
    $project = $this->authorizedProject($form_state);
    $subtype = (string) ($form_state->getBuildInfo()['args'][1] ?? '');
    $reason = ManagerMailRegistry::archiveReason($subtype);
    if ($project === NULL || $reason === NULL) {
      $form_state->set('send_denied', TRUE);
      return;
    }

    $event = (new ProjectArchiveEvent($project))
      ->setReason($reason)
      ->setMail((string) $form_state->getValue('subject'), (string) $form_state->getValue('body'))
      ->setSender($this->currentUser());

    // A replayed POST must not archive and mail again.
    $archived = $this->once->runOnce($form_state, fn (): bool => $this->archive($event));
    if ($archived === NULL) {
      $form_state->set('already_submitted', TRUE);
    }
    elseif ($archived) {
      $form_state->set('sent', TRUE);
    }
    else {
      // Nothing happened: show the form again with the input.
      $form_state->set('send_failed', TRUE);
      $form_state->setRebuild();
    }
  }

  /**
   * Dispatches the archive event.
   *
   * @return bool
   *   TRUE if the project is archived, even if a later listener failed.
   */
  protected function archive(ProjectArchiveEvent $event): bool {
    try {
      $this->eventDispatcher->dispatch($event);
      return TRUE;
    }
    catch (\Throwable $e) {
      // A later listener may fail after the project was archived and saved.
      $project = $event->getProject();
      $archived = $this->isArchived($project);
      $context = ['@id' => $project->id(), '@message' => $e->getMessage()];
      if ($archived) {
        $this->logger('manager')->error('The project @id was archived, but a listener of the archive failed: @message', $context);
      }
      else {
        $this->logger('manager')->error('The project @id could not be archived: @message', $context);
      }
      return $archived;
    }
  }

  /**
   * Whether the stored project is archived.
   */
  protected function isArchived(ProjectInterface $project): bool {
    try {
      $stored = $this->entityTypeManager->getStorage('project')->loadUnchanged($project->id());
    }
    catch (\Throwable) {
      return FALSE;
    }
    return $stored instanceof ProjectInterface && $stored->isArchived();
  }

}
