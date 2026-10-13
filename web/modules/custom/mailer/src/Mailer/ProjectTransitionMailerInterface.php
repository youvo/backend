<?php

namespace Drupal\mailer\Mailer;

use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Mails sent on project transitions.
 */
interface ProjectTransitionMailerInterface extends ComponentMailerInterface {

  /**
   * Notifies the manager and the organization that a project was submitted.
   *
   * @return bool
   *   TRUE if all mails were sent.
   */
  public function submit(ProjectInterface $project): bool;

  /**
   * Notifies the organization that its project was published.
   */
  public function publish(ProjectInterface $project): bool;

  /**
   * Notifies the organization, the creatives and the staff of a mediation.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Drupal\creatives\Entity\Creative[] $creatives
   *   The selected creatives.
   *
   * @return bool
   *   TRUE if all mails were sent.
   */
  public function mediate(ProjectInterface $project, array $creatives): bool;

  /**
   * Notifies the organization and the participants of a completed project.
   *
   * @return bool
   *   TRUE if all mails were sent.
   */
  public function complete(ProjectInterface $project): bool;

}
