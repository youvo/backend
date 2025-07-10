<?php

namespace Drupal\manager\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;

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
      'context_pane__logbook' => [
        'base hook' => 'context_pane',
      ],
      'context_pane__promote' => [
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

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() === 'project_manager') {
      $view->element['#attached']['library'][] = 'manager/core';
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_context_pane_alter')]
  public function themeSuggestionsContextPaneAlter(array &$suggestions, array $variables): void {
    if (!empty($variables['elements']['#type'])) {
      $suggestions[] = 'context_pane__' . $variables['elements']['#type'];
    }
  }

}
