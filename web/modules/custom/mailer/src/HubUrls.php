<?php

namespace Drupal\mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\projects\ProjectInterface;

/**
 * Builds links into the hub (organizations frontend).
 */
class HubUrls {

  /**
   * Constructs a HubUrls object.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * Gets the link organizations use to open this project.
   */
  public function project(ProjectInterface $project): string {
    $base = rtrim((string) $this->configFactory->get('mailer.settings')->get('hub_url'), '/');
    $path = $project->lifecycle()->isDraft() ? '/draft/' : '/projects/';
    return $base . $path . $project->uuid();
  }

}
