<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\projects\Entity\Project;

/**
 * Provides a project edit manager context pane.
 */
#[ManagerContextPane(id: "edit")]
class ManagerContextPaneEdit extends ManagerContextPaneBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    // Promote and demote are not available for declined projects.
    if ($project->lifecycle()->isDeclined()) {
      return [
        '#theme' => 'context_pane',
        '#type' => 'edit',
        '#project' => $project,
        'content' => [],
      ];
    }

    $is_promoted = $project->isPromoted();

    $button = [
      '#type' => 'button',
      '#value' => $is_promoted ? $this->t('Demote') : $this->t('Promote'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
          'button--small',
          'js-promote-btn',
        ],
        'data-action' => $is_promoted ? 'demote' : 'promote',
      ],
    ];

    return [
      '#theme' => 'context_pane',
      '#type' => 'edit',
      '#project' => $project,
      'content' => [
        'button' => $button,
        'description' => $is_promoted ? $this->buildDemoteDescription() : $this->buildPromoteDescription(),
      ],
    ];
  }

  /**
   * Builds the promote description.
   */
  protected function buildPromoteDescription(): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will promote the project.') . '</li>' .
      '    <li>' . $this->t('The project is promoted to the front page.') . '</li>' .
      '    <li>' . $this->t('Nobody receives an email.') . '</li>' .
      '  </ul>',
    ];
  }

  /**
   * Builds the demote description.
   */
  protected function buildDemoteDescription(): array {
    return [
      '#markup' => '<ul>' .
      '    <li>' . $this->t('This action will demote the project.') . '</li>' .
      '    <li>' . $this->t('The project is no longer promoted to the front page.') . '</li>' .
      '    <li>' . $this->t('Nobody receives an email.') . '</li>' .
      '  </ul>',
    ];
  }

}
