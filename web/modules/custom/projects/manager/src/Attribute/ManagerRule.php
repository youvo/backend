<?php

namespace Drupal\manager\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines the ManagerRule plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ManagerRule extends Plugin {}
