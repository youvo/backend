<?php

namespace Drupal\logbook\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\logbook\LogPatternInterface;
use Drupal\youvo\SimpleTokenReplacer;

/**
 * Provides a base for computed text fields for log events.
 */
abstract class ComputedTextFieldItemListBase extends FieldItemList implements FieldItemListInterface {

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
      $pattern = $this->getPattern();

      // Replace and validate tokens in text.
      $text = $pattern->publicText(TRUE);
      $tokens = $pattern->tokens();
      $this->simpleTokenReplacer()->populateReplacements($this->getReplacements(), $tokens);
      $this->simpleTokenReplacer()->replace($text, $tokens);
      $this->simpleTokenReplacer()->validate($tokens);

      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
      $item = $this->createItem(0, $text);

      // Add cache dependencies.
      $item->getValueProperty()->addCacheableDependency($log)
        ->addCacheableDependency($pattern)
        ->addCacheableDependency($log->getOwner());
      if ($creatives = $log->getCreatives()) {
        foreach ($creatives as $creative) {
          $item->getValueProperty()->addCacheableDependency($creative);
        }
      }
      if ($manager = $log->getManager()) {
        $item->getValueProperty()->addCacheableDependency($manager);
      }
      if ($organization = $log->getOrganization()) {
        $item->getValueProperty()->addCacheableDependency($organization);
      }
      if ($project = $log->getProject()) {
        $item->getValueProperty()->addCacheableDependency($project);
      }

      $this->list[0] = $item;
    }
  }

  /**
   * Gets replacements for tokens.
   */
  abstract protected function getReplacements(): array;

  /**
   * Gets the pattern related to the event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getPattern(): LogPatternInterface {
    $log = $this->getEntity();
    /** @var \Drupal\logbook\LogPatternInterface $pattern */
    $pattern = \Drupal::entityTypeManager()
      ->getStorage($log->getEntityType()->getBundleEntityType())
      ->load($log->bundle());
    return $pattern;
  }

  /**
   * Concatinates creative names.
   *
   * @todo Use fake translation here. It could be handled by interface
   *   translation in the future.
   */
  protected function concatCreativeNames(array $names): string {
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

    return $concat_names;
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
