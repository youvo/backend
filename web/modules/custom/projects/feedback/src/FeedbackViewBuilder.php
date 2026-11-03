<?php

namespace Drupal\feedback;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The entity type manager, to find the other feedback on the project.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter, for the dates of the other feedback.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

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
      'details' => [
        '#type' => 'html_tag',
        '#tag' => 'section',
        '#attributes' => ['class' => ['feedback-side-section']],
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
        $build['side']['details']['facts'][$name] = [
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

    // As on the profiles, a card opens the main column: the title of the
    // page, then the public comment.
    $build['main']['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => ['class' => ['feedback-intro']],
      '#weight' => -95,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        'text' => ['#plain_text' => $entity->label()],
        '#attributes' => ['class' => ['feedback-title']],
      ],
    ];

    $project = $entity->get('project')->entity;
    if ($project instanceof ProjectInterface) {
      $this->buildOtherFeedback($build, $entity, $project);
    }

    // The public comment for the project, as a quote.
    if ($this->hasContent($build, 'project_comment')) {
      $build['project_comment']['#label_display'] = 'visually_hidden';
      $build['main']['intro']['comment'] = [
        '#type' => 'html_tag',
        '#tag' => 'figure',
        '#attributes' => ['class' => ['feedback-comment']],
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
   * Lists the other completed feedback on the project in the sidebar.
   *
   * As in the feedback list of the profiles: the author, and the date as a
   * link to each feedback the viewer may open.
   */
  protected function buildOtherFeedback(array &$build, FeedbackInterface $entity, ProjectInterface $project): void {

    $cache = CacheableMetadata::createFromRenderArray($build)->addCacheTags(['feedback_list']);
    $storage = $this->entityTypeManager->getStorage('feedback');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->condition('id', $entity->id(), '<>')
      ->condition('completed', NULL, 'IS NOT NULL')
      ->sort('completed', 'DESC')
      ->execute();

    $items = [];
    /** @var \Drupal\feedback\FeedbackInterface $other */
    foreach ($storage->loadMultiple($ids) as $id => $other) {
      $author = $other->getOwner();
      $access = $other->access('view', NULL, TRUE);
      $cache->addCacheableDependency($access)->addCacheableDependency($other)->addCacheableDependency($author);
      $name = $author instanceof Creative || $author instanceof Organization ? $author->getName() : (string) $other->label();
      $date = $this->dateFormatter->format((int) $other->get('completed')->value, 'custom', 'd.m.Y');
      $value = ['#plain_text' => $date];
      if ($access->isAllowed()) {
        $value = [
          '#type' => 'link',
          '#title' => [
            'date' => ['#plain_text' => $date],
            'context' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => ': ' . new TranslatableMarkup('feedback by @name', ['@name' => $name]),
              '#attributes' => ['class' => ['visually-hidden']],
            ],
          ],
          '#url' => $other->toUrl(),
        ];
      }
      $items[$id] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'dt',
          'text' => ['#plain_text' => $name],
        ],
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'dd',
          'value' => $value,
        ],
      ];
    }
    $cache->applyTo($build);
    if (!$items) {
      return;
    }

    $build['side']['other'] = [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => ['class' => ['feedback-side-section']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => new TranslatableMarkup('Other feedback on this project'),
        '#attributes' => ['class' => ['feedback-side-title']],
      ],
      'facts' => [
        '#type' => 'html_tag',
        '#tag' => 'dl',
        '#attributes' => ['class' => ['feedback-facts']],
      ] + $items,
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
