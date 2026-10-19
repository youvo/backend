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
   * Provides the inbox preview text of HTML mails of the mailer module, whose
   * layout (email-wrap--mailer.html.twig) prints it. The trailing invisible
   * characters keep the start of the body out of the preview, which would
   * repeat the text.
   */
  #[Hook('preprocess_email_wrap__mailer')]
  public function preprocessEmailWrap(array &$variables): void {
    $text = $variables['is_html'] ? PreviewText::fromHtml((string) $variables['body']) : '';
    $variables['preview'] = $text === '' ? '' : Markup::create(Html::escape($text) . str_repeat('&nbsp;&zwnj;', 80));
  }

}
