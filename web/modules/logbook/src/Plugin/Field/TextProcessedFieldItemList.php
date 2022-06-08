<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\projects\ProjectInterface;
use Drupal\youvo\SimpleTokenReplacer;

/**
 * Computes processed texts of log events with tags for frontend.
 */
class TextProcessedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * The simple token replacer.
   *
   * @var \Drupal\youvo\SimpleTokenReplacer
   */
  protected SimpleTokenReplacer $simpleTokenReplacer;

  /**
   * Gets the simple token replacer.
   *
   * @todo Replace with proper DI after
   *   https://www.drupal.org/project/drupal/issues/2914419 or
   *   https://www.drupal.org/project/drupal/issues/2053415
   *
   * @return \Drupal\youvo\SimpleTokenReplacer
   *   The simple token replacer.
   */
  protected function simpleTokenReplacer() {
    if (!isset($this->simpleTokenReplacer)) {
      $this->simpleTokenReplacer = \Drupal::service('youvo.token_replacer');
    }
    return $this->simpleTokenReplacer;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      /** @var \Drupal\logbook\LogEventInterface $log */
      $log = $this->getEntity();
      /** @var \Drupal\logbook\LogPatternInterface $pattern */
      $pattern = \Drupal::entityTypeManager()
        ->getStorage($log->getEntityType()->getBundleEntityType())
        ->load($log->bundle());

      $text = $pattern->publicText(TRUE);
      $tokens = $pattern->tokens();
      $replacements = [];

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
          $replacements['%Project'] = '<subject>' . $project->getTitle() . '</subject>';
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

        // Concatinate string for names.
        // @todo Use fake translation here. It could be handled by interface
        //   translation in the future.
        if (count($names) <= 3) {
          $first = implode(', ', array_slice($names, 0, -1));
          $last = array_slice($names, -1);
          $both = array_filter(array_merge([$first], $last), 'strlen');
          $concat_names = implode(' ' . $this->fakeTranslateAnd() . ' ', $both);
        }
        else {
          $first = implode(', ', array_slice($names, 0, 3));
          $more = array_slice($names, 3);
          $concat_names = $first . ' ' . $this->fakeTranslateMoreCreatives($more);
        }

        $replacements['%Creatives'] = $concat_names;
      }

      // Replace and validate tokens in text.
      $this->simpleTokenReplacer()->populateReplacements($replacements, $tokens);
      $this->simpleTokenReplacer()->replace($text, $tokens);
      $this->simpleTokenReplacer()->validate($tokens);

      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
      $item = $this->createItem(0, $text);

      // Add cache dependencies.
      $item->getValueProperty()->addCacheableDependency($log)
        ->addCacheableDependency($pattern);

      if (!empty($creatives)) {
        foreach ($creatives as $creative) {
          $item->getValueProperty()->addCacheableDependency($creative);
        }
      }
      if (isset($manager)) {
        $item->getValueProperty()->addCacheableDependency($manager);
      }
      if (isset($organization)) {
        $item->getValueProperty()->addCacheableDependency($organization);
      }
      if (isset($project)) {
        $item->getValueProperty()->addCacheableDependency($project);
      }

      $this->list[0] = $item;
    }
  }

  /**
   * Returns 'and' in English or German.
   */
  protected function fakeTranslateAnd(): string {
    if (\Drupal::languageManager()->getCurrentLanguage()->getId() == 'de') {
      return 'und';
    }
    return 'and';
  }

  /**
   * Returns 'more creatives' in English or German.
   */
  protected function fakeTranslateMoreCreatives(array $more): string {
    $text = 'and %count other creatives';
    $one = 'one';
    if (\Drupal::languageManager()->getCurrentLanguage()->getId() == 'de') {
      $text = 'und %count weitere Kreative';
      $one = 'eine';
    }
    $extra_count = count($more) == 1 ? $one : count($more);
    return str_replace('%count', $extra_count, $text);
  }

}
