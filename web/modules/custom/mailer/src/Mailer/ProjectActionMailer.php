<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\CorrespondenceRecorder;
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
    $email = $this->projectEmail('apply_organization', $project, array_replace($base, $creative, [
      'message' => $message,
      'creative_phone' => $phone,
    ]));
    $success = $this->deliver($email, $project, 'apply_organization', $this->ownerEmail($project));

    $email = $this->projectEmail('apply_creative', $project, $base + $creative);
    return $this->deliver($email, $project, 'apply_creative', $applicant->getEmail()) && $success;
  }

  /**
   * {@inheritdoc}
   */
  public function notify(ProjectInterface $project, string $link): bool {
    return $this->sendWithLink($project, 'notify_organization', $link);
  }

  /**
   * {@inheritdoc}
   */
  public function invite(ProjectInterface $project, string $link): bool {
    return $this->sendWithLink($project, 'invite_organization', $link);
  }

  /**
   * Sends a mail to the organization that carries a link.
   */
  protected function sendWithLink(ProjectInterface $project, string $subtype, string $link): bool {
    $email = $this->projectEmail($subtype, $project, $this->mailVariables->project($project) + ['link' => $link]);
    // The link can be a one-time login link: it is never stored.
    return $this->deliver($email, $project, $subtype, $this->ownerEmail($project), [$link]);
  }

}
