<?php

namespace Drupal\projects;

/**
 * Provides project transition options.
 */
enum ProjectTransition: string {

  case SUBMIT = 'submit';
  case PUBLISH = 'publish';
  case MEDIATE = 'mediate';
  case COMPLETE = 'complete';
  case RESET = 'reset';

}
