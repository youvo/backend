<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project transition views context pane.
 */
#[ViewsContextPane(id: "transition")]
class ContextPaneTransition extends ContextPaneBase {

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

    return [
      '#theme' => 'context_pane',
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
      $users[$applicant->uuid()] = $view_builder->view($applicant, 'avatar');
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
