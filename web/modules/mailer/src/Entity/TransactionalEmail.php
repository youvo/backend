<?php

namespace Drupal\mailer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\mailer\MailerToken;
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
  public function subject() {
    return $this->subject ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function body() {
    return $this->body ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function tokens($as_object = FALSE) {
    if ($as_object) {
      return MailerToken::createMultiple($this->tokens);
    }
    return $this->tokens;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    $a_id = strstr($a->id(), '_', TRUE);
    $b_id = strstr($a->id(), '_', TRUE);
    return strnatcasecmp($a_id, $b_id);
  }

}
