<?php

namespace Drupal\mailer_door;

/**
 * The places a mail door leads to.
 */
enum DoorTarget: string {

  case Draft = 'draft';
  case Applicants = 'applicants';
  case Edit = 'edit';
  case Complete = 'complete';
  case Applicant = 'applicant';
  case Feedback = 'feedback';

  /**
   * Gets the code of the target in a door token.
   *
   * Fixed per target, so that reordering the cases keeps issued doors.
   */
  public function code(): int {
    return match ($this) {
      DoorTarget::Draft => 1,
      DoorTarget::Applicants => 2,
      DoorTarget::Edit => 3,
      DoorTarget::Complete => 4,
      DoorTarget::Applicant => 5,
      DoorTarget::Feedback => 6,
    };
  }

  /**
   * Gets the target of a code in a door token, or NULL for an unknown code.
   */
  public static function fromCode(int $code): ?self {
    foreach (static::cases() as $target) {
      if ($target->code() === $code) {
        return $target;
      }
    }
    return NULL;
  }

  /**
   * Gets the heading of the door page.
   */
  public function heading(): string {
    return match ($this) {
      DoorTarget::Draft => 'Euer Projektentwurf wartet',
      DoorTarget::Applicants => 'Neue Bewerbungen für euer Projekt',
      DoorTarget::Edit => 'Noch keine Bewerbungen',
      DoorTarget::Complete => 'Ist euer Projekt fertig?',
      DoorTarget::Applicant => 'Neue Bewerbung für euer Projekt',
      DoorTarget::Feedback => 'Wie war euer Projekt?',
    };
  }

  /**
   * Whether pressing the door button may log the recipient in.
   *
   * Feedback lives on the website, which does not share the hub login.
   */
  public function logsIn(): bool {
    return $this !== DoorTarget::Feedback;
  }

}
