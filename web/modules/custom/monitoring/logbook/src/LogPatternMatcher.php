<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Finds the log patterns that match the filters of the logbook.
 *
 * The flags live on the log pattern config entities and cannot be joined in
 * SQL, so the filters resolve pattern ids here and query by log type.
 */
class LogPatternMatcher {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LogGroups $groups,
  ) {}

  /**
   * Gets the ids of the enabled, observable patterns.
   *
   * @return string[]
   *   The pattern ids.
   */
  public function scopeIds(): array {
    return $this->ids(static fn (LogPatternInterface $p) => $p->isEnabled() && $p->isObservable());
  }

  /**
   * Gets the ids of the patterns that are not hidden.
   *
   * @return string[]
   *   The pattern ids.
   */
  public function visibleIds(): array {
    return $this->ids(static fn (LogPatternInterface $p) => !$p->isHidden());
  }

  /**
   * Gets the ids of the patterns of a group.
   *
   * @return string[]
   *   The pattern ids.
   */
  public function groupIds(string $group): array {
    return $this->ids(fn (LogPatternInterface $p) => $this->groups->group((string) $p->id()) === $group);
  }

  /**
   * Gets the ids of the patterns with a visibility.
   *
   * @param string $visibility
   *   One of internal (not public), public (all public, including promoted) or
   *   promoted (the public ones that are promoted, a subset of public).
   *
   * @return string[]
   *   The pattern ids.
   */
  public function visibilityIds(string $visibility): array {
    return match ($visibility) {
      'internal' => $this->ids(static fn (LogPatternInterface $p) => !$p->isPublic()),
      'public' => $this->ids(static fn (LogPatternInterface $p) => $p->isPublic()),
      'promoted' => $this->ids(static fn (LogPatternInterface $p) => $p->isPublic() && $p->isPromoted()),
      default => [],
    };
  }

  /**
   * Gets the group options of the group filter.
   */
  public function groupOptions(): array {
    return $this->groups->options();
  }

  /**
   * Gets the options of the visibility filter.
   */
  public function visibilityOptions(): array {
    return [
      'internal' => $this->t('Internal'),
      'public' => $this->t('Public'),
      'promoted' => $this->t('Promoted'),
    ];
  }

  /**
   * Gets the ids of the patterns that match.
   *
   * @param callable $match
   *   Gets a pattern, returns TRUE if it matches.
   *
   * @return string[]
   *   The pattern ids.
   */
  protected function ids(callable $match): array {
    $ids = [];
    /** @var \Drupal\logbook\LogPatternInterface $pattern */
    foreach ($this->entityTypeManager->getStorage('log_pattern')->loadMultiple() as $id => $pattern) {
      if ($match($pattern)) {
        $ids[] = (string) $id;
      }
    }
    return $ids;
  }

}
