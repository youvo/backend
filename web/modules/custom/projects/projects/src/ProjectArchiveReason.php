<?php

namespace Drupal\projects;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides project archive reason options.
 */
enum ProjectArchiveReason: string {

  case Custom = 'custom';
  case Unattractive = 'unattractive';
  case External = 'external';

  /**
   * Gets the label of the reason.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Custom => new TranslatableMarkup('Custom reason'),
      self::Unattractive => new TranslatableMarkup('Project unattractive'),
      self::External => new TranslatableMarkup('External mediation'),
    };
  }

}
