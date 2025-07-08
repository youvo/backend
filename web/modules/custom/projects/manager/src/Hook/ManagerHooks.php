<?php

namespace Drupal\manager\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the manager module.
 */
class ManagerHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'context_pane' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessContextPane',
      ],
      'context_pane__lifecycle' => [
        'base hook' => 'context_pane',
      ],
    ];
  }

  /**
   * Prepares variables for context pane templates.
   */
  public function preprocessContextPane(array &$variables): void {
    $variables['attributes']['id'] = uniqid('context-pane--', FALSE);
    $variables['type'] = $variables['elements']['#type'] ?? NULL;
    $variables['project'] = $variables['elements']['#project'] ?? NULL;
    $variables['content'] = $variables['elements']['content'] ?? [];
  }

}
