<?php

namespace Drupal\mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
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
   * Constructs the mail variables.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * Variables for a project, its organization and manager.
   */
  public function project(ProjectInterface $project): array {
    $organization = $project->getOwner();
    $manager = $organization instanceof Organization ? $organization->getManager() : NULL;
    $organization_name = $organization instanceof Organization ? $organization->getName() : '';
    $contact = $organization instanceof Organization ? $organization->getContact() : '';
    return [
      'project_title' => $project->getTitle(),
      // Used in greetings: "Hallo zusammen,".
      'contact' => $contact ?: (string) $this->t('zusammen'),
      'manager' => $manager?->getName() ?: (string) $this->t('Dein youvo-Team'),
      // Without a manager the users are pointed to the site mail address.
      'manager_email' => $manager?->getEmail() ?: (string) $this->configFactory->get('system.site')->get('mail'),
      'organization_name' => $organization_name,
      // Used to name the contact of the organization: the name of the
      // organization is the neutral choice.
      'organization_contact' => $contact ?: $organization_name,
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
      'creative_name' => $creative->getName() ?: (string) $this->t('Kreative'),
      'creative_email' => $creative->getEmail() ?? '',
      'creative_link' => 'https://hub.youvo.org/users/' . $creative->uuid(),
      // Filled by the mailer that applies the creative variables.
      'creative_phone' => '',
    ];
  }

}
