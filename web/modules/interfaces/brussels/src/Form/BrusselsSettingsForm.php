<?php

namespace Drupal\brussels\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * The brussels settings form.
 *
 * @internal
 */
class BrusselsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'brussels_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['brussels.settings'];
  }

}
