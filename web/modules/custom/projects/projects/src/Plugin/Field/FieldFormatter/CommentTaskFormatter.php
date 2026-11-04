<?php

namespace Drupal\projects\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Names the task of a comment's author, as the participants are named.
 *
 * The task is computed in lowercase: creative, manager or organization.
 */
#[FieldFormatter(
  id: 'project_comment_task',
  label: new TranslatableMarkup('Comment task'),
  field_types: ['cacheable_string'],
)]
class CommentTaskFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $task = (string) $item->get('value')->getValue();
      $label = match ($task) {
        'creative' => $this->t('Creative'),
        'manager' => $this->t('Manager'),
        'organization' => $this->t('Organization'),
        default => $task,
      };
      if ($label !== '') {
        $elements[$delta] = ['#plain_text' => (string) $label];
      }
    }
    return $elements;
  }

}
