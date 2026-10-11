<?php

namespace Drupal\correspondence\Hook;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\correspondence\CorrespondenceBodyFilter;
use Drupal\correspondence\CorrespondenceInterface;

/**
 * Hook implementations for the correspondence module.
 */
class CorrespondenceHooks {

  use StringTranslationTrait;

  public function __construct(
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'correspondence' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCorrespondence',
      ],
    ];
  }

  /**
   * Prepares variables for correspondence templates.
   */
  public function preprocessCorrespondence(array &$variables): void {
    $entry = $variables['elements']['#correspondence'];
    assert($entry instanceof CorrespondenceInterface);
    $time = $entry->getOccurredTime();
    $is_note = $entry->isNote();

    $variables['entry'] = $entry;
    $variables['type'] = $is_note ? 'note' : 'mail';
    $variables['label'] = $is_note ? $this->t('Notiz') : $this->t('Mail');
    $variables['date'] = $this->dateFormatter->format($time, 'html_date');
    $variables['info'] = $this->dateFormatter->format($time, 'short');
    $variables['subject'] = (string) $entry->get('subject')->value;
    $variables['failed'] = !$is_note && !$entry->isSent();

    $body = (string) $entry->get('body')->value;
    if ($is_note) {
      // Notes are plain text: Twig escapes the value and applies nl2br.
      $variables['body_text'] = $body;
      $variables['body_html'] = '';
    }
    else {
      $variables['body_text'] = '';
      // The only place where stored HTML is marked as safe markup: the body is
      // filtered against a strict tag allowlist (no scripts, styles, event
      // handlers or unsafe protocols) right before.
      $variables['body_html'] = Markup::create(Xss::filter($body, CorrespondenceBodyFilter::ALLOWED_TAGS));
    }
  }

}
