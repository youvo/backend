<?php

namespace Drupal\manager\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer\Manager\ManagerMailRegistry;

/**
 * The editor of the rejection mail, posted to the project:decline resource.
 */
class ProjectDeclineForm extends ProjectActionMailForm {

  protected const string ACTION = 'decline';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_decline_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel(): TranslatableMarkup {
    return $this->t('Decline and send');
  }

  /**
   * {@inheritdoc}
   */
  protected function acceptsSubtype(string $subtype): bool {
    return ManagerMailRegistry::declineReason($subtype) !== NULL;
  }

}
