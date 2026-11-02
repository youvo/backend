<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a project transition manager context pane.
 *
 * Besides the next transition, the pane offers to decline a project proposal
 * (view 'decline') with the mail editor.
 */
#[ManagerContextPane(id: "transition")]
class ManagerContextPaneTransition extends ManagerContextPaneBase implements ManagerContextPaneActionInterface, ManagerContextPaneViewInterface {

  use ProjectActionPaneTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->resourceManager = $container->get('plugin.manager.rest');
    $instance->registry = $container->get('mailer.manager_mail_registry');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function hasActions(ProjectInterface $project): bool {
    return $this->permittedTransition($project) !== NULL
      || $this->mayMail($project, 'decline', 'decline_custom');
  }

  /**
   * {@inheritdoc}
   */
  public function build(ProjectInterface $project): array {

    $build = [
      '#theme' => 'context_pane',
      '#type' => 'transition',
      '#project' => $project,
      'content' => [],
    ];

    if ($transition = $this->permittedTransition($project)) {
      $action = $transition->value;
      $build['content']['actions'][$action] = [
        'options' => $transition === ProjectTransition::Mediate ? $this->buildApplicantOptions($project) : NULL,
        'users' => $transition === ProjectTransition::Complete ? $this->buildParticipantUsers($project) : NULL,
        'button' => [
          '#type' => 'button',
          '#value' => $this->label($action),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'button--small', 'js-transition-btn'],
            'data-action' => $action,
          ],
        ],
        'description' => $this->description($action),
      ];
    }

    if ($decline = $this->buildMailActionLink($project, 'decline', $this->label('decline'))) {
      $build['content']['actions']['decline'] = [
        'button' => $decline,
        'description' => $this->description('decline'),
      ];
    }

    return empty($build['content']) ? [] : $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildView(ProjectInterface $project, Request $request): array {
    if ($request->query->get('view') !== 'decline') {
      return $this->build($project);
    }
    return $this->buildMailActionView($project, 'decline', $request);
  }

  /**
   * Gets the next transition, if the current user may post it.
   */
  protected function permittedTransition(ProjectInterface $project): ?ProjectTransition {
    $transition = $this->nextTransition($project);
    return $transition && $this->mayPost($project, $transition->value) ? $transition : NULL;
  }

  /**
   * Gets the next transition of the linear flow, without checking it.
   */
  protected function nextTransition(ProjectInterface $project): ?ProjectTransition {
    $lifecycle = $project->lifecycle();
    return match (TRUE) {
      $lifecycle->isDraft() => ProjectTransition::Submit,
      $lifecycle->isPending() => ProjectTransition::Publish,
      $lifecycle->isOpen() => ProjectTransition::Mediate,
      $lifecycle->isOngoing() => ProjectTransition::Complete,
      default => NULL,
    };
  }

  /**
   * Gets the button label of an action.
   */
  protected function label(string $action): TranslatableMarkup {
    return match ($action) {
      'submit' => $this->t('Submit'),
      'publish' => $this->t('Publish'),
      'mediate' => $this->t('Mediate'),
      'complete' => $this->t('Complete'),
      'decline' => $this->t('Decline'),
      default => throw new \InvalidArgumentException('Unknown action ' . $action . '.'),
    };
  }

  /**
   * Builds the description of an action: what happens when it is used.
   */
  protected function description(string $action): array {
    $items = match ($action) {
      'submit' => [
        $this->t('This action will submit the project for review.'),
        $this->t('If the organization is a prospect, it will be promoted to a proper organization.'),
        $this->t('The organization receives a notification.'),
      ],
      'publish' => [
        $this->t('This action will publish the project.'),
        $this->t('The project will appear in searches and be visible to creatives.'),
        $this->t('Creatives can apply to the project.'),
        $this->t('The organization receives a notification.'),
      ],
      'mediate' => [
        $this->t('This action will mediate the project.'),
        $this->t('The project will not appear in searches and will not be promoted anymore.'),
        $this->t('Each selected applicant receives an email from the organization.'),
        $this->t('The organization receives an email.'),
      ],
      'complete' => [
        $this->t('This action will complete the project.'),
        $this->t('The project will appear in searches.'),
        $this->t('Each selected participant receives an email from the organization.'),
        $this->t('The organization receives an email.'),
      ],
      'decline' => [
        $this->t('The project proposal is declined; a reset makes it a draft again.'),
        $this->t('The organization receives the edited email.'),
      ],
      default => throw new \InvalidArgumentException('Unknown action ' . $action . '.'),
    };
    return ['#theme' => 'item_list', '#items' => $items];
  }

  /**
   * Builds the applicants to select for the mediation.
   */
  protected function buildApplicantOptions(ProjectInterface $project): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    foreach ($project->getApplicants() as $applicant) {
      $options[$applicant->uuid()] = $view_builder->view($applicant, 'option');
    }
    return $options ?? [];
  }

  /**
   * Builds the participants that the completion mails.
   */
  protected function buildParticipantUsers(ProjectInterface $project): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    foreach ($project->getParticipants('Creative') as $participant) {
      $users[$participant->uuid()] = $view_builder->view($participant, 'option');
    }
    return $users ?? [];
  }

}
