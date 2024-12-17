<?php

namespace Drupal\projects\Event;

/**
 * Defines a project complete event.
 *
 * @todo Not clear how the results are stored or rendered, yet.
 */
class ProjectCompleteEvent extends ProjectEventBase {

  /**
   * The result files.
   */
  protected array $files = [];

  /**
   * The result links.
   */
  protected array $links = [];

  /**
   * Gets the result files.
   */
  public function getFiles(): array {
    return $this->files;
  }

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
  public function setFiles(array $file_targets): static {
    $this->files = $file_targets;
    return $this;
  }

  /**
   * Gets the result links.
   */
  public function getLinks(): array {
    return $this->links;
  }

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
  public function setLinks(array $links): static {
    $this->links = $links;
    return $this;
  }

}
