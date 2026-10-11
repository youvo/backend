<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\mailer\HubUrls;
use Drupal\mailer\MailVariables;
use Drupal\mailer\Manager\LateTokens;
use Drupal\mailer\Manager\ManagerMailPreview;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\mailer\Manager\PlainTextBody;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Psr\Log\LoggerInterface;

/**
 * Mails a manager sends from the project manager.
 */
#[MailerInfo(
  base_tag: "mailer.manager",
  label: new TranslatableMarkup("Manager mail"),
  sub_defs: [
    "reminder_draft" => new TranslatableMarkup("Reminder: submit the project"),
    "reminder_open" => new TranslatableMarkup("Reminder: review applications"),
    "reminder_open_noone" => new TranslatableMarkup("Reminder: project is online"),
    "reminder_ongoing" => new TranslatableMarkup("Reminder: complete the project"),
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
    'project_link' => new TranslatableMarkup("Link to the project in the hub"),
    'applicants_count' => new TranslatableMarkup("Number of applicants"),
  ],
)]
class ManagerMailer extends YouvoMailerBase implements ManagerMailerInterface {

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
    protected HubUrls $hubUrls,
    protected ManagerMailRegistry $registry,
    protected LateTokens $lateTokens,
    protected PlainTextBody $plainText,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag, $recorder, $logger, $configFactory);
  }

  /**
   * Gets the variables for a mail: the single source for preview and send.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param bool $preview
   *   TRUE keeps the link as a late token the manager can see.
   *
   * @return array<string, string>
   *   The variables.
   */
  protected function variables(ProjectInterface $project, bool $preview): array {
    return $this->mailVariables->project($project) + [
      'project_link' => $preview ? LateTokens::token('project_link') : $this->hubUrls->project($project),
      'applicants_count' => (string) count($project->getApplicants()),
    ];
  }

  /**
   * Creates the email with variables and the project parameter.
   */
  protected function projectEmail(string $subtype, ProjectInterface $project, bool $preview): EmailInterface {
    return $this->newEmail($subtype)
      ->setVariables($this->variables($project, $preview))
      ->setParam('project', $project);
  }

  /**
   * {@inheritdoc}
   */
  public function preview(ProjectInterface $project, string $subtype): ?ManagerMailPreview {
    $to = $this->ownerEmail($project);
    if ($to === NULL || $this->registry->find($project, $subtype) === NULL) {
      return NULL;
    }
    $captured = NULL;
    $email = $this->projectEmail($subtype, $project, TRUE);
    // Capture after the policy was rendered and URLs made absolute (700),
    // before the wrapper (800), then stop before the transport. The skip
    // exception ends the pipeline before the transport and the post-send
    // phase, and nothing is recorded because deliver() is not used.
    $email->addCallback(static function (EmailInterface $e) use (&$captured): void {
      $captured = [(string) $e->getSubject(), (string) $e->getHtmlBody()];
    }, EmailInterface::PHASE_POST_RENDER, 750, 'manager_preview_capture');
    $email->addCallback(static function (): void {
      throw new SkipMailException('Preview only.');
    }, EmailInterface::PHASE_POST_RENDER, 751, 'manager_preview_stop');
    try {
      $email->setTo($to)->send();
    }
    catch (\Throwable $e) {
      $this->logger->error('Manager mail preview failed for %subtype: @message', [
        '%subtype' => $subtype,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    return $captured === NULL ? NULL : new ManagerMailPreview($captured[0], $this->plainText->fromHtml($captured[1]), [$to]);
  }

  /**
   * {@inheritdoc}
   */
  public function send(ProjectInterface $project, string $subtype, string $subject, string $bodyText, AccountInterface $sender): bool {
    if ($this->registry->find($project, $subtype) === NULL) {
      $this->logger->warning('Manager mail %subtype is not offered for project @id.', [
        '%subtype' => $subtype,
        '@id' => $project->id(),
      ]);
      return FALSE;
    }
    $subject = trim(preg_replace('/\s+/', ' ', $subject) ?? '');
    $values = ['project_link' => $this->hubUrls->project($project)];
    $success = TRUE;
    foreach ([$this->ownerEmail($project)] as $to) {
      // Late tokens are resolved on the plain text, then everything is
      // escaped and converted to HTML: the manager cannot introduce markup.
      $body = $this->plainText->toHtml($this->lateTokens->replace($bodyText, $values));
      $email = $this->projectEmail($subtype, $project, FALSE);
      // BUILD phase, after ReplacementEmailProcessor (900): the edited text
      // replaces the rendered policy text and is never parsed as Twig. Added
      // on the email, so it applies to this email only.
      $email->addCallback(static function (EmailInterface $e) use ($subject, $body): void {
        $e->setSubject($subject);
        $e->setBody(['#type' => 'processed_text', '#text' => $body, '#format' => 'email_html']);
      }, EmailInterface::PHASE_BUILD, 950, 'manager_override');
      $success = $this->deliver($email, $project, $subtype, $to, [], 'manager', (int) $sender->id()) && $success;
    }
    return $success;
  }

}
