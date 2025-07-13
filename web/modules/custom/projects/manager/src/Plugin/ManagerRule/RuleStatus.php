<?php

namespace Drupal\manager\Plugin\ManagerRule;

/**
 * Provides manager rule typess.
 */
enum RuleStatus: string {

  case Inactive = 'inactive';
  case Normal = 'normal';
  case Warning = 'warning';
  case Critical = 'critical';

}
