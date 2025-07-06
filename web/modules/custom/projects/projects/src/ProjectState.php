<?php

namespace Drupal\projects;

/**
 * Provides project state options.
 */
enum ProjectState: string {

  case Draft = 'draft';
  case Pending = 'pending';
  case Open = 'open';
  case Ongoing = 'ongoing';
  case Completed = 'completed';

}
