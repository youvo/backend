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
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param string $link
   *   The link to complete the project.
   */
  public function notify(ProjectInterface $project, string $link): bool;

  /**
   * Invites the organization to complete a project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param string $link
   *   The one-time link of the invitation.
   */
  public function invite(ProjectInterface $project, string $link): bool;

}
