<?php

namespace Drupal\manager\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer\Manager\ManagerMailRegistry;

/**
 * The editor of the archive mail, posted to the project:archive resource.
 */
class ProjectArchiveForm extends ProjectActionMailForm {

  protected const string ACTION = 'archive';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitLabel(): TranslatableMarkup {
    return $this->t('Archive and send');
  }

  /**
   * {@inheritdoc}
   */
  protected function acceptsSubtype(string $subtype): bool {
    return ManagerMailRegistry::archiveReason($subtype) !== NULL;
  }

}
