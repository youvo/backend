<?php

/**
 * @file
 */

namespace Drupal\projects;

/**
 * Provides project state options.
 */
enum ProjectState: string {

  case DRAFT = 'draft';
  case PENDING = 'pending';
  case OPEN = 'open';
  case ONGOING = 'ongoing';
  case COMPLETED = 'completed';

}
