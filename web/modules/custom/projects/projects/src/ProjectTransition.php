<?php

namespace Drupal\projects;

/**
 * Provides project transition options.
 */
enum ProjectTransition: string {

  case Submit = 'submit';
  case Publish = 'publish';
  case Mediate = 'mediate';
  case Complete = 'complete';
  case Reset = 'reset';

}
