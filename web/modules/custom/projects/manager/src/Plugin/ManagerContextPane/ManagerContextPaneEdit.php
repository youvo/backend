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
      'content' => [
        'button' => $button,
      ],
    ];
  }

}
