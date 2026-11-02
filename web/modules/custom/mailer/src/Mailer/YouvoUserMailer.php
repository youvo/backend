<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\symfony_mailer\Attribute\MailerInfo;

/**
 * Mails sent on the registration of users.
 */
#[MailerInfo(
  base_tag: "mailer.user",
  label: new TranslatableMarkup("User"),
  sub_defs: [
    "register_creative" => new TranslatableMarkup("Registration (creative)"),
    "register_organization" => new TranslatableMarkup("Registration (organization)"),
    "register_organization_staff" => new TranslatableMarkup("Registration (organization, staff)"),
  ],
  required_config: ["email_subject", "email_body"],
  variables: [
    'name' => new TranslatableMarkup("Creative name"),
    'link' => new TranslatableMarkup("Link"),
    'organization_name' => new TranslatableMarkup("Organization name"),
    'organization_contact' => new TranslatableMarkup("Organization contact"),
    'organization_email' => new TranslatableMarkup("Organization email"),
  ],
)]
class YouvoUserMailer extends YouvoMailerBase implements YouvoUserMailerInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function registerCreative(Creative $creative, string $link): bool {
    $email = $this->newEmail('register_creative')->setVariables([
      'name' => $creative->getName() ?: (string) $this->t('Kreative'),
      'link' => $link,
    ]);
    // The one-time link is never stored. Without a project the mail is not
    // recorded anyway; the secret keeps that true if this ever changes.
    return $this->deliver($email, NULL, 'register_creative', $creative->getEmail(), [$link]);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyOrganizationCreated(Organization $organization): bool {
    $name = $organization->getName();
    $email = $this->newEmail('register_organization_staff')->setVariables([
      'organization_name' => $name,
      // The name of the organization is the neutral choice for the contact.
      'organization_contact' => $organization->getContact() ?: $name,
      'organization_email' => $organization->getEmail() ?? '',
    ]);
    $to = (string) $this->configFactory->get('system.site')->get('mail');
    return $this->deliver($email, NULL, 'register_organization_staff', $to);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmOrganizationProposal(Organization $organization): bool {
    // The address is not verified: the mail carries no text of the proposal,
    // so nobody can send their own words to someone else through youvo.org.
    $email = $this->newEmail('register_organization');
    return $this->deliver($email, NULL, 'register_organization', $organization->getEmail());
  }

}
