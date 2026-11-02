<?php

namespace Drupal\logbook\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Theme hook implementations of the logbook module.
 */
class LogbookThemeHooks {

  public function __construct(
    protected DateFormatterInterface $dateFormatter,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'log' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessLog',
      ],
    ];
  }

  /**
   * Prepares variables for log templates.
   */
  public function preprocessLog(array &$variables): void {

    /** @var \Drupal\logbook\LogInterface $log */
    $log = $variables['elements']['#log'];

    $variables['log'] = $log;
    $variables['color'] = $log->getColor();
    $variables['created'] = $this->dateFormatter->format($log->getCreatedTime(), 'html_date');
    $variables['info'] = $this->dateFormatter->format($log->getCreatedTime(), 'short') . ' by ' . $log->getOwner()->getDisplayName();
    $variables['show_hidden'] = $this->requestStack->getCurrentRequest()->query->get('hidden');
    $variables['label'] = str_replace('Project ', '', $log->getPattern()->label());
    $variables['hidden'] = $log->getPattern()->isHidden();

    $variables['text']['#markup'] = $log->getMarkup();

    $variables['message'] = $log->buildMessage();
  }

}
