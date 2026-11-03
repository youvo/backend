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
    // Without an entity template, a container carries the class of the page.
    $build['#theme_wrappers'][] = 'container';
    $build['#attributes']['class'][] = 'feedback';

    // As on the profiles: the answers in the main column, who gave the
    // feedback, for which project and when in a sidebar. The sidebar comes
    // first, so small screens show it above the answers.
    $build['side'] = [
      '#type' => 'html_tag',
      '#tag' => 'aside',
      '#attributes' => ['class' => ['feedback-side']],
      '#weight' => -100,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => new TranslatableMarkup('Details'),
        '#attributes' => ['class' => ['feedback-side-title']],
      ],
      'facts' => [
        '#type' => 'html_tag',
        '#tag' => 'dl',
        '#attributes' => ['class' => ['feedback-facts']],
      ],
    ];
    $build['main'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feedback-main']],
    ];

    // The author by name.
    $owner = $entity->getOwner();
    if (isset($build['author'][0]) && ($owner instanceof Creative || $owner instanceof Organization)) {
      $build['author'][0] = $owner->toLink($owner->getName())->toRenderable();
    }
    $facts = [
      'project' => NULL,
      'author' => new TranslatableMarkup('Author'),
      'type' => new TranslatableMarkup('Type'),
      'created' => new TranslatableMarkup('Submitted'),
      'completed' => NULL,
    ];
    $build['type'] = [
      '#plain_text' => $entity->get('bundle')->entity?->label(),
    ];
    foreach ($facts as $name => $title) {
      if ($name === 'type' || $this->hasContent($build, $name)) {
        $value = $build[$name];
        $value['#label_display'] = 'hidden';
        $build['side']['facts'][$name] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'dt',
            '#value' => $title ?? $build[$name]['#title'] ?? '',
          ],
          'value' => [
            '#type' => 'html_tag',
            '#tag' => 'dd',
            'value' => $value,
          ],
        ];
      }
      unset($build[$name]);
    }

    // The public comment for the project, as a quote.
    if ($this->hasContent($build, 'project_comment')) {
      $build['project_comment']['#label_display'] = 'visually_hidden';
      $build['main']['comment'] = [
        '#type' => 'html_tag',
        '#tag' => 'figure',
        '#attributes' => ['class' => ['feedback-comment']],
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
        $build['main']['section_' . $section] = [
          '#type' => 'html_tag',
          '#tag' => 'section',
          '#attributes' => [
            'class' => ['feedback-section'],
            'aria-label' => $this->sectionTitle($section),
          ],
          '#weight' => $weight++,
          'rows' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['feedback-rows']],
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
    $row = ['class' => ['feedback-row']];
    if ($follow_up) {
      $row['data-follow-up'] = 'true';
    }
    $answer = [
      'class' => ['feedback-answer'],
      'data-format' => $field['#formatter'] ?? 'default',
    ];
    if ($definition?->getFieldStorageDefinition()->isMultiple()) {
      $answer['data-multiple'] = 'true';
    }
    if ($definition?->getType() === 'boolean') {
      $answer['data-answer'] = $entity->get($name)->value ? 'yes' : 'no';
    }

    return [
      '#type' => 'container',
      '#attributes' => $row,
      'question' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $question,
        '#attributes' => ['class' => ['feedback-question']],
      ],
      'answer' => [
        '#type' => 'container',
        '#attributes' => $answer,
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
