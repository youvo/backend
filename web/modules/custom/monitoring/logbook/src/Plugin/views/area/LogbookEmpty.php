<?php

namespace Drupal\logbook\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\logbook\SearchTags;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Says why the logbook is empty, and offers to reset a search without results.
 */
#[ViewsArea('logbook_empty')]
class LogbookEmpty extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if (!$empty && empty($this->options['empty'])) {
      return [];
    }
    if (!$this->isSearching()) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['logbook-empty']],
        'title' => [
          '#markup' => 'Noch keine Einträge.',
          '#prefix' => '<p class="logbook-empty__title">',
          '#suffix' => '</p>',
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['logbook-empty']],
      'title' => [
        '#markup' => 'Keine Einträge für diese Suche.',
        '#prefix' => '<p class="logbook-empty__title">',
        '#suffix' => '</p>',
      ],
      'hint' => [
        '#markup' => 'Entferne einen Suchbegriff oder Filter, oder setze die Suche zurück.',
        '#prefix' => '<p class="logbook-empty__hint">',
        '#suffix' => '</p>',
      ],
      'reset' => [
        '#type' => 'link',
        '#title' => 'Suche zurücksetzen',
        '#url' => Url::fromUserInput('/' . $this->view->getPath()),
        '#attributes' => ['class' => ['button', 'button--small']],
      ],
    ];
  }

  /**
   * Whether the list is limited by the search bar or a filter.
   */
  protected function isSearching(): bool {
    $input = $this->view->getExposedInput();
    $created = $input['created'] ?? [];
    return SearchTags::fromInput($input['tags'] ?? NULL) !== []
      || !in_array($input['type'] ?? '', ['', 'All'], TRUE)
      || (is_array($created) && array_filter($created) !== []);
  }

}
