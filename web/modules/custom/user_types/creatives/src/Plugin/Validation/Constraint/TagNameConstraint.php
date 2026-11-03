<?php

declare(strict_types=1);

namespace Drupal\creatives\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a tag name keeps characters and is not taken by another tag.
 *
 * Core's UniqueField constraint compares across all vocabularies, so a tag
 * "design" would collide with the skill "Design".
 */
#[Constraint(
  id: 'TagName',
  label: new TranslatableMarkup('Tag name', [], ['context' => 'Validation']),
)]
class TagNameConstraint extends SymfonyConstraint {

  /**
   * Message for a name without any allowed characters.
   */
  public string $empty = 'The tag %name contains no letters or digits.';

  /**
   * Message for a name another tag already has.
   */
  public string $taken = 'The tag %name already exists.';

}
