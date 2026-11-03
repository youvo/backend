<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\logbook\LogPatternInterface;
use Drupal\user\UserInterface;

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

    // A log without an author names only the platform. The texts still fit,
    // because all role words are feminine.
    $author = $log->getOwner();
    if ($this->hasAuthor()) {
      $replacements['%Author'] = $this->generateLink($author->getName(), $author);
      $replacements['%AuthorWithRole'] = $this->fakeTranslateRole() . ' ' . $replacements['%Author'];
    }
    else {
      $replacements['%Author'] = $replacements['%AuthorWithRole'] = $this->fakeTranslateRole();
    }

    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = $this->generateLink($manager->getName(), $manager);
    }

    $project = $log->getProject();
    if ($project) {
      $replacements['%Project'] = $this->generateLink($project->getTitle(), $project);
    }

    // Organization logs have no project.
    if ($organization = $log->getOrganization() ?? $project?->getOwner()) {
      $replacements['%Organization'] = $this->generateLink($organization->getName(), $organization);
    }

    if ($creatives = $log->getCreatives()) {
      $names = [];
      foreach ($creatives as $creative) {
        $names[] = $this->generateLink($creative->getName(), $creative);
      }
      $replacements['%Creatives'] = $this->concatCreativeNames($names);
      $replacements['%Creative'] = $names[0];
    }

    return $replacements;
  }

  /**
   * Generates link for entity with given text and respects anonymous users.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function generateLink(string $text, ContentEntityInterface $entity): string {
    if ($entity instanceof UserInterface && $entity->isAnonymous()) {
      return $text;
    }
    return Link::fromTextAndUrl($text, $entity->toUrl('canonical', [
      'language' => \Drupal::languageManager()->getCurrentLanguage(),
    ]))->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getText(LogPatternInterface $pattern): string {
    return $pattern->getText();
  }

}
