<?php

namespace Drupal\logbook;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Maps log patterns to the groups of the logbook filter.
 *
 * A pattern that is not in the map belongs to the group "other".
 */
class LogGroups {

  use StringTranslationTrait;

  /**
   * The group of each known pattern.
   */
  protected const MAP = [
    'project_create' => 'lifecycle',
    'project_submit' => 'lifecycle',
    'project_publish' => 'lifecycle',
    'project_complete' => 'lifecycle',
    'project_reset' => 'lifecycle',
    'project_archive' => 'lifecycle',
    'project_promote' => 'lifecycle',
    'project_demote' => 'lifecycle',
    'project_apply' => 'creatives',
    'project_invite' => 'creatives',
    'project_notify' => 'creatives',
    'project_mediate' => 'creatives',
    'creative_register' => 'creatives',
    'organization_create' => 'organizations',
    'organization_manage' => 'organizations',
    'organization_disband' => 'organizations',
  ];

  /**
   * Gets the group of a pattern.
   */
  public function group(string $pattern_id): string {
    return self::MAP[$pattern_id] ?? 'other';
  }

  /**
   * Gets the group labels, keyed by group.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The labels.
   */
  public function options(): array {
    return [
      'lifecycle' => $this->t('Lifecycle'),
      'creatives' => $this->t('Creatives'),
      'organizations' => $this->t('Organizations'),
      'other' => $this->t('Other'),
    ];
  }

}
