<?php

namespace Drupal\projects;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface defining a project result entity type.
 */
interface ProjectResultInterface extends ContentEntityInterface, EntityChangedInterface, ChildEntityInterface {

  /**
   * Gets the project result creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the project result creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Sets the files by file IDs.
   *
   * @param array $file_targets
   *   An array of file targets. Each entry has the form:
   *   ['target_id' => int, 'weight' => int, 'description' => ?string].
   *
   * @return $this
   *   The called project result entity.
   */
  public function setFiles(array $file_targets): static;

  /**
   * Sets the hyperlinks field.
   *
   * @param array $links
   *   An array of hyperlinks. Each entry has the form:
   *   ['value' => string, 'weight' => int, 'description' => ?string].
   *
   * @return $this
   *   The called project result entity.
   */
  public function setLinks(array $links): static;

  /**
   * Appends a project comment.
   *
   * @param \Drupal\projects\ProjectCommentInterface $comment
   *   The project comment entity.
   *
   * @return $this
   *   The called project result entity.
   */
  public function appendComment(ProjectCommentInterface $comment): static;

  /**
   * Gets the project comments.
   *
   * @return \Drupal\projects\ProjectCommentInterface[]
   *   The comments.
   */
  public function getComments(): array;

  /**
   * Gets the comment by the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return string|null
   *   The comment, if found.
   */
  public function getCommentByUser(AccountInterface $account): ?string;

}
