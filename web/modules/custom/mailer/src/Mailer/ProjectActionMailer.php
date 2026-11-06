<?php

namespace Drupal\mailer\Mailer;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\mailer\Door\DoorLinks;
use Drupal\mailer\Door\DoorTarget;
use Drupal\mailer\MailVariables;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Psr\Log\LoggerInterface;

/**
 * Mails sent on actions of users on a project.
 */
#[MailerInfo(
  base_tag: "mailer.project_action",
  label: new TranslatableMarkup("Project action"),
  sub_defs: [
    "apply_organization" => new TranslatableMarkup("Application (organization)"),
    "apply_creative" => new TranslatableMarkup("Application (creative)"),
    "notify_organization" => new TranslatableMarkup("Project created (organization)"),
    "invite_organization" => new TranslatableMarkup("Invitation (organization)"),
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
    'creative_name' => new TranslatableMarkup("Creative name"),
    'creative_email' => new TranslatableMarkup("Creative email"),
    'creative_link' => new TranslatableMarkup("Creative profile link"),
    'creative_phone' => new TranslatableMarkup("Creative phone"),
    'message' => new TranslatableMarkup("Message of the applicant"),
    'link' => new TranslatableMarkup("Link"),
  ],
)]
class ProjectActionMailer extends YouvoMailerBase implements ProjectActionMailerInterface {

  /**
   * Constructs the mailer.
   */
  public function __construct(
    MailerPlusInterface $mailer,
    MailerLookupInterface $mailerLookup,
    string $baseTag,
    CorrespondenceRecorder $recorder,
    LoggerInterface $logger,
    ConfigFactoryInterface $configFactory,
    protected MailVariables $mailVariables,
    protected DoorLinks $doorLinks,
    protected UuidInterface $uuid,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag, $recorder, $logger, $configFactory);
  }

  /**
   * Creates an email with the project as parameter and the variables set.
   *
   * The project parameter makes the base class set the manager as reply-to.
   */
  protected function projectEmail(string $subtype, ProjectInterface $project, array $variables): EmailInterface {
    return $this->newEmail($subtype)->setVariables($variables)->setParam('project', $project);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(ProjectInterface $project, Creative $applicant, string $message, string $phone): bool {
    $base = $this->mailVariables->project($project);
    $creative = $this->mailVariables->creative($applicant);

    // The message is user input and rendered escaped by the policy.
    // The phone number replaces the empty default of the creative variables.
    // The organization gets a door to the applicant, which is never stored.
    $to = $this->ownerEmail($project);
    $uuid = $this->uuid->generate();
    $door = $to === NULL ? '' : $this->doorLinks->build($project->getOwner(), $uuid, DoorTarget::Applicant, (string) $applicant->uuid());
    $email = $this->projectEmail('apply_organization', $project, array_replace($base, $creative, [
      'message' => $message,
      'creative_phone' => $phone,
      'creative_link' => $door,
    ]));
    $success = $this->deliver($email, $project, 'apply_organization', $to, [$door], 'automatic', NULL, $uuid);

    $email = $this->projectEmail('apply_creative', $project, $base + $creative);
    return $this->deliver($email, $project, 'apply_creative', $applicant->getEmail()) && $success;
  }

  /**
   * {@inheritdoc}
   */
  public function notify(ProjectInterface $project): bool {
    return $this->sendWithDraftDoor($project, 'notify_organization');
  }

  /**
   * {@inheritdoc}
   */
  public function invite(ProjectInterface $project): bool {
    return $this->sendWithDraftDoor($project, 'invite_organization');
  }

  /**
   * Sends a mail to the organization that carries a door to the draft.
   *
   * A prospect sets its password on the door.
   */
  protected function sendWithDraftDoor(ProjectInterface $project, string $subtype): bool {
    $to = $this->ownerEmail($project);
    $uuid = $this->uuid->generate();
    $door = $to === NULL ? '' : $this->doorLinks->build($project->getOwner(), $uuid, DoorTarget::Draft);
    $email = $this->projectEmail($subtype, $project, $this->mailVariables->project($project) + ['link' => $door]);
    // The door can log the organization in: it is never stored.
    return $this->deliver($email, $project, $subtype, $to, [$door], 'automatic', NULL, $uuid);
  }

}
