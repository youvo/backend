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
      'project_result' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessProjectResult',
      ],
      'project_comment' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessProjectComment',
      ],
      'project_result_item' => [
        'variables' => [
          'url' => NULL,
          'label' => NULL,
          'name' => NULL,
          'preview' => NULL,
          'kind' => NULL,
          'lightbox' => NULL,
          'external' => FALSE,
        ],
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
    $this->preprocessEntity($variables, 'project');
  }

  /**
   * Prepares variables for project result templates.
   */
  public function preprocessProjectResult(array &$variables): void {
    $this->preprocessEntity($variables, 'project_result');
  }

  /**
   * Prepares variables for project comment templates.
   */
  public function preprocessProjectComment(array &$variables): void {
    $this->preprocessEntity($variables, 'project_comment');
  }

  /**
   * Puts the entity and its fields into the variables.
   */
  protected function preprocessEntity(array &$variables, string $entity_type_id): void {
    $variables[$entity_type_id] = $variables['elements']['#' . $entity_type_id];
    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

}
