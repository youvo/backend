<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\Form\ProjectDeclineForm;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a project transition manager context pane.
 *
 * Besides the next transition, the pane offers to decline a project proposal
 * (view 'decline'): a form that sends the rejection mail and then declines.
 */
#[ManagerContextPane(id: "transition")]
class ManagerContextPaneTransition extends ManagerContextPaneBase implements ManagerContextPaneViewInterface {

  use PaneMessagesTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The registry of manager mails.
   */
  protected ManagerMailRegistry $registry;

  /**
   * The access service of manager mails.
   */
  protected ManagerMailAccess $access;

  /**
   * The form builder.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The messenger.
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->registry = $container->get('mailer.manager_mail_registry');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->formBuilder = $container->get('form_builder');
    $instance->messenger = $container->get('messenger');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    $transition = $this->getNextTransition($project);
    if (!$transition instanceof ProjectTransition) {
      return [];
    }

    $build = [
      '#theme' => 'context_pane',
      '#type' => 'transition',
      '#project' => $project,
      'content' => [
        'button' => [
          '#type' => 'button',
          // phpcs:ignore
          '#value' => $this->t($transition->name),
          '#attributes' => [
            'class' => [
              'button',
              'button--primary',
              'button--small',
              'js-transition-btn',
            ],
            'data-action' => $transition->value,
          ],
        ],
        'options' => match ($transition) {
          ProjectTransition::Mediate => $this->buildMediateOptions($project),
          default => NULL,
        },
        'users' => match ($transition) {
          ProjectTransition::Complete => $this->buildCompleteUsers($project),
          default => NULL,
        },
        // @todo Check the workflow and adjust descriptions.
        'description' => match ($transition) {
          ProjectTransition::Submit => $this->buildSubmitDescription($project),
          ProjectTransition::Publish => $this->buildPublishDescription($project),
          ProjectTransition::Mediate => $this->buildMediateDescription($project),
          ProjectTransition::Complete => $this->buildCompleteDescription($project),
          default => NULL,
        },
      ],
    ];

    $decline_subtype = ManagerMailRegistry::declineSubtype(ProjectDeclineReason::Custom);
    if ($this->mayDecline($project, $decline_subtype)) {
      $build['content']['decline'] = [
        '#type' => 'link',
        '#title' => $this->t('Decline'),
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
          'query' => ['type' => 'transition', 'view' => 'decline'],
        ]),
        '#attributes' => [
          'class' => ['button', 'button--small', 'button--danger'],
          'data-pane-view' => 'decline',
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildView(Project $project, Request $request): array {
    if ($request->query->get('view') !== 'decline') {
      return $this->build($project);
    }
    $reason = ProjectDeclineReason::tryFrom((string) $request->query->get('reason', ProjectDeclineReason::Custom->value));
    if (!$reason instanceof ProjectDeclineReason) {
      return $this->emptyDeclinePane($project);
    }
    return $this->buildDeclineView($project, ManagerMailRegistry::declineSubtype($reason), $request);
  }

  /**
   * Whether the current user may decline the project with the mail subtype.
   */
  protected function mayDecline(Project $project, string $subtype): bool {
    return ProjectDeclineForm::mayDecline($this->currentUser)
      && $this->access->access($this->currentUser, $project, $subtype)->isAllowed();
  }

  /**
   * Builds the empty decline pane.
   */
  protected function emptyDeclinePane(Project $project): array {
    return ['#theme' => 'context_pane', '#type' => 'decline', '#project' => $project, 'content' => []];
  }

  /**
   * Builds the decline form view; access is checked on every request.
   *
   * A valid POST sends the mail and then declines the project in the form's
   * submit handler; the pane then only shows the outcome. A failed send
   * returns the form again.
   */
  protected function buildDeclineView(Project $project, string $subtype, Request $request): array {
    if (!$this->mayDecline($project, $subtype)) {
      return $this->emptyDeclinePane($project);
    }
    $form_state = new FormState();
    // Without this the form API would try to produce a redirect response.
    $form_state->disableRedirect();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    // A POST with a form_build_id restores the build info of the earlier GET:
    // tell the form which project and subtype this request is for.
    $request->attributes->set('manager_pane_mail', [(string) $project->id(), $subtype]);
    $form = $this->formBuilder->buildForm(ProjectDeclineForm::class, $form_state);
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $built_for = ($args[0] ?? NULL) instanceof Project ? [(string) $args[0]->id(), $args[1] ?? NULL] : NULL;
    if ($built_for !== NULL && $built_for !== [(string) $project->id(), $subtype]) {
      return $this->emptyDeclinePane($project);
    }
    if ($form_state->get('send_denied')) {
      return $this->emptyDeclinePane($project);
    }
    $build = $this->emptyDeclinePane($project);
    if ($form_state->get('already_submitted')) {
      $this->messenger->addStatus($this->t('This form was already submitted.'));
      $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
      return $build;
    }
    if ($form_state->get('sent')) {
      if ($form_state->get('decline_failed')) {
        $this->messenger->addError($this->t('The mail was sent, but the project could not be declined. Please contact a supervisor.'));
      }
      else {
        $this->messenger->addStatus($this->t('The project was declined and the mail was sent.'));
      }
      $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
      return $build;
    }
    if ($form_state->get('send_failed')) {
      // symfony_mailer reports the transport error (host, port) on the
      // messenger as error or warning: show the manager only our own message.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
      $this->messenger->deleteByType(MessengerInterface::TYPE_WARNING);
      $this->messenger->addError($this->t('The mail could not be sent. The project was not declined.'));
    }
    $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
    $build['content']['form'] = $form;
    $this->buildReasons($build, $project, $subtype);
    return $build;
  }

