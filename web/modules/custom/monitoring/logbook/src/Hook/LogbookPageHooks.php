<?php

namespace Drupal\logbook\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Page hook implementations of the logbook module.
 */
class LogbookPageHooks {

  public function __construct(protected RouteMatchInterface $routeMatch) {}

  /**
   * Implements hook_preprocess_page_title().
   *
   * The logbook starts with its search: the title is read out, but not shown,
   * which leaves room for the logs on small screens.
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(array &$variables): void {
    if ($this->routeMatch->getRouteName() === 'view.logbook.page_1') {
      $variables['title_attributes']['class'][] = 'visually-hidden';
    }
  }

}
