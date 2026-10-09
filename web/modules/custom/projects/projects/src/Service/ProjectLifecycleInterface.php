<?php

namespace Drupal\projects\Service;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\projects\ProjectInterface;

/**
 * Provides methods to manage the workflow of a project.
 */
interface ProjectLifecycleInterface {

  /**
   * Sets the project.
   */
  public function setProject(ProjectInterface $project): static;

  /**
   * Gets the project.
   */
  public function project(): ProjectInterface;

  /**
   * Checks if the project is a draft.
   */
  public function isDraft(): bool;

  /**
   * Checks if the project is pending.
   */
  public function isPending(): bool;

  /**
   * Checks if the project is open.
   */
  public function isOpen(): bool;

  /**
   * Checks if the project is ongoing.
   */
  public function isOngoing(): bool;

  /**
   * Checks if the project is completed.
   */
  public function isCompleted(): bool;

  /**
   * Submits the project.
   */
  public function submit(?int $timestamp = NULL): bool;

  /**
   * Publishes the project.
   */
  public function publish(?int $timestamp = NULL): bool;

  /**
   * Mediates the project.
   */
  public function mediate(?int $timestamp = NULL): bool;

  /**
   * Completes the project.
   */
  public function complete(?int $timestamp = NULL): bool;

  /**
   * Resets the project.
   */
  public function reset(?int $timestamp = NULL): bool;

  /**
   * Gets the lifecycle history.
   */
  public function history(): FieldItemListInterface;

}
