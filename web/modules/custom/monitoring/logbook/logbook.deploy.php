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
