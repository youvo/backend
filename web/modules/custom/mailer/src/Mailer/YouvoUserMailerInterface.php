<?php

namespace Drupal\mailer\Mailer;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Mails sent on the registration of users.
 *
 * These mails do not belong to a project and are not recorded as
 * correspondence.
 */
interface YouvoUserMailerInterface extends ComponentMailerInterface {

  /**
   * Sends the registration mail with the one-time login link to a creative.
   *
   * @param \Drupal\creatives\Entity\Creative $creative
   *   The creative.
   * @param string $link
   *   The one-time registration link.
   *
   * @return bool
   *   TRUE if the mail was sent.
   */
  public function registerCreative(Creative $creative, string $link): bool;

  /**
   * Notifies the site mail address that an organization registered.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The new organization.
   *
   * @return bool
   *   TRUE if the mail was sent.
   */
  public function notifyOrganizationCreated(Organization $organization): bool;

}
