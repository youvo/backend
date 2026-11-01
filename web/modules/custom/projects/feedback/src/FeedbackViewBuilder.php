<?php

namespace Drupal\feedback;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;

/**
 * View builder for the feedback entity.
 *
 * The admin page of a feedback shows the answers in the steps of the
 * frontend form, each answer next to its question. The display decides which
 * fields are shown and how; this builder only arranges them.
 */
class FeedbackViewBuilder extends EntityViewBuilder {

  /**
   * The steps of the frontend form per bundle, with their questions in order.
   *
   * A question may have follow-up questions, which the frontend only asks for
   * some answers, such as "Warum nicht?" after "Nein".
   */
  const SECTIONS = [
    'creative' => [
      'general' => [
        'field_satisfied_scale' => [],
        'field_service_scale' => ['field_service_text'],
        'field_recommend_boolean' => [],
        'field_next_boolean' => ['field_next_text'],
        'field_volunteer_boolean' => ['field_skilled_boolean'],
      ],
      'collaboration' => [
        'field_collaboration_scale' => ['field_collaboration_text'],
        'field_communication_scale' => [],
        'field_appreciation_scale' => [],
        'field_time_scale' => [],
        'field_organization_text' => [],
      ],
      'product' => [
        'field_handling_scale' => ['field_handling_text'],
        'field_features_text' => [],
        'field_followup_boolean' => [],
      ],
    ],
    'organization' => [
      'general' => [
        'field_satisfied_scale' => [],
        'field_challenge_boolean' => ['field_challenge_text'],
        'field_service_scale' => ['field_service_text', 'field_service_checkboxes'],
      ],
      'collaboration' => [
        'field_communication_scale' => [],
        'field_meet_boolean' => [],
        'field_channels_checkboxes' => [],
        'field_struggel_boolean' => [],
        'field_result_radios' => [],
        'field_volunteer_boolean' => [],
      ],
      'product' => [
        'field_handling_scale' => ['field_handling_text'],
        'field_features_text' => [],
        'field_difficulties_boolean' => ['field_difficulties_text'],
        'field_add_text' => [],
      ],
      'beyond' => [
        'field_knowledge_boolean' => [],
        'field_next_boolean' => ['field_next_text'],
        'field_digital_text' => [],
      ],
    ],
  ];

