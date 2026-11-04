<?php

namespace Drupal\projects\Service;

use Caxy\HtmlDiff\HtmlDiff;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;
use Drupal\user_types\Utility\Profile;

/**
 * Builds the changes of a project from its revisions, newest first.
 */
class ProjectChangesBuilder {

  /**
   * The number of newest revisions that get an entry.
   */
  public const int LIMIT = 50;

  /**
   * The fields shown as the changed passage, not as old and new value.
   */
  public const array LONG_FIELDS = ['body', 'field_material', 'field_appreciation'];

  /**
   * The tags the inline diff of a long text keeps.
   */
  protected const array DIFF_TAGS = ['del', 'ins', 'p', 'br'];

  /**
   * The German words of the author roles, as in the logbook.
   */
  protected const array ROLE_WORDS = [
    'organization' => 'Organisation',
    'manager' => 'Managerin',
    'supervisor' => 'Supervisorin',
    'administrator' => 'Administratorin',
    'creative' => 'Kreative',
    'platform' => 'Plattform',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DiffEntityComparison $diffEntityComparison,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Builds the changes of a project.
   *
   * @return array{entries: list<array{revision_id: int, author: string, time: int, created: bool, fields: list<array{label: string, long: bool, old: string, new: string, diff: string}>}>, earlier: int}
   *   The entries of the newest revisions, newest first, and the number of
   *   earlier revisions without an entry.
   */
  public function build(ProjectInterface $project): array {

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('project');
    $revision_ids = array_keys($storage->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('id', $project->id())
      ->sort('revision_id', 'DESC')
      ->execute());

    // One revision more than the limit is the base of the oldest entry.
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $revisions = [];
    foreach ($storage->loadMultipleRevisions(array_slice($revision_ids, 0, static::LIMIT + 1)) as $revision) {
      /** @var \Drupal\projects\ProjectInterface $revision */
      $revisions[] = $revision->hasTranslation($langcode) ? $revision->getTranslation($langcode) : $revision;
    }
    usort($revisions, static fn ($a, $b) => $b->getRevisionId() <=> $a->getRevisionId());

    $entries = [];
    foreach (array_slice($revisions, 0, static::LIMIT) as $index => $revision) {
      $previous = $revisions[$index + 1] ?? NULL;
      if ($previous && !$revision->isRevisionTranslationAffected()) {
        continue;
      }
      $entries[] = [
        'revision_id' => (int) $revision->getRevisionId(),
        'author' => $this->author($revision),
        'time' => (int) $revision->getRevisionCreationTime(),
        'created' => $previous === NULL,
        'fields' => $previous ? $this->fields($previous, $revision) : [],
      ];
    }

    return [
      'entries' => $entries,
      'earlier' => max(0, count($revision_ids) - static::LIMIT),
    ];
  }

  /**
   * Gets the fields that differ between two revisions.
   */
  protected function fields(ProjectInterface $previous, ProjectInterface $revision): array {

    $fields = [];
    foreach ($this->diffEntityComparison->compareRevisions($previous, $revision) as $key => $comparison) {
      $old = (string) ($comparison['#data']['#left'] ?? '');
      $new = (string) ($comparison['#data']['#right'] ?? '');
      if ($old === $new) {
        continue;
      }
      $field_name = substr($key, strrpos($key, '.') + 1);
      $long = in_array($field_name, static::LONG_FIELDS, TRUE);
      $fields[] = [
        'label' => (string) $comparison['#name'],
        'long' => $long,
        'old' => $this->plain($old),
        'new' => $this->plain($new),
        'diff' => $long ? $this->passageDiff($old, $new) : '',
      ];
    }
    return $fields;
  }

  /**
   * Turns a compared value into plain text, one line per value.
   */
  protected function plain(string $value): string {
    $lines = array_filter(array_map('trim', explode("\n", strip_tags($value))), static fn (string $line) => $line !== '');
    return implode(', ', $lines);
  }

  /**
   * Marks the changed words of the changed paragraphs, with some context.
   */
  protected function passageDiff(string $old, string $new): string {

    $old_paragraphs = $this->paragraphs($old);
    $new_paragraphs = $this->paragraphs($new);

    // Paragraphs equal at the start and at the end are context at most.
    $start = 0;
    while (isset($old_paragraphs[$start], $new_paragraphs[$start]) && $old_paragraphs[$start] === $new_paragraphs[$start]) {
      $start++;
    }
    $end = 0;
    while (
      $end < count($old_paragraphs) - $start &&
      $end < count($new_paragraphs) - $start &&
      $old_paragraphs[count($old_paragraphs) - 1 - $end] === $new_paragraphs[count($new_paragraphs) - 1 - $end]
    ) {
      $end++;
    }
    $from = max(0, $start - 1);
    $old_passage = array_slice($old_paragraphs, $from, count($old_paragraphs) - $from - max(0, $end - 1));
    $new_passage = array_slice($new_paragraphs, $from, count($new_paragraphs) - $from - max(0, $end - 1));

    $diff = HtmlDiff::create($this->html($old_passage), $this->html($new_passage))->build();
    return Xss::filter($diff, static::DIFF_TAGS);
  }

  /**
   * Splits a text into paragraphs of plain text.
   *
   * @return string[]
   *   The non-empty paragraphs.
   */
  protected function paragraphs(string $text): array {
    $text = preg_replace('~</p>|<br\s*/?>~i', "\n\n", $text);
    $paragraphs = preg_split('~\n\s*\n~', strip_tags($text));
    return array_values(array_filter(array_map('trim', $paragraphs), static fn (string $paragraph) => $paragraph !== ''));
  }

  /**
   * Joins paragraphs of plain text to escaped HTML.
   */
  protected function html(array $paragraphs): string {
    return implode('', array_map(static fn ($paragraph) => '<p>' . htmlspecialchars($paragraph, ENT_QUOTES) . '</p>', $paragraphs));
  }

  /**
   * Names the author of a revision with the role, as the logbook does.
   */
  protected function author(ProjectInterface $revision): string {

    $user = $revision->getRevisionUser();
    if (!$user instanceof UserInterface || $user->isAnonymous()) {
      return static::ROLE_WORDS['platform'];
    }

    $role = 'creative';
    $organization = $revision->getOwner();
    if (Profile::isOrganization($user)) {
      $role = 'organization';
    }
    elseif ($organization instanceof Organization && $organization->isManager($user)) {
      $role = 'manager';
    }
    else {
      foreach (['supervisor', 'administrator'] as $staff_role) {
        if (in_array($staff_role, $user->getRoles(), TRUE)) {
          $role = $staff_role;
          break;
        }
      }
    }

    return static::ROLE_WORDS[$role] . ' ' . $user->getAccountName();
  }

}
