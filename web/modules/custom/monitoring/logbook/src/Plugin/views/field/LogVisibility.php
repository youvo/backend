<?php

namespace Drupal\logbook\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\logbook\LogInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Shows who can see a log as an icon.
 *
 * A lock for internal logs, a globe for public ones and a star for promoted
 * ones, which are public as well.
 */
#[ViewsField('log_visibility')]
class LogVisibility extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // The value comes from the entity. Nothing to add to the query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {
    $log = $values->_entity;
    if (!$log instanceof LogInterface) {
      return '';
    }
    $pattern = $log->getPattern();
    // One icon: a promoted log is public by definition, so the star stands for
    // both and the globe would be redundant.
    if (!$pattern->isPublic()) {
      $icon = $this->icon('lock', $this->t('Internal: only managers of the organization'));
    }
    elseif ($pattern->isPromoted()) {
      $icon = $this->icon('star', $this->t('Public and promoted: highlighted in the site-wide logbook'));
    }
    else {
      $icon = $this->icon('globe', $this->t('Public: visible to signed-in users'));
    }
    return Markup::create('<span class="logbook-visibility">' . $icon . '</span>');
  }

  /**
   * Builds the markup of one icon.
   */
  protected function icon(string $name, string|\Stringable $label): string {
    $label = Html::escape((string) $label);
    return '<span class="logbook-icon logbook-icon--' . $name . '" role="img" aria-label="' . $label . '" title="' . $label . '"></span>';
  }

}
