<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;

/**
 * Provides a project lifecycle views context pane.
 */
#[ViewsContextPane(
  id: "lifecycle",
  label: "Lifecycle Context Pane"
)]
class LifecycleContextPane extends ContextPanePluginBase implements ContextPanePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    $states = '';
    foreach ($project->lifecycle()->history() as $state) {
      $states .= $state->to . ' ' . $state->timestamp;
    }

    return [
      '#theme' => 'context_pane',
      'content' => [
        'states' => $states,
      ],
    ];
  }

}
