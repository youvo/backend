<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\mailer\MailVariables;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Psr\Log\LoggerInterface;

/**
 * Mails sent on project transitions.
 */
#[MailerInfo(
  base_tag: "mailer.project_transition",
  label: new TranslatableMarkup("Project transition"),
  sub_defs: [
    "publish_organization" => new TranslatableMarkup("Published (organization)"),
  ],
  required_config: ["email_subject", "email_body"],
  variables: [
    'project_title' => new TranslatableMarkup("Project title"),
    'contact' => new TranslatableMarkup("Organization contact"),
    'manager' => new TranslatableMarkup("Manager name"),
    'manager_email' => new TranslatableMarkup("Manager email"),
    'organization_name' => new TranslatableMarkup("Organization name"),
    'organization_contact' => new TranslatableMarkup("Organization contact"),
    'organization_address' => new TranslatableMarkup("Organization address"),
    'organization_email' => new TranslatableMarkup("Organization email"),
    'organization_phone' => new TranslatableMarkup("Organization phone"),
  ],
)]
class ProjectTransitionMailer extends YouvoMailerBase implements ProjectTransitionMailerInterface {

  /**
   * Constructs the mailer.
   */
  public function __construct(
    MailerPlusInterface $mailer,
    MailerLookupInterface $mailerLookup,
    string $baseTag,
    CorrespondenceRecorder $recorder,
    LoggerInterface $logger,
    protected MailVariables $mailVariables,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag, $recorder, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function publish(ProjectInterface $project): bool {
    $email = $this->newEmail('publish_organization')
      ->setParam('project', $project)
      ->setVariables($this->mailVariables->project($project));
    return $this->deliver($email, $project, 'publish_organization', $project->getOwner()->getEmail());
  }

}
