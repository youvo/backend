<?php

namespace Drupal\mailer\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\mailer\PreviewText;

/**
 * Hook implementations for the mailer module.
 */
class MailerHooks {

  /**
   * Implements hook_preprocess_HOOK() for email_wrap__mailer.
   *
   * Sets the inbox preview text. Trailing invisible characters keep the body
   * start from repeating after it in the preview.
   */
  #[Hook('preprocess_email_wrap__mailer')]
  public function preprocessEmailWrap(array &$variables): void {
    $text = $variables['is_html'] ? PreviewText::fromHtml((string) $variables['body']) : '';
    $variables['preview'] = $text === '' ? '' : Markup::create(Html::escape($text) . str_repeat('&nbsp;&zwnj;', 80));
  }

}
