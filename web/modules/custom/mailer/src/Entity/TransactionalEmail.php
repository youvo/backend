<?php

namespace Drupal\mailer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\youvo\SimpleToken;
use Drupal\mailer\TransactionalEmailInterface;

/**
 * Defines the transactional email entity type.
 *
 * @ConfigEntityType(
 *   id = "transactional_email",
 *   label = @Translation("Transactional Email"),
 *   label_collection = @Translation("Transactional Emails"),
 *   label_singular = @Translation("Transactional Email"),
 *   label_plural = @Translation("Transactional Emails"),
 *   label_count = @PluralTranslation(
 *     singular = "@count transactional email",
 *     plural = "@count transactional emails",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\mailer\TransactionalEmailAccessControlHandler",
 *     "list_builder" = "Drupal\mailer\TransactionalEmailListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mailer\Form\TransactionalEmailForm",
 *       "edit" = "Drupal\mailer\Form\TransactionalEmailForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "transactional_email",
 *   admin_permission = "administer transactional emails",
 *   links = {
 *     "collection" = "/admin/structure/transactional-email",
 *     "add-form" = "/admin/structure/transactional-email/add",
 *     "edit-form" = "/admin/structure/transactional-email/{transactional_email}",
 *     "delete-form" = "/admin/structure/transactional-email/{transactional_email}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "uuid",
 *     "label",
 *     "subject",
 *     "body",
 *     "tokens"
 *   }
 * )
 */
class TransactionalEmail extends ConfigEntityBase implements TransactionalEmailInterface {

  /**
   * The transactional email machine name.
   *
   * @var string
   */
  protected string $id;

  /**
   * The transactional email label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The transactional email subject.
   *
   * @var string
   */
  protected string $subject;

  /**
   * The transactional email body.
   *
   * @var string
   */
  protected string $body;

  /**
   * The transactional email tokens.
   *
   * @var array
   */
  protected array $tokens;

  /**
   * {@inheritdoc}
   */
  public function subject(): string {
    return $this->subject ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function body(): string {
    return $this->body ?? '';
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
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b): int {
    $a_id = substr((string) $a->id(), 0, strpos((string) $a->id(), '_') ?: NULL);
    $b_id = substr((string) $b->id(), 0, strpos((string) $b->id(), '_') ?: NULL);
    return strnatcasecmp($a_id, $b_id);
  }

}
