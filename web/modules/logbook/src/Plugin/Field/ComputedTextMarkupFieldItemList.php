<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\Core\Link;
use Drupal\projects\ProjectInterface;

/**
 * Computes processed texts of logs with markup for backend.
 */
class ComputedTextMarkupFieldItemList extends ComputedTextFieldItemListBase {

  /**
   * Gets replacements for tokens.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getReplacements(): array {

    /** @var \Drupal\logbook\LogInterface $log */
    $log = $this->getEntity();

    // Replacement for author.
    $author = $log->getOwner();
    $replacements['%Author'] = Link::fromTextAndUrl($author->getName(), $author->toUrl())->toString();

    // Replacement for organization.
    if ($organization = $log->getOrganization()) {
      $replacements['%Organization'] = Link::fromTextAndUrl($organization->getName(), $organization->toUrl())->toString();
    }

    // Replacement for manager.
    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = Link::fromTextAndUrl($manager->getName(), $manager->toUrl())->toString();
    }

    // Replacement for project.
    if ($project = $log->getProject()) {
      if ($project instanceof ProjectInterface) {
        $replacements['%Project'] = Link::fromTextAndUrl($project->getTitle(), $project->toUrl())->toString();
      }
    }

    // Replacement for creatives.
    if ($creatives = $log->getCreatives()) {
      $names = [];
      foreach ($creatives as $creative) {
        $names[] = Link::fromTextAndUrl($creative->getName(), $creative->toUrl())->toString();
      }
      $replacements['%Creatives'] = $this->concatCreativeNames($names);
    }

    return $replacements;
  }

}
