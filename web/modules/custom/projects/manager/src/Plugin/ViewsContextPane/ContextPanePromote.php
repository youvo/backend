<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;

/**
 * Provides a project promote views context pane.
 */
#[ViewsContextPane(id: "promote")]
class ContextPanePromote extends ContextPaneBase {

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
