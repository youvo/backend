<?php

declare(strict_types=1);

namespace Drupal\creatives\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\creatives\TagName;
use Drupal\taxonomy\TermInterface;

/**
 * Keeps the names of the creative tags in one format and unique.
 */
class TagHooks {

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'taxonomy_term' && isset($fields['name'])) {
      $fields['name']->addConstraint('TagName');
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for taxonomy_term.
   */
  #[Hook('taxonomy_term_presave')]
  public function taxonomyTermPresave(TermInterface $term): void {
    if ($term->bundle() === TagName::VOCABULARY) {
      $term->setName(TagName::format($term->getName()));
    }
  }

}
