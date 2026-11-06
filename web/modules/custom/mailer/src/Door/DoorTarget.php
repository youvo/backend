<?php

namespace Drupal\mailer\Door;

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
