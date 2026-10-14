<?php

namespace Drupal\logbook\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\logbook\LogInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Shows the text of a log with its links and its message.
 */
#[ViewsField('log_event_text')]
class LogEventText extends FieldPluginBase {

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
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['logbook-event']],
      'text' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['logbook-event__text']],
        'markup' => ['#markup' => $log->getMarkup()],
      ],
    ];
    if ($message = $log->buildMessage()) {
      $build['message'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['logbook-event__message']],
        'content' => $message,
      ];
    }
    return $this->getRenderer()->render($build);
  }

}
