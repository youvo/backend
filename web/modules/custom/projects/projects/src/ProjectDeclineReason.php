<?php

namespace Drupal\projects;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides project decline reason options.
 */
enum ProjectDeclineReason: string {

  case Custom = 'custom';
  case Justification = 'justification';
  case Imprecise = 'imprecise';
  case Deadline = 'deadline';
  case Skill = 'skill';
  case Organization = 'organization';
  case Impact = 'impact';
  case Idea = 'idea';
  case Religious = 'religious';

  /**
   * Gets the label of the reason.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Custom => new TranslatableMarkup('No specific reason'),
      self::Justification => new TranslatableMarkup('Volunteering not justified'),
      self::Imprecise => new TranslatableMarkup('Project too vague'),
      self::Deadline => new TranslatableMarkup('Deadline too short'),
      self::Skill => new TranslatableMarkup('Skill does not fit the community'),
      self::Organization => new TranslatableMarkup('Type of organization'),
      self::Impact => new TranslatableMarkup('Impact hard to communicate'),
      self::Idea => new TranslatableMarkup('Only an idea'),
      self::Religious => new TranslatableMarkup('Religious'),
    };
  }

}