  /**
   * Adds the links of the decline reasons.
   *
   * @param array $build
   *   The pane build.
   * @param \Drupal\projects\Entity\Project $project
   *   The project.
   * @param string $active
   *   The subtype whose form is open, which is marked as current.
   */
  protected function buildReasons(array &$build, Project $project, string $active): void {
    foreach ($this->registry->availableDeclines($project) as $definition) {
      $reason = ManagerMailRegistry::declineReason($definition->subtype);
      if ($reason === NULL) {
        continue;
      }
      $is_active = $definition->subtype === $active;
      $build['content']['reasons'][$reason->value] = [
        '#type' => 'link',
        '#title' => $definition->label,
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
          'query' => ['type' => 'transition', 'view' => 'decline', 'reason' => $reason->value],
        ]),
        '#attributes' => [
          'class' => $is_active ? ['manager-mail-type', 'is-active'] : ['manager-mail-type'],
          'data-pane-view' => 'decline',
          'data-pane-params' => http_build_query(['reason' => $reason->value]),
        ] + ($is_active ? ['aria-current' => 'true'] : []),
      ];
    }
  }

  /**
   * Buils the mediate transition options.
   */
  protected function buildMediateOptions(ProjectInterface $project): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    foreach ($project->getApplicants() as $applicant) {
      $options[$applicant->uuid()] = $view_builder->view($applicant, 'option');
    }
    return $options ?? [];
  }

  /**
   * Buils the complete transition users.
   */
  protected function buildCompleteUsers(ProjectInterface $project): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    foreach ($project->getParticipants('Creative') as $applicant) {
      $users[$applicant->uuid()] = $view_builder->view($applicant, 'option');
    }
    return $users ?? [];
  }

  /**
   * Builds the mediate transition description.
   */
  protected function buildMediateDescription(ProjectInterface $project): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will mediate the project.') . '</li>' .
      '    <li>' . $this->t('The project will not appear in searches and will not be promoted anymore.') . '</li>' .
      '    <li>' . $this->t('Each selected applicant receives an email from the organization.') . '</li>' .
      '    <li>' . $this->t('The organization receives an email.') . '</li>' .
      '  </ul>',
    ];
  }

  /**
   * Builds the submit transition description.
   */
  protected function buildSubmitDescription(ProjectInterface $project): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will submit the project for review.') . '</li>' .
      '    <li>' . $this->t('If the organization is a prospect, it will be promoted to a proper organization.') . '</li>' .
      '    <li>' . $this->t('The organization receives a notification.') . '</li>' .
      '  </ul>',
    ];
  }

  /**
   * Builds the publish transition description.
   */
  protected function buildPublishDescription(ProjectInterface $project): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will publish the project.') . '</li>' .
      '    <li>' . $this->t('The project will appear in searches and be visible to creatives.') . '</li>' .
      '    <li>' . $this->t('Creatives can apply to the project.') . '</li>' .
      '    <li>' . $this->t('The organization receives a notification.') . '</li>' .
      '  </ul>',
    ];
  }

  /**
   * Builds the complete transition description.
   */
  protected function buildCompleteDescription(ProjectInterface $project): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will complete the project.') . '</li>' .
      '    <li>' . $this->t('The project will appear in searches.') . '</li>' .
      '    <li>' . $this->t('Each selected participant receives an email from the organization.') . '</li>' .
      '    <li>' . $this->t('The organization receives an email.') . '</li>' .
      '  </ul>',
    ];
  }

  /**
   * Gets next transition assuming linear transition flow.
   *
   * This method does not validate whether the transition is possible.
   *
   * @return \Drupal\projects\ProjectTransition|null
   *   The project transition or NULL if not applicable.
   */
  protected function getNextTransition(ProjectInterface $project): ?ProjectTransition {
    return match (TRUE) {
      $project->lifecycle()->isDraft() => ProjectTransition::Submit,
      $project->lifecycle()->isPending() => ProjectTransition::Publish,
      $project->lifecycle()->isOpen() => ProjectTransition::Mediate,
      $project->lifecycle()->isOngoing() => ProjectTransition::Complete,
      default => NULL,
    };
  }

}
