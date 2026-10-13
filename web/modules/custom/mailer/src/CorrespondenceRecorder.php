<?php

namespace Drupal\mailer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\projects\ProjectInterface;
use Psr\Log\LoggerInterface;

/**
 * Records sent mails as correspondence entities.
 */
class CorrespondenceRecorder {

  /**
   * Constructs a CorrespondenceRecorder object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected TimeInterface $time,
  ) {}

  /**
   * Records a mail. Never throws; failures are logged.
   *
   * Mails without a project are not recorded, because correspondence is a
   * child entity of a project.
   */
  public function recordMail(?ProjectInterface $project, string $subtype, array $recipients, string $subject, string $body, bool $success, string $origin = 'automatic', ?int $sender = NULL): ?CorrespondenceInterface {
    if (!$project instanceof ProjectInterface) {
      return NULL;
    }
    try {
      /** @var \Drupal\correspondence\CorrespondenceInterface $entry */
      $entry = $this->entityTypeManager->getStorage('correspondence')->create([
        'type' => 'mail',
        'project' => $project->id(),
        'sender' => $sender,
        'recipients' => json_encode(array_values($recipients)),
        'subject' => Unicode::truncate($subject, 255, FALSE, TRUE),
        'body' => ['value' => $body, 'format' => 'email_html'],
        'subtype' => $subtype,
        'origin' => $origin,
        'result' => $success ? 'sent' : 'failed',
        'occurred' => $this->time->getRequestTime(),
      ]);
      $entry->save();
      return $entry;
    }
    catch (\Throwable $e) {
      $this->logger->error('Unable to record correspondence for %subtype: @message', [
        '%subtype' => $subtype,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
