<?php

namespace Drupal\mailer\Manager;

/**
 * Stops the mail pipeline after the render of a preview.
 *
 * Unlike SkipMailException this is not caught by the mailer, so no skip
 * message is added to the messenger.
 */
final class PreviewStop extends \Exception {

}