  /**
   * The base fields shown above the answers.
   */
  const array META_FIELDS = ['project', 'author', 'created', 'completed'];

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode): array {
    $build = parent::getBuildDefaults($entity, $view_mode);
    // The feedback has no entity template itself.
    unset($build['#theme']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {

    parent::alterBuild($build, $entity, $display, $view_mode);
    if (!$entity instanceof FeedbackInterface) {
      return;
    }
    $build['#attached']['library'][] = 'feedback/feedback';
    // Without an entity template, a container carries the class of the page.
    $build['#theme_wrappers'][] = 'container';
    $build['#attributes']['class'][] = 'feedback';

    // Who gave the feedback, for which project and when.
    $build['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feedback__meta']],
      '#weight' => -100,
    ];
    $titles = [
      'author' => new TranslatableMarkup('Author'),
      'created' => new TranslatableMarkup('Submitted'),
    ];
    foreach (self::META_FIELDS as $name) {
      if ($this->hasContent($build, $name)) {
        $build['meta'][$name] = $build[$name];
        $build['meta'][$name]['#title'] = $titles[$name] ?? $build[$name]['#title'] ?? '';
        $build['meta'][$name]['#label_display'] = 'inline';
      }
      unset($build[$name]);
    }
    // The author by name, with the type of feedback next to it.
    $owner = $entity->getOwner();
    if (isset($build['meta']['author'][0]) && ($owner instanceof Creative || $owner instanceof Organization)) {
      $build['meta']['author'][0] = $owner->toLink($owner->getName())->toRenderable();
    }
    $build['meta']['type'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $entity->get('bundle')->entity?->label(),
      '#attributes' => ['class' => ['feedback__type']],
      '#weight' => ($build['meta']['author']['#weight'] ?? 0) + 0.5,
    ];

    // The public comment for the project, as a quote.
    if ($this->hasContent($build, 'project_comment')) {
      $build['project_comment']['#label_display'] = 'visually_hidden';
      $build['comment'] = [
        '#type' => 'html_tag',
        '#tag' => 'figure',
        '#attributes' => ['class' => ['feedback__comment']],
        '#weight' => -90,
        'caption' => [
          '#type' => 'html_tag',
          '#tag' => 'figcaption',
          '#value' => new TranslatableMarkup('Public comment on the project'),
        ],
        'quote' => [
          '#type' => 'html_tag',
          '#tag' => 'blockquote',
          'field' => $build['project_comment'],
        ],
      ];
      unset($build['project_comment']);
    }

    // The answers, grouped by the steps of the frontend form. Answers that
    // no step knows end up in a last section.
    $sections = self::SECTIONS[$entity->bundle()] ?? [];
    $known = [];
    foreach ($sections as $questions) {
      foreach ($questions as $question => $follow_ups) {
        $known[] = $question;
        array_push($known, ...$follow_ups);
      }
    }
    $rest = [];
    foreach (Element::children($build) as $name) {
      if (str_starts_with($name, 'field_') && !in_array($name, $known, TRUE)) {
        $rest[$name] = [];
      }
    }
    if ($rest) {
      $sections['other'] = $rest;
    }

    $weight = 0;
    foreach ($sections as $section => $questions) {
      $rows = [];
      foreach ($questions as $question => $follow_ups) {
        if ($row = $this->buildRow($build, $entity, $question)) {
          $rows[] = $row;
        }
        foreach ($follow_ups as $follow_up) {
          if ($row = $this->buildRow($build, $entity, $follow_up, TRUE)) {
            $rows[] = $row;
          }
        }
      }
      if ($rows) {
        $build['section_' . $section] = [
          '#type' => 'html_tag',
          '#tag' => 'section',
          '#attributes' => ['class' => ['feedback__section']],
          '#weight' => $weight++,
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->sectionTitle($section),
            '#attributes' => ['class' => ['feedback__section-title']],
          ],
          'rows' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['feedback__rows']],
          ] + $rows,
        ];
      }
    }
  }

  /**
   * Moves a field of the build into a row next to its question.
   *
   * @return array|null
   *   The row, or NULL if the field has no answer.
   */
  protected function buildRow(array &$build, FeedbackInterface $entity, string $name, bool $follow_up = FALSE): ?array {
    if (!$this->hasContent($build, $name)) {
      unset($build[$name]);
      return NULL;
    }
    $field = $build[$name];
    unset($build[$name]);
    $field['#label_display'] = 'visually_hidden';

    $definition = $entity->getFieldDefinition($name);
    $question = (string) $definition?->getDescription() ?: (string) $definition?->getLabel();
    $classes = ['feedback__answer', 'feedback__answer--' . ($field['#formatter'] ?? 'default')];
    if ($definition?->getFieldStorageDefinition()->isMultiple()) {
      $classes[] = 'feedback__answer--multiple';
    }
    if ($definition?->getType() === 'boolean') {
      $classes[] = $entity->get($name)->value ? 'feedback__answer--yes' : 'feedback__answer--no';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => array_filter([
          'feedback__row',
          $follow_up ? 'feedback__row--follow-up' : NULL,
        ]),
      ],
      'question' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $question,
        '#attributes' => ['class' => ['feedback__question']],
      ],
      'answer' => [
        '#type' => 'container',
        '#attributes' => ['class' => $classes],
        'field' => $field,
      ],
    ];
  }

  /**
   * Whether a field of the build shows something.
   */
  protected function hasContent(array $build, string $name): bool {
    return isset($build[$name]) && Element::children($build[$name]) !== [];
  }

  /**
   * The title of a section, as the frontend names the step.
   */
  protected function sectionTitle(string $section): TranslatableMarkup {
    return match ($section) {
      'general' => new TranslatableMarkup('Offer'),
      'collaboration' => new TranslatableMarkup('Collaboration'),
      'product' => new TranslatableMarkup('Platform'),
      'beyond' => new TranslatableMarkup('Outlook'),
      default => new TranslatableMarkup('Further answers'),
    };
  }

}
