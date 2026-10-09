<?php

namespace Drupal\manager\Plugin\ManagerRule;

/**
 * Provides manager rule severity.
 */
enum RuleSeverity {

  case Dormant;
  case Critical;
  case Warning;
  case Normal;

}
