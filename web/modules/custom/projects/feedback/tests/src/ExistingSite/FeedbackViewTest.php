<?php

namespace Drupal\Tests\feedback\ExistingSite;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the admin page of a feedback with the displays of the site.
 */
#[Group('feedback')]
class FeedbackViewTest extends ProjectResourceTestBase {

  /**
   * Creates a feedback of a creative or an organization with the answers.
   *
   * The step stays below the final step, so the feedback is not completed.
   */
  protected function createFeedback(array $values, string $bundle = 'creative'): FeedbackInterface {
    $project = $this->createProject(ProjectState::Completed);
    $author = $bundle === 'organization' ? $project->getOwner() : $this->createCreative();
    $author->set('field_name', 'Theo Lechner')->save();
    $feedback = Feedback::create($values + [
      'bundle' => $bundle,
      'project' => $project->id(),
      'author' => $author->id(),
      'step' => 1,
    ]);
    $feedback->save();
    $this->markEntityForCleanup($feedback);
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
   */
  public function testAnswersByStep(): void {

    $html = $this->renderFeedback($this->createFeedback([
      'field_service_scale' => 4,
      'field_service_text' => 'Schneller antworten.',
      'field_recommend_boolean' => TRUE,
      'project_comment' => 'Ein tolles Projekt.',
    ]));

    $this->assertStringContainsString('feedback-comment', $html);
    $this->assertStringContainsString('Ein tolles Projekt.', $html);
    // The question is shown as the description of the field.
    $this->assertStringContainsString('Wie zufrieden bist Du mit der Betreuung durch youvo?', $html);
    $this->assertStringContainsString((string) new TranslatableMarkup('Offer'), $html);
    // The text follows its scale as a follow-up.
    $question = preg_quote('Was kann youvo Deiner Meinung nach tun, um eine bessere Betreuung sicherzustellen?', '/');
    $this->assertMatchesRegularExpression('/data-follow-up="true"[^>]*>\s*<div class="feedback-question">' . $question . '/', $html);
    $this->assertStringContainsString('Schneller antworten.', $html);
    $this->assertStringContainsString('data-answer="yes"', $html);
    $this->assertStringNotContainsString((string) new TranslatableMarkup('Further answers'), $html);
  }

  /**
   * A field that no step knows ends up in the last section.
   *
   * The organization steps do not ask whether to recommend youvo, but the
   * display of the site shows the field.
   */
  public function testFurtherAnswers(): void {

    $html = $this->renderFeedback($this->createFeedback([
      'field_satisfied_scale' => 4,
      'field_recommend_boolean' => TRUE,
    ], 'organization'));

    $offer = strpos($html, (string) new TranslatableMarkup('Offer'));
    $further = strpos($html, (string) new TranslatableMarkup('Further answers'));
    $this->assertNotFalse($offer);
    $this->assertNotFalse($further);
    $this->assertLessThan($further, $offer);
    $this->assertGreaterThan($further, strpos($html, 'Würdest Du youvo weiterempfehlen?'));
  }

  /**
   * Empty answers and empty steps are not shown.
   */
  public function testEmptyAnswersAreLeftOut(): void {
    $html = $this->renderFeedback($this->createFeedback(['field_service_scale' => 2]));
    $this->assertStringContainsString((string) new TranslatableMarkup('Offer'), $html);
    $this->assertStringNotContainsString((string) new TranslatableMarkup('Collaboration'), $html);
    $this->assertStringNotContainsString('data-follow-up', $html);
    $this->assertStringNotContainsString((string) new TranslatableMarkup('Further answers'), $html);
    $this->assertStringNotContainsString('feedback-comment', $html);
  }

  /**
   * A scale shows its segments, the value and the label of the value.
   */
  public function testScale(): void {
    $html = $this->renderFeedback($this->createFeedback(['field_service_scale' => 4]));
    $this->assertSame(5, substr_count($html, 'feedback-scale-step"'));
    $this->assertSame(4, substr_count($html, 'feedback-scale-step" data-filled'));
    $this->assertStringContainsString('<span class="feedback-scale-value" aria-hidden="true">4</span>', $html);
    $this->assertStringContainsString('eher zufrieden', $html);
    $this->assertStringContainsString('aria-label="4 of 5: eher zufrieden"', $html);
  }

  /**
   * The feedback is titled with the person who gave it.
   */
  public function testLabel(): void {
    $feedback = $this->createFeedback([]);
    $this->assertSame('Feedback by Theo Lechner', (string) $feedback->label());
    $this->assertStringContainsString('Theo Lechner', $this->renderFeedback($feedback));
  }

}
