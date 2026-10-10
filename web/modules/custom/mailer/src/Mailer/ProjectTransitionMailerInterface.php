<?php

namespace Drupal\mailer\Mailer;

use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Mails sent on project transitions.
 */
interface ProjectTransitionMailerInterface extends ComponentMailerInterface {

  /**
   * Notifies the organization that its project was published.
   */
  public function publish(ProjectInterface $project): bool;

}
