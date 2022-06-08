<?php

namespace Drupal\logbook\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\logbook\LogPatternInterface;
use Drupal\logbook\LogTextInterface;
use Drupal\youvo\SimpleToken;

/**
 * Defines the Log Pattern entity type.
 *
 * @ConfigEntityType(
 *   id = "log_pattern",
 *   label = @Translation("Log Pattern"),
 *   label_collection = @Translation("Log Patterns"),
 *   label_singular = @Translation("log pattern"),
 *   label_plural = @Translation("log patterns"),
 *   label_count = @PluralTranslation(
 *     singular = "@count log pattern",
 *     plural = "@count log patterns",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\logbook\LogPatternListBuilder",
 *     "form" = {
 *       "add" = "Drupal\logbook\Form\LogPatternForm",
 *       "edit" = "Drupal\logbook\Form\LogPatternForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "log_pattern",
 *   admin_permission = "administer log pattern",
 *   bundle_of = "log_event",
 *   links = {
 *     "collection" = "/admin/structure/log-pattern",
 *     "add-form" = "/admin/structure/log-pattern/add",
 *     "edit-form" = "/admin/structure/log-pattern/{log_pattern}",
 *     "delete-form" = "/admin/structure/log-pattern/{log_pattern}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "tokens",
 *     "promote",
 *     "hidden"
 *   }
 * )
 */
class LogPattern extends ConfigEntityBundleBase implements LogPatternInterface {

  /**
   * The log pattern machine name.
   *
   * @var string
   */
  protected string $id;

  /**
   * The log pattern label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The log pattern tokens.
   *
   * @var array
   */
  protected array $tokens;

  /**
   * The log pattern promoted status.
   *
   * @var bool
   */
  protected bool $promote;

  /**
   * The log pattern hidden status.
   *
   * @var bool
   */
  protected bool $hidden;

  /**
   * The log text entity.
   *
   * @var \Drupal\logbook\Entity\LogText
   */
  protected LogText $log_text;

  /**
   * {@inheritdoc}
   */
  public function text(): string {
    if ($this->isNew()) {
      return '';
    }
    if ($log_text = $this->getLogTextEntity()) {
      return $log_text->getText();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function publicText(bool $fallback = FALSE): string {
    if ($this->isNew()) {
      return '';
    }
    if ($log_text = $this->getLogTextEntity()) {
      return $log_text->getPublicText($fallback);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function promoted() {
    return !empty($this->promote);
  }

  /**
   * {@inheritdoc}
   */
  public function hidden() {
    return !empty($this->hidden);
  }

  /**
   * {@inheritdoc}
   */
  public function tokens(bool $as_array = FALSE): array {
    if ($as_array) {
      return $this->tokens ?? [];
    }
    return SimpleToken::createMultiple($this->tokens ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getLogTextEntity(): ?LogTextInterface {
    if (isset($this->log_text)) {
      return $this->log_text;
    }
    try {
      $log_text = $this->entityTypeManager()->getStorage('log_text')
        ->loadByProperties(['log_pattern' => $this->id()]);
      $log_text = reset($log_text);
      /** @var \Drupal\logbook\Entity\LogText|false $log_text */
      if ($log_text instanceof LogText) {
        $this->log_text = $log_text;
        return $log_text;
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
    }
    return NULL;
  }

}
