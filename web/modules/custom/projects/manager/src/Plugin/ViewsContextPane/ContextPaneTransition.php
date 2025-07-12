<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
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
   * The form builder.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->formBuilder = $container->get('form_builder');
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

    $button = [
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
    ];

    if ($transition === ProjectTransition::Mediate) {

      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      foreach ($project->getApplicants() as $applicant) {
        $options[$applicant->uuid()] = $view_builder->view($applicant, 'option');
      }

      // @todo Check the workflow and adjust description.
      $description = [
        '#markup' => '<ul>' .
        '    <li>' . $this->t('This action will @transition the project.', ['@transition' => lcfirst($transition->name)]) . '</li>' .
        '    <li>' . $this->t('The project will not appear in searches and will not be promoted anymore.') . '</li>' .
        '    <li>' . $this->t('Each selected applicant receives an email from the organization.') . '</li>' .
        '    <li>' . $this->t('The organization receives an email.') . '</li>' .
        '  </ul>',
      ];
    }

    return [
      '#theme' => 'context_pane',
      'content' => [
        'options' => $options ?? NULL,
        'button' => $button,
        'description' => $description ?? NULL,
      ],
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
