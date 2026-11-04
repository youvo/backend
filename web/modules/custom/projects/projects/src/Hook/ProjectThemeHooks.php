<?php

namespace Drupal\projects\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations of the projects module.
 */
class ProjectThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'project' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessProject',
      ],
    ];
  }

  /**
   * Prepares variables for project templates.
   *
   * The fields go into "content", so the template does not print the
   * element itself, which would render the project inside itself.
   */
  public function preprocessProject(array &$variables): void {
    $variables['project'] = $variables['elements']['#project'];
    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

}
