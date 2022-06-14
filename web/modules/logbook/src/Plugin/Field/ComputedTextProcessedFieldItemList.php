<?php

namespace Drupal\logbook\Plugin\Field;

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

    // Replacement for manager.
    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = '<manager>' . $manager->getName() . '</manager>';
    }

    // Replacement for project and organization.
    if ($project = $log->getProject()) {
      $replacements['%Project'] = '<project>' . $project->getTitle() . '</project>';
      $replacements['%Organization'] = '<organization>' . $project->getOwner()->getName() . '</organization>';
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

    return $replacements ?? [];
  }

}
