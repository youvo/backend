<?php

namespace Drupal\mailer;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;

/**
 * Provides template variables for transactional mails.
 */
class MailVariables {

  use StringTranslationTrait;

  /**
   * Variables for a project, its organization and manager.
   */
  public function project(ProjectInterface $project): array {
    $organization = $project->getOwner();
    $manager = $organization instanceof Organization ? $organization->getManager() : NULL;
    return [
      'project_title' => $project->getTitle(),
      'contact' => $organization instanceof Organization ? $organization->getContact() : '',
      'manager' => $manager?->getName() ?? (string) $this->t('Dein youvo-Team'),
      'manager_email' => $manager?->getEmail() ?? '',
      'organization_name' => $organization instanceof Organization ? $organization->getName() : '',
      'organization_contact' => $organization instanceof Organization ? $organization->getContact() : '',
      'organization_address' => $organization instanceof Organization ? $organization->getAddress() : '',
      'organization_email' => $organization->getEmail() ?? '',
      'organization_phone' => $organization instanceof Organization ? $organization->getPhoneNumber() : '',
    ];
  }

  /**
   * Variables for a creative.
   */
  public function creative(Creative $creative): array {
    return [
      'creative_name' => $creative->getName(),
      'creative_email' => $creative->getEmail() ?? '',
      'creative_link' => 'https://hub.youvo.org/users/' . $creative->uuid(),
      // Filled by the mailer that applies the creative variables.
      'creative_phone' => '',
    ];
  }

}
