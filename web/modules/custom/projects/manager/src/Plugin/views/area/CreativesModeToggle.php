<?php

namespace Drupal\manager\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Links the table and the profiles mode of the creatives finder.
 *
 * Both links keep the filters and the sort of the current request. They drop
 * the page, because the modes show a different number of creatives per page.
 */
#[ViewsArea('creatives_mode_toggle')]
class CreativesModeToggle extends AreaPluginBase {

  /**
   * Renders the links to the two modes.
   *
   * @param bool $empty
   *   Whether the view has no results.
   *
   * @return array
   *   The render array of the links.
   */
  public function render($empty = FALSE): array {

    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $query = $this->view->getRequest()->query->all();
    unset($query['page'], $query['mode']);
    $profiles = $this->view->getRequest()->query->get('mode') === 'profiles';

    $links = [];
    foreach ([
      'table' => [$this->t('Table'), $query, !$profiles],
      'profiles' => [$this->t('Profiles'), ['mode' => 'profiles'] + $query, $profiles],
    ] as $key => [$title, $link_query, $active]) {
      $links[$key] = [
        'title' => $title,
        'url' => Url::fromRoute('<current>', [], ['query' => $link_query]),
        'attributes' => $active ? ['aria-current' => 'page', 'class' => ['is-active']] : [],
      ];
    }

    return [
      '#theme' => 'links',
      '#links' => $links,
      '#attributes' => ['class' => ['creatives-mode-toggle']],
      '#cache' => ['contexts' => ['url.query_args']],
    ];
  }

}
