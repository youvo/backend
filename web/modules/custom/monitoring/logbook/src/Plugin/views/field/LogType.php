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
 * Shows the log pattern as a pill, colored by the tone of the pattern.
 */
#[ViewsField('log_type')]
class LogType extends FieldPluginBase {

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
    $classes = 'logbook-type';
    if ($tone = $pattern->getTone()) {
      $classes .= ' logbook-type--' . Html::cleanCssIdentifier($tone);
    }
    $label = trim(str_replace('Project ', '', (string) $pattern->label()));
    return Markup::create('<span class="' . $classes . '">' . Html::escape($label) . '</span>');
  }

}
