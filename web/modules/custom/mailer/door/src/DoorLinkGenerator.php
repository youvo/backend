<?php

namespace Drupal\mailer_door;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;

/**
 * Makes door links without sending a mail.
 *
 * The links are real: each one gets a mail record like a sent mail, so the
 * door logs in and writes its follow log as usual. The URL itself is never
 * stored.
 */
class DoorLinkGenerator {

  /**
   * Constructs a DoorLinkGenerator object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected UuidInterface $uuid,
    protected TimeInterface $time,
    protected DoorLinks $doorLinks,
  ) {}

  /**
   * Records a mail without sending it and builds its door.
   *
   * The subject is the creative UUID for the applicant target.
   *
   * @return string
   *   The absolute URL of the door.
   */
  public function generate(ProjectInterface $project, UserInterface $recipient, DoorTarget $target, string $subject, AccountInterface $sender): string {

    $uuid = $this->uuid->generate();
    $now = $this->time->getRequestTime();
    $url = $this->doorLinks->build($recipient, $uuid, $target, $subject, $now);

    // The same values as a recorded mail, with the link redacted.
    $this->entityTypeManager->getStorage('correspondence')->create([
      'type' => 'mail',
      'uuid' => $uuid,
      'project' => $project->id(),
      'sender' => $sender->id(),
      'recipients' => json_encode([$recipient->getEmail()]),
      'subject' => Unicode::truncate('Link: ' . $target->heading(), 255, FALSE, TRUE),
      'body' => ['value' => '<p>Link ohne Mail erzeugt: [Link entfernt]</p>', 'format' => 'email_html'],
      'subtype' => 'mailer_door.' . $target->value,
      'origin' => 'manager',
      'result' => 'link',
      'occurred' => $now,
    ])->save();

    return $url;
  }

  /**
   * Gets the door links that make sense in the current project state.
   *
   * The owner is read from the field, because a project of a creative has no
   * owner for getOwner().
   *
   * @return array<int, array{recipient: \Drupal\user\UserInterface, target: \Drupal\mailer_door\DoorTarget, subject: string}>
   *   The suggestions.
   */
  public function suggestions(ProjectInterface $project): array {

    $owner = $this->entityTypeManager->getStorage('user')->load($project->getOwnerId());
    if (!$owner instanceof UserInterface) {
      return [];
    }

    $lifecycle = $project->lifecycle();
    $suggestions = [];
    if ($lifecycle->isDraft() || $lifecycle->isPending()) {
      $suggestions[] = ['recipient' => $owner, 'target' => DoorTarget::Draft, 'subject' => ''];
    }
    elseif ($lifecycle->isOpen() && $project->hasApplicant()) {
      $suggestions[] = ['recipient' => $owner, 'target' => DoorTarget::Applicants, 'subject' => ''];
      foreach ($project->getApplicants() as $applicant) {
        $suggestions[] = [
          'recipient' => $owner,
          'target' => DoorTarget::Applicant,
          'subject' => (string) $applicant->uuid(),
        ];
      }
    }
    elseif ($lifecycle->isOpen()) {
      $suggestions[] = ['recipient' => $owner, 'target' => DoorTarget::Edit, 'subject' => ''];
    }
    elseif ($lifecycle->isOngoing()) {
      $suggestions[] = ['recipient' => $owner, 'target' => DoorTarget::Complete, 'subject' => ''];
    }
    elseif ($lifecycle->isCompleted()) {
      $suggestions[] = ['recipient' => $owner, 'target' => DoorTarget::Feedback, 'subject' => ''];
      foreach ($project->getParticipants('Creative') as $participant) {
        $suggestions[] = ['recipient' => $participant, 'target' => DoorTarget::Feedback, 'subject' => ''];
      }
    }

    return $suggestions;
  }

  /**
   * Describes what a door made now does when nobody is signed in.
   *
   * Uses the verdict of the door, so the line cannot drift from the door.
   */
  public function outcome(ProjectInterface $project, UserInterface $recipient, DoorTarget $target, string $subject): string {

    $verdict = $this->doorLinks->verdict($recipient, $project, $target, $this->time->getRequestTime(), NULL);
    if ($verdict->rejected !== NULL) {
      return 'Die Tür antwortet mit 403: ' . $verdict->rejected . '.';
    }

    $name = $recipient->getDisplayName();
    $lines = [];
    if ($verdict->password) {
      $lines[] = 'Zeigt das Passwortformular.';
      $lines[] = 'Befördert die Organisation, setzt ihr Passwort, meldet sie an und schreibt einen Folge-Eintrag.';
    }
    elseif ($verdict->login) {
      $lines[] = 'Meldet an und schreibt einen Folge-Eintrag.';
      $lines[] = 'Ältere Türlinks dieses Kontos melden danach nicht mehr an.';
    }
    elseif (!$verdict->belongs) {
      $lines[] = 'Meldet nicht an und schreibt keinen Folge-Eintrag: ' . $name . ' gehört nicht zum Projekt.';
    }
    elseif ($verdict->spent) {
      $lines[] = 'Meldet nicht an (Entwurfslink verbraucht) und schreibt einen Folge-Eintrag.';
    }
    elseif ($target === DoorTarget::Feedback) {
      $lines[] = 'Meldet nicht an (Feedback liegt auf der Website) und schreibt einen Folge-Eintrag.';
    }
    elseif ($verdict->staff) {
      $lines[] = 'Meldet nicht an (' . $name . ' ist Team) und schreibt einen Folge-Eintrag.';
    }
    else {
      $lines[] = 'Meldet nicht an und schreibt einen Folge-Eintrag.';
    }

    // The door does not check the subject, so the line tells.
    if ($target === DoorTarget::Applicant) {
      $creatives = $this->entityTypeManager->getStorage('user')->loadByProperties(['uuid' => $subject]);
      $creative = reset($creatives);
      if ($creative instanceof Creative && !$project->isApplicant($creative)) {
        $lines[] = $creative->getDisplayName() . ' hat sich nicht beworben.';
      }
    }

    return implode(' ', $lines);
  }

}
