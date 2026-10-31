<?php

namespace Drupal\Tests\feedback\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\Entity\FeedbackType;
use Drupal\feedback\FeedbackInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Tests the admin page of a feedback.
 *
 * @coversDefaultClass \Drupal\feedback\FeedbackViewBuilder
 * @group feedback
 */
class FeedbackViewTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'feedback',
    'file',
    'language',
    'link',
    'projects_result_test',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('feedback');
    $this->installConfig(['system', 'projects_result_test']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    FeedbackType::create(['id' => 'creative', 'label' => 'Creative'])->save();

    // The title reads the name of the creative.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_name',
      'entity_type' => 'user',
      'type' => 'string',
    ]);
    $storage->save();
    FieldConfig::create(['field_storage' => $storage, 'bundle' => 'user'])->save();

    // A scale with a follow-up text, a yes/no question and a field that no
    // step of the frontend knows.
    $fields = [
      'field_service_scale' => ['integer', 'Wie zufrieden bist Du mit der Betreuung durch youvo?'],
      'field_service_text' => ['string_long', 'Was kann youvo besser machen?'],
      'field_recommend_boolean' => ['boolean', 'Würdest Du youvo weiterempfehlen?'],
      'field_unknown_text' => ['string_long', 'Eine Frage ohne Schritt?'],
    ];
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'feedback',
      'bundle' => 'creative',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    foreach ($fields as $name => [$type, $question]) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'feedback',
        'type' => $type,
      ])->save();
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'feedback',
        'bundle' => 'creative',
        'label' => $name,
        'description' => $question,
      ])->save();
      $display->setComponent($name, match ($type) {
        'integer' => [
          'type' => 'feedback_scale',
          'label' => 'above',
          'settings' => [
            'options' => array_map(
              fn ($value) => ['value' => $value, 'label' => 'label ' . $value],
              range(1, 5),
            ),
          ],
        ],
        'boolean' => ['type' => 'boolean', 'label' => 'above'],
        default => ['type' => 'basic_string', 'label' => 'above'],
      });
    }
    $display->setComponent('project_comment', ['type' => 'basic_string', 'label' => 'above']);
    $display->setComponent('author', ['type' => 'entity_reference_label', 'label' => 'inline']);
    $display->save();
  }

  /**
   * Creates a feedback of a creative with the given answers.
   */
  protected function createFeedback(array $values): FeedbackInterface {
    $project = $this->createProject(ProjectState::Completed);
    $project->save();
    $creative = $this->createCreative();
    $creative->set('field_name', 'Theo Lechner')->save();
    $feedback = Feedback::create($values + [
      'bundle' => 'creative',
      'project' => $project->id(),
      'author' => $creative->id(),
    ]);
    $feedback->save();
    return $feedback;
  }

  /**
   * Renders the full view of a feedback.
   */
  protected function renderFeedback(FeedbackInterface $feedback): string {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('feedback')
      ->view($feedback);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * The answers are grouped by step, each next to its question.
   *
   * @covers ::alterBuild
   */
  public function testAnswersByStep(): void {
    $feedback = $this->createFeedback([
      'field_service_scale' => 4,
      'field_service_text' => 'Schneller antworten.',
      'field_recommend_boolean' => TRUE,
      'field_unknown_text' => 'Irgendwas.',
      'project_comment' => 'Ein tolles Projekt.',
    ]);
    $html = $this->renderFeedback($feedback);

    $this->assertStringContainsString('feedback__comment', $html);
    $this->assertStringContainsString('Ein tolles Projekt.', $html);
    // The question is shown, not the technical label of the field.
    $this->assertStringContainsString('Wie zufrieden bist Du mit der Betreuung durch youvo?', $html);
    $this->assertStringContainsString('Offer', $html);
    // The text follows its scale as a follow-up.
    $this->assertMatchesRegularExpression('/feedback__row--follow-up[^>]*>\s*<div class="feedback__question">Was kann youvo besser machen\?/', $html);
    $this->assertStringContainsString('feedback__answer--yes', $html);
    // A field outside the steps ends up in the last section.
    $this->assertStringContainsString('Further answers', $html);
    $this->assertLessThan(strpos($html, 'Further answers'), strpos($html, 'Offer'));
  }

  /**
   * Empty answers and empty steps are not shown.
   *
   * @covers ::alterBuild
   */
  public function testEmptyAnswersAreLeftOut(): void {
    $html = $this->renderFeedback($this->createFeedback(['field_service_scale' => 2]));
    $this->assertStringContainsString('Offer', $html);
    $this->assertStringNotContainsString('feedback__row--follow-up', $html);
    $this->assertStringNotContainsString('Further answers', $html);
    $this->assertStringNotContainsString('feedback__comment', $html);
  }

  /**
   * A scale shows its segments, the value and the label of the value.
   *
   * @covers \Drupal\feedback\Plugin\Field\FieldFormatter\FeedbackScaleFormatter::viewElements
   */
  public function testScale(): void {
    $html = $this->renderFeedback($this->createFeedback(['field_service_scale' => 4]));
    $this->assertSame(5, substr_count($html, 'feedback-scale__step"') + substr_count($html, 'feedback-scale__step is-filled'));
    $this->assertSame(4, substr_count($html, 'feedback-scale__step is-filled'));
    $this->assertStringContainsString('<span class="feedback-scale__value" aria-hidden="true">4</span>', $html);
    $this->assertStringContainsString('label 4', $html);
    $this->assertStringContainsString('aria-label="4 of 5: label 4"', $html);
  }

  /**
   * The feedback is titled with the person who gave it.
   *
   * @covers \Drupal\feedback\Entity\Feedback::label
   */
  public function testLabel(): void {
    $feedback = $this->createFeedback([]);
    $this->assertSame('Feedback by Theo Lechner', (string) $feedback->label());
    $this->assertStringContainsString('Theo Lechner', $this->renderFeedback($feedback));
  }

}
