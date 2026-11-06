<?php

namespace Drupal\mailer_door;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\projects\ProjectInterface;

/**
 * Builds links into the hub (organizations) and the app (creatives).
 */
class HubUrls {

  /**
   * Constructs a HubUrls object.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * Gets the hub base URL without a trailing slash.
   *
   * With the local OAuth setting, the hub runs on the developer machine.
   */
  public function base(): string {
    if ($this->configFactory->get('oauth_grant.settings')->get('local')) {
      return 'http://localhost:3000';
    }
    return rtrim((string) $this->configFactory->get('youvo.settings')->get('hub_url'), '/');
  }

  /**
   * Gets the app base URL without a trailing slash.
   *
   * With the local OAuth setting, the app runs on the developer machine.
   */
  public function appBase(): string {
    if ($this->configFactory->get('oauth_grant.settings')->get('local')) {
      return 'http://localhost:3000';
    }
    return rtrim((string) $this->configFactory->get('youvo.settings')->get('app_url'), '/');
  }

  /**
   * Gets the link organizations use to open this project.
   */
  public function project(ProjectInterface $project): string {
    $path = $project->lifecycle()->isDraft() ? '/draft/' : '/projects/';
    return $this->base() . $path . $project->uuid();
  }

}
