<?php

namespace Drupal\youvo\Utility;

class RestPrefix {

  /**
   * @return string
   */
  public static function getPrefix() {
    return \Drupal::config('youvo.settings')->get('rest_prefix');
  }

  /**
   * Prepend rest prefix to path.
   *
   * @param string $path
   * @return string
   */
  public static function prependPrefix(string $path) {
    $prefix = self::getPrefix();
    return !empty($prefix) ? '/' . $prefix . $path : $path;
  }

  /**
   * Get rest prefix.
   *
   * @return string
   */
  public static function getPrefixFromEnvironment() {

    // Load environment file.
    $env_path = dirname(DRUPAL_ROOT) . '/config/.env.api';
    if (!is_readable($env_path)) {
      \Drupal::logger('youvo')
        ->notice('Not using obscurity path for REST resources, since environment file can not be loaded.');
      return '';
    }

    // Get api prefix.
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (str_starts_with(trim($line), 'PREFIX')) {
        $prefix = trim(substr($line, strpos($line, "=") + 1));
      }
    }

    return $prefix ?? '';
  }

}
