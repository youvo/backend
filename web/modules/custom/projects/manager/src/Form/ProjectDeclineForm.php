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
 * The mail is sent first; the project is only declined if the mail was sent.
 * The pane (see ManagerContextPaneTransition) reads the outcome from the form
 * state: the flags of the mail form and 'decline_failed'.
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
   * Declines the project with the reason of the sent mail.
   */
  protected function afterSend(ProjectInterface $project, string $subtype, FormStateInterface $form_state): void {
    $reason = ManagerMailRegistry::declineReason($subtype);
    // acceptsSubtype() only lets subtypes with a reason through: fail closed.
    if ($reason === NULL) {
      $form_state->set('decline_failed', TRUE);
      return;
    }
    try {
      $this->eventDispatcher->dispatch((new ProjectDeclineEvent($project))->setReason($reason));
    }
    catch (\Throwable $e) {
      // The pane shows the error message when it sees this flag.
      $form_state->set('decline_failed', TRUE);
      // A later listener may fail after the project was declined and saved:
      // the failure is only reported if the stored project is not declined.
      try {
        $stored = $this->entityTypeManager->getStorage('project')->loadUnchanged($project->id());
        $declined = $stored instanceof ProjectInterface && $stored->lifecycle()->isDeclined();
      }
      catch (\Throwable) {
        $declined = FALSE;
      }
      $context = ['@id' => $project->id(), '@message' => $e->getMessage()];
      if ($declined) {
        $form_state->set('decline_failed', FALSE);
        $this->logger('manager')->error('The project @id was declined, but a listener of the decline failed: @message', $context);
      }
      else {
        $this->logger('manager')->error('The project @id could not be declined after the decline mail was sent: @message', $context);
      }
    }
  }

}
