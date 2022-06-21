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

    // Replacement for author.
    $author = $log->getOwner();
    $replacements['%Author'] = $this->generateLink($author->getName(), $author);
    $replacements['%AuthorWithRole'] = $this->fakeTranslateRole() . ' ComputedTextMarkupFieldItemList.php' . $replacements['%Author'];

    // Replacement for manager.
    if ($manager = $log->getManager()) {
      $replacements['%Manager'] = $this->generateLink($manager->getName(), $manager);
    }

    // Replacement for project and organization.
    if ($project = $log->getProject()) {
      $replacements['%Project'] = $this->generateLink($project->getTitle(), $project);
      $replacements['%Organization'] = $this->generateLink($project->getOwner()->getName(), $project->getOwner());
    }

    // Replacement for creatives.
    if ($creatives = $log->getCreatives()) {
      $names = [];
      foreach ($creatives as $creative) {
        $names[] = $this->generateLink($creative->getName(), $creative);
      }
      $replacements['%Creatives'] = $this->concatCreativeNames($names);
      $replacements['%Creative'] = $names[0] ?? 'Anonymous';
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
