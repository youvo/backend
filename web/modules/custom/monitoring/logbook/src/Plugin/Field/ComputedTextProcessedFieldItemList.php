<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\logbook\LogPatternInterface;

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

    // The platform gets no author tag, since there is no one to link.
    $author = $log->getOwner();
    $replacements['%Author'] = $this->hasAuthor()
      ? '<author>' . $author->getName() . '</author>'
      : $this->fakeTranslateRole();

    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = '<manager>' . $manager->getName() . '</manager>';
    }

    $project = $log->getProject();
    if ($project) {
      $replacements['%Project'] = '<project>' . $project->getTitle() . '</project>';
    }

    // Organization logs have no project.
    if ($organization = $log->getOrganization() ?? $project?->getOwner()) {
      $replacements['%Organization'] = '<organization>' . $organization->getName() . '</organization>';
    }

    if ($creatives = $log->getCreatives()) {
      // The delta in the tag lets the frontend use format() on multiple
      // creatives.
      $names = [];
      foreach (array_values($creatives) as $delta => $creative) {
        $names[] = '<creative' . ($delta + 1) . '>' . $creative->getName() . '</creative' . ($delta + 1) . '>';
      }
      $replacements['%Creatives'] = $this->concatCreativeNames($names);
      $replacements['%Creative'] = $names[0];
    }

    return $replacements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getText(LogPatternInterface $pattern): string {
    return $pattern->getPublicText(TRUE);
  }

}
