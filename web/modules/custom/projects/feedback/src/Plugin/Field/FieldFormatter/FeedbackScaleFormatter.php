<?php

namespace Drupal\feedback\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'feedback_scale' formatter.
 *
 * The feedback stores the answers of a scale as integers from 1 to the number
 * of options. This formatter shows the answer as a row of segments, filled up
 * to the answer, followed by the value and its label, as the frontend shows
 * them, for example "4 eher zufrieden".
 *
 * @FieldFormatter(
 *   id = "feedback_scale",
 *   label = @Translation("Feedback scale"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class FeedbackScaleFormatter extends FeedbackOptionsFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $options = $this->getOptions();
    $max = $options ? max(array_keys($options)) : 5;
    $elements = [];
    foreach ($items as $delta => $item) {
      $value = (int) $item->get('value')->getValue();
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '<span class="feedback-scale" role="img" aria-label="{{ aria }}"><span class="feedback-scale__steps" aria-hidden="true">{% for step in 1..max %}<span class="feedback-scale__step{{ step <= value ? " is-filled" }}"></span>{% endfor %}</span><span class="feedback-scale__value" aria-hidden="true">{{ value }}</span>{% if label %}<span class="feedback-scale__label" aria-hidden="true">{{ label }}</span>{% endif %}</span>',
        '#context' => [
          'value' => $value,
          'max' => $max,
          'label' => $options[$value] ?? '',
          'aria' => $this->t('@value of @max: @label', [
            '@value' => $value,
            '@max' => $max,
            '@label' => $options[$value] ?? '',
          ]),
        ],
      ];
    }
    return $elements;
  }

}
