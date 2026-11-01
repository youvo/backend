<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Lets a manager decline a project with an edited rejection mail.
 *
 * Dispatches the decline event; the mailer module sends the mail.
 */
class ProjectDeclineForm extends ProjectMailForm {

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
   * Whether the account may use the decline transition.
   */
  public static function mayDecline(AccountInterface $account): bool {
    return $account->hasPermission(WorkflowPermissions::useTransition(ProjectLifecycle::WORKFLOW_ID, ProjectTransition::Decline->value))
      || $account->hasPermission(WorkflowPermissions::bypassTransition(ProjectLifecycle::WORKFLOW_ID));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_decline_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, string $subtype = ''): array {
    $form = parent::buildForm($form, $form_state, $project, $subtype);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $this->t('Decline and send');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function acceptsSubtype(string $subtype): bool {
    return ManagerMailRegistry::declineReason($subtype) !== NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Declining also requires the decline transition permission.
   */
  protected function authorizedProject(FormStateInterface $form_state): ?ProjectInterface {
    $project = parent::authorizedProject($form_state);
    if ($project === NULL || !static::mayDecline($this->currentUser())) {
      return NULL;
    }
    return $project;
  }

  /**
   * {@inheritdoc}
   *
   * Declines the project; the mailer sends the edited mail afterwards.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // A repeated submitForm() call on this in-memory form state.
    if ($form_state->get('sent')) {
      return;
    }
    $form_state->set('send_failed', FALSE);
    $project = $this->authorizedProject($form_state);
    $subtype = (string) ($form_state->getBuildInfo()['args'][1] ?? '');
    $reason = ManagerMailRegistry::declineReason($subtype);
    if ($project === NULL || $reason === NULL) {
      $form_state->set('send_denied', TRUE);
      return;
    }

    $event = (new ProjectDeclineEvent($project))
      ->setReason($reason)
      ->setMail((string) $form_state->getValue('subject'), (string) $form_state->getValue('body'))
      ->setSender($this->currentUser());

    // A replayed POST must not decline and mail again.
    $declined = $this->once->runOnce($form_state, fn (): bool => $this->decline($event));
    if ($declined === NULL) {
      $form_state->set('already_submitted', TRUE);
    }
    elseif ($declined) {
      $form_state->set('sent', TRUE);
    }
    else {
      // Nothing happened: show the form again with the input.
      $form_state->set('send_failed', TRUE);
      $form_state->setRebuild();
    }
  }

  /**
   * Dispatches the decline event.
   *
   * @return bool
   *   TRUE if the project is declined, even if a later listener failed.
   */
  protected function decline(ProjectDeclineEvent $event): bool {
    try {
      $this->eventDispatcher->dispatch($event);
      return TRUE;
    }
    catch (\Throwable $e) {
      // A later listener may fail after the project was declined and saved.
      $project = $event->getProject();
      $declined = $this->isDeclined($project);
      $context = ['@id' => $project->id(), '@message' => $e->getMessage()];
      if ($declined) {
        $this->logger('manager')->error('The project @id was declined, but a listener of the decline failed: @message', $context);
      }
      else {
        $this->logger('manager')->error('The project @id could not be declined: @message', $context);
      }
      return $declined;
    }
  }

  /**
   * Whether the stored project is declined.
   */
  protected function isDeclined(ProjectInterface $project): bool {
    try {
      $stored = $this->entityTypeManager->getStorage('project')->loadUnchanged($project->id());
    }
    catch (\Throwable) {
      return FALSE;
    }
    return $stored instanceof ProjectInterface && $stored->lifecycle()->isDeclined();
  }

}
