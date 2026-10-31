<?php

namespace Drupal\logbook\Plugin\views\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsCache;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Tag based caching that also follows projects, users and log texts.
 *
 * The search bar of the logbook matches project titles, project owners and
 * organization names in subqueries, not in joins, and the log texts of the
 * patterns in PHP, so Views does not know the results depend on them.
 */
#[ViewsCache(
  id: 'logbook_tag',
  title: new TranslatableMarkup('Logbook tags'),
  help: new TranslatableMarkup('Tag based caching that also follows projects and users.'),
)]
class LogbookTag extends Tag {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'project_list',
      'user_list',
      'log_text_list',
      'config:log_pattern_list',
    ]);
  }

}
