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
   * Implements hook_preprocess_HOOK() for email_wrap.
   *
   * Provides the inbox preview text of HTML mails. The trailing invisible
   * characters keep the start of the body out of the preview, which would
   * repeat the text.
   */
  #[Hook('preprocess_email_wrap')]
  public function preprocessEmailWrap(array &$variables): void {
    $text = $variables['is_html'] ? PreviewText::fromHtml((string) $variables['body']) : '';
    $variables['preview'] = $text === '' ? '' : Markup::create(Html::escape($text) . str_repeat('&nbsp;&zwnj;', 80));
  }

}
