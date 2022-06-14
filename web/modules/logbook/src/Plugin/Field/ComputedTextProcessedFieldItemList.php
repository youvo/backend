<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\projects\ProjectInterface;

/**
 * Computes processed texts of logs with tags for frontend.
 */
class ComputedTextProcessedFieldItemList extends ComputedTextFieldItemListBase {

  /**
   * Gets replacements for tokens.
   */
  protected function getReplacements(): array {

    /** @var \Drupal\logbook\LogInterface $log */
    $log = $this->getEntity();

    // Replacement for author.
    /** @var \Drupal\creatives\Entity\Creative|\Drupal\organizations\Entity\Organization $author */
    $author = $log->getOwner();
    $replacements['%Author'] = '<organization>' . $author->getName() . '</organization>';

    // Replacement for organization.
    if ($organization = $log->getOrganization()) {
      $replacements['%Organization'] = '<organization>' . $organization->getName() . '</organization>';
    }

    // Replacement for manager.
    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = '<manager>' . $manager->getName() . '</manager>';
    }

    // Replacement for project.
    if ($project = $log->getProject()) {
      if ($project instanceof ProjectInterface) {
        $replacements['%Project'] = '<project>' . $project->getTitle() . '</project>';
      }
    }

    // Replacement for creatives.
    if ($creatives = $log->getCreatives()) {

      // Get creative names. Append the delta to the tag. This way the
      // frontend can use format() on multiple creatives.
      $names = [];
      foreach (array_values($creatives) as $delta => $creative) {
        $names[] = '<creative' . ($delta + 1) . '>' . $creative->getName() . '</creative' . ($delta + 1) . '>';
      }
      $replacements['%Creatives'] = $this->concatCreativeNames($names);
    }

    return $replacements;
  }

}
