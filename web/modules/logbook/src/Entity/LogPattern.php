<?php

namespace Drupal\logbook\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\logbook\LogPatternInterface;
use Drupal\mailer\MailerToken;

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
 *     "text",
 *     "public_text",
 *     "tokens",
 *     "promote",
 *     "hidden"
 *   }
 * )
 */
class LogPattern extends ConfigEntityBase implements LogPatternInterface {

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
   * The log pattern text.
   *
   * @var string
   */
  protected string $text;

  /**
   * The log pattern public text.
   *
   * @var string
   */
  protected string $public_text;

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
   * {@inheritdoc}
   */
  public function text(): string {
    return $this->text ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function publicText(): string {
    return $this->public_text ?? '';
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
    return MailerToken::createMultiple($this->tokens ?? []);
  }

}
