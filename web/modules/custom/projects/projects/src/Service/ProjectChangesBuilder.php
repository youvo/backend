<?php

namespace Drupal\projects\Service;

use Caxy\HtmlDiff\HtmlDiff;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\creatives\Entity\Creative;
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
   * @return array{entries: list<array{revision_id: int, author: string, author_role: string, author_name: string, author_id: int|null, time: int, created: bool, fields: list<array{label: string, long: bool, old: string, new: string, diff: string}>}>, earlier: int}
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
        ...$this->author($revision),
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
    // Diff escapes the values; Twig escapes them again on output.
    $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);
    $lines = array_filter(array_map('trim', explode("\n", $text)), static fn (string $line) => $line !== '');
    return implode(', ', $lines);
  }

  /**
   * Marks the changed words of the changed sentences, with one around them.
   *
   * Left out text at the start or the end shows as an ellipsis.
   */
  protected function passageDiff(string $old, string $new): string {

    $old_sentences = $this->sentences($old);
    $new_sentences = $this->sentences($new);
    $old_texts = array_column($old_sentences, 'text');
    $new_texts = array_column($new_sentences, 'text');

    // Sentences equal at the start and at the end are context at most.
    $start = 0;
    while (isset($old_texts[$start], $new_texts[$start]) && $old_texts[$start] === $new_texts[$start]) {
      $start++;
    }
    $end = 0;
    while (
      $end < count($old_texts) - $start &&
      $end < count($new_texts) - $start &&
      $old_texts[count($old_texts) - 1 - $end] === $new_texts[count($new_texts) - 1 - $end]
    ) {
      $end++;
    }
    $from = max(0, $start - 1);
    $cut_end = max(0, $end - 1);
    $old_passage = array_slice($old_sentences, $from, count($old_sentences) - $from - $cut_end);
    $new_passage = array_slice($new_sentences, $from, count($new_sentences) - $from - $cut_end);

    $diff = HtmlDiff::create(
      $this->html($old_passage, $from > 0, $cut_end > 0),
      $this->html($new_passage, $from > 0, $cut_end > 0),
    )->build();
    return Xss::filter($diff, static::DIFF_TAGS);
  }

  /**
   * Splits a text into sentences of plain text, with their paragraph.
   *
   * @return list<array{paragraph: int, text: string}>
   *   The sentences in order.
   */
  protected function sentences(string $text): array {

    $text = preg_replace('~</p>|<br\s*/?>~i', "\n\n", $text);
    $paragraphs = preg_split('~\n\s*\n~', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), static fn (string $paragraph) => $paragraph !== ''));

    $sentences = [];
    foreach ($paragraphs as $index => $paragraph) {
      foreach (preg_split('~(?<=[.!?…])\s+~u', $paragraph) as $sentence) {
        $sentences[] = ['paragraph' => $index, 'text' => $sentence];
      }
    }
    return $sentences;
  }

  /**
   * Joins sentences to escaped HTML, one element per paragraph.
   */
  protected function html(array $sentences, bool $cut_start, bool $cut_end): string {

    $paragraphs = [];
    foreach ($sentences as $sentence) {
      $paragraphs[$sentence['paragraph']][] = htmlspecialchars($sentence['text'], ENT_QUOTES);
    }
    $paragraphs = array_map(static fn (array $texts) => implode(' ', $texts), array_values($paragraphs));
    if ($paragraphs && $cut_start) {
      $paragraphs[0] = '… ' . $paragraphs[0];
    }
    if ($paragraphs && $cut_end) {
      $paragraphs[count($paragraphs) - 1] .= ' …';
    }
    return implode('', array_map(static fn (string $paragraph) => '<p>' . $paragraph . '</p>', $paragraphs));
  }

  /**
   * Names the author of a revision with the role, as the logbook does.
   *
   * @return array{author: string, author_role: string, author_name: string, author_id: int|null}
   *   The role and name together, each of them, and the user ID if any.
   */
  protected function author(ProjectInterface $revision): array {

    $user = $revision->getRevisionUser();
    if (!$user instanceof UserInterface || $user->isAnonymous()) {
      return [
        'author' => static::ROLE_WORDS['platform'],
        'author_role' => static::ROLE_WORDS['platform'],
        'author_name' => '',
        'author_id' => NULL,
      ];
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

    // The user types name their people; other accounts have a display name.
    $name = $user instanceof Organization || $user instanceof Creative ? $user->getName() : $user->getDisplayName();
    return [
      'author' => static::ROLE_WORDS[$role] . ' ' . $name,
      'author_role' => static::ROLE_WORDS[$role],
      'author_name' => $name,
      'author_id' => (int) $user->id(),
    ];
  }

}
