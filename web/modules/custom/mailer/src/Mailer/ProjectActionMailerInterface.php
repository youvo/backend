<?php

namespace Drupal\mailer\Mailer;

use Drupal\creatives\Entity\Creative;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Mails sent on actions of users on a project.
 */
interface ProjectActionMailerInterface extends ComponentMailerInterface {

  /**
   * Notifies the organization and the creative of an application.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Drupal\creatives\Entity\Creative $applicant
   *   The applicant.
   * @param string $message
   *   The message of the applicant. It is user input.
   * @param string $phone
   *   The phone number of the applicant, may be empty.
   *
   * @return bool
   *   TRUE if all mails were sent.
   */
  public function apply(ProjectInterface $project, Creative $applicant, string $message, string $phone): bool;

  /**
   * Notifies the organization that a project was created for it.
   *
   * The mail carries a door to the draft.
   */
  public function notify(ProjectInterface $project): bool;

  /**
   * Invites the organization to complete a project.
   *
   * The mail carries a door to the draft, where the prospect sets its password.
   */
  public function invite(ProjectInterface $project): bool;

}
