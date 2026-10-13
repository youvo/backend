<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\mailer\MailVariables;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
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
    "submit_manager" => new TranslatableMarkup("Submitted (manager)"),
    "submit_organization" => new TranslatableMarkup("Submitted (organization)"),
    "publish_organization" => new TranslatableMarkup("Published (organization)"),
    "mediate_organization" => new TranslatableMarkup("Mediated (organization)"),
    "mediate_creative" => new TranslatableMarkup("Mediated (creative)"),
    "mediate_staff" => new TranslatableMarkup("Mediated (staff)"),
    "complete_organization" => new TranslatableMarkup("Completed (organization)"),
    "complete_creative" => new TranslatableMarkup("Completed (creative)"),
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
    'participants' => new TranslatableMarkup("Participants"),
    'selected_creatives' => new TranslatableMarkup("Selected creatives"),
    'feedback_link' => new TranslatableMarkup("Feedback link"),
    'results_link' => new TranslatableMarkup("Results link"),
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
    ConfigFactoryInterface $configFactory,
    protected MailVariables $mailVariables,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag, $recorder, $logger, $configFactory);
  }

  /**
   * Creates an email with the project as parameter and the variables set.
   *
   * The project parameter makes the base class set the manager as reply-to.
   * Without it the reply-to is the site mail address.
   */
  protected function projectEmail(string $subtype, ProjectInterface $project, array $variables, bool $replyTo = TRUE): EmailInterface {
    $email = $this->newEmail($subtype)->setVariables($variables);
    return $replyTo ? $email->setParam('project', $project) : $email;
  }

  /**
   * Joins the names of accounts to a comma-separated list.
   */
  protected function names(array $accounts): string {
    return implode(', ', array_map(static fn ($account) => $account->getName(), $accounts));
  }

  /**
   * {@inheritdoc}
   */
  public function submit(ProjectInterface $project): bool {
    $variables = $this->mailVariables->project($project);
    $organization = $project->getOwner();
    $manager = $organization instanceof Organization ? $organization->getManager() : NULL;
    $success = TRUE;

    // The manager is the recipient: replies go to the site mail address.
    if ($manager instanceof Creative) {
      $email = $this->projectEmail('submit_manager', $project, $variables, FALSE);
      $success = $this->deliver($email, $project, 'submit_manager', $manager->getEmail());
    }

    $email = $this->projectEmail('submit_organization', $project, $variables);
    return $this->deliver($email, $project, 'submit_organization', $this->ownerEmail($project)) && $success;
  }

  /**
   * {@inheritdoc}
   */
  public function publish(ProjectInterface $project): bool {
    $email = $this->projectEmail('publish_organization', $project, $this->mailVariables->project($project));
    return $this->deliver($email, $project, 'publish_organization', $this->ownerEmail($project));
  }

  /**
   * {@inheritdoc}
   */
  public function mediate(ProjectInterface $project, array $creatives): bool {
    $base = $this->mailVariables->project($project) + [
      'selected_creatives' => $this->names($creatives),
    ];

    $email = $this->projectEmail('mediate_organization', $project, $base);
    $success = $this->deliver($email, $project, 'mediate_organization', $this->ownerEmail($project));

    foreach ($creatives as $creative) {
      if (!$creative instanceof Creative) {
        continue;
      }
      $email = $this->projectEmail('mediate_creative', $project, $this->mailVariables->creative($creative) + $base);
      $success = $this->deliver($email, $project, 'mediate_creative', $creative->getEmail()) && $success;
    }

    // Notify the staff at the site mail address (replies go there as well).
    $staff = (string) $this->configFactory->get('system.site')->get('mail');
    $email = $this->projectEmail('mediate_staff', $project, $base, FALSE);
    return $this->deliver($email, $project, 'mediate_staff', $staff) && $success;
  }

  /**
   * {@inheritdoc}
   */
  public function complete(ProjectInterface $project): bool {
    $base = $this->mailVariables->project($project);
    $participants = $project->getParticipants('Creative');

    $email = $this->projectEmail('complete_organization', $project, $base + [
      'participants' => $this->names($participants),
      'feedback_link' => 'https://youvo.org/feedback/organization/' . $project->uuid(),
    ]);
    $success = $this->deliver($email, $project, 'complete_organization', $this->ownerEmail($project));

    foreach ($participants as $participant) {
      if (!$participant instanceof Creative) {
        continue;
      }
      $email = $this->projectEmail('complete_creative', $project, $this->mailVariables->creative($participant) + $base + [
        'feedback_link' => 'https://youvo.org/feedback/creative/' . $project->uuid(),
        'results_link' => 'https://youvo.org/results/' . $project->uuid(),
      ]);
      $success = $this->deliver($email, $project, 'complete_creative', $participant->getEmail()) && $success;
    }
    return $success;
  }

}
