<?php

namespace Drupal\manager\Plugin\ManagerRule;

/**
 * Provides manager rule severity.
 */
enum RuleSeverity {

  case Normal;
  case Warning;
  case Critical;
  case Dormant;

}
