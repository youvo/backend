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
 * Shows who can see a log as icons: lock (internal), globe (public), star.
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
    $icons = [];
    if (!$pattern->isPublic()) {
      $icons[] = $this->icon('lock', $this->t('Internal: only managers of the organization'));
    }
    else {
      $icons[] = $this->icon('globe', $this->t('Public: visible to signed-in users'));
      if ($pattern->isPromoted()) {
        $icons[] = $this->icon('star', $this->t('Promoted: highlighted in the site-wide logbook'));
      }
    }
    return Markup::create('<span class="logbook-visibility">' . implode('', $icons) . '</span>');
  }

  /**
   * Builds the markup of one icon.
   */
  protected function icon(string $name, string|\Stringable $label): string {
    $label = Html::escape((string) $label);
    return '<span class="logbook-icon logbook-icon--' . $name . '" role="img" aria-label="' . $label . '" title="' . $label . '"></span>';
  }

}
