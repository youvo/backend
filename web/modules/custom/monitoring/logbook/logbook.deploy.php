<?php

/**
 * @file
 * Deploy hooks for the Logbook module, run by drush deploy after the config.
 */

/**
 * Imports the log texts of the project hide and show patterns.
 */
function logbook_deploy_hide_show_log_texts(): void {
  \Drupal::moduleHandler()->loadInclude('logbook', 'install');
  logbook_import_log_texts();
}

/**
 * Imports the log texts of the project archive and unarchive patterns.
 */
function logbook_deploy_archive_log_texts(): void {
  \Drupal::moduleHandler()->loadInclude('logbook', 'install');
  logbook_import_log_texts();
}

/**
 * Fixes the grammar of the log text of the project mediate pattern.
 *
 * The import only adds missing texts, so the existing text is updated here,
 * unless someone changed it in the meantime.
 */
function logbook_deploy_mediate_log_text(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('log_text');
  /** @var \Drupal\logbook\LogTextInterface $log_text */
  foreach ($storage->loadByProperties(['log_pattern' => 'project_mediate']) as $log_text) {
    if ($log_text->get('text')->value === 'Die %AuthorWithRole hat das Projekt %Project wurde an %Creatives vermittelt.') {
      $log_text->set('text', 'Die %AuthorWithRole hat das Projekt %Project an %Creatives vermittelt.');
      $log_text->save();
    }
  }
}
