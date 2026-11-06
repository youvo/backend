<?php

namespace Drupal\creatives\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the registration of creatives.
 */
class CreativeRegisterHooks {

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * The registration keeps its button below the fields: Gin would move it
   * into the sticky bar, which visitors without the admin toolbar never see.
   *
   * @return string[]
   *   The ids of the forms.
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return ['creative_register_password_form'];
  }

}
