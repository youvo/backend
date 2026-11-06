<?php

namespace Drupal\mailer_door\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the mailer_door module.
 */
class MailerDoorHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'mail_door' => [
        'render element' => 'form',
      ],
    ];
  }

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * The door keeps its button below the text: Gin would move it into the
   * sticky bar, which visitors without the admin toolbar never see.
   *
   * @return string[]
   *   The ids of the forms.
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return ['mailer_door_form'];
  }

  /**
   * Implements hook_preprocess_HOOK() for mail_door.
   *
   * Passes the texts of the door form to the template.
   */
  #[Hook('preprocess_mail_door')]
  public function preprocessMailDoor(array &$variables): void {
    $variables['heading'] = $variables['form']['#heading'];
    $variables['context'] = $variables['form']['#context'];
    $variables['note'] = $variables['form']['#note'];
  }

}
