<?php

namespace Drupal\projects;

/**
 * Provides project archive reason options.
 */
enum ProjectArchiveReason: string {

  case Unattractive = 'unattractive';
  case Creatives = 'creatives';
  case External = 'external';
  case Other = 'other';

}
