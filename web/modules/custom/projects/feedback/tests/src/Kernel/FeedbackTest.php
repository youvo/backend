<?php

namespace Drupal\Tests\feedback\Kernel;

use Drupal\creatives\Entity\Creative;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\Entity\FeedbackType;
use Drupal\feedback\Event\FeedbackCompleteEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the feedback completion and field access.
 *
 * @coversDefaultClass \Drupal\feedback\Entity\Feedback
 * @group feedback
 */
class FeedbackTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'feedback',
    'file',
    'link',
    'projects_result_test',
    'system',
  ];

  /**
   * The dispatched feedback complete events.
   *
   * @var \Drupal\feedback\Event\FeedbackCompleteEvent[]
   */
  protected array $completeEvents = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('feedback');
    $this->installEntitySchema('project_comment');
    $this->installConfig('projects_result_test');
    foreach (['creative', 'organization'] as $bundle) {
      FeedbackType::create(['id' => $bundle, 'label' => $bundle])->save();
    }
    $this->eventDispatcher->addListener(FeedbackCompleteEvent::class, function (FeedbackCompleteEvent $event): void {
      $this->completeEvents[] = $event;
    });
  }

  /**
   * Creates a saved feedback for a completed project.
   */
  protected function createFeedback(string $bundle = 'creative'): FeedbackInterface {
    $project = $this->createProject(ProjectState::Completed);
    $project->save();
    $author = $bundle === 'organization' ? $project->getOwner() : $this->createCreative();
    $feedback = Feedback::create([
      'bundle' => $bundle,
      'project' => $project->id(),
      'author' => $author->id(),
      'project_comment' => $this->randomString(),
    ]);
    $feedback->save();
    return $feedback;
  }

  /**
   * Reloads the project of the feedback to read the stored comments.
   */
  protected function reloadProject(FeedbackInterface $feedback): ProjectInterface {
    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $this->container->get('entity_type.manager')
      ->getStorage('project')
      ->loadUnchanged($feedback->getProject()->id());
    return $project;
  }

  /**
   * Tests that a feedback below the final step is not completed.
   *
   * @covers ::preSave
   * @covers ::isFinalStep
   */
  public function testFeedbackNotCompletedBeforeFinalStep(): void {

    $feedback = $this->createFeedback();
    $feedback->set('step', 3)->save();

    $this->assertFalse($feedback->isCompleted());
    $this->assertEmpty($this->reloadProject($feedback)->getResult()->getComments());
    $this->assertEmpty($this->completeEvents);
  }

  /**
   * Tests that a creative feedback is completed at step 4.
   *
   * @covers ::preSave
   * @covers ::isFinalStep
   * @covers ::complete
   * @covers ::isCompleted
   */
  public function testCreativeFeedbackCompleted(): void {

    $feedback = $this->createFeedback();
    $feedback->set('step', 4)->save();

    $this->assertTrue($feedback->isCompleted());
    $this->assertCount(1, $this->completeEvents);

    $comments = $this->reloadProject($feedback)->getResult()->getComments();
    $this->assertCount(1, $comments);
    $comment = reset($comments);
    $this->assertEquals($feedback->getOwnerId(), $comment->getOwnerId());
    $this->assertEquals($feedback->get('project_comment')->value, $comment->getComment());

    // Saving the completed feedback again does not add another comment.
    $feedback->set('step', 5)->save();
    $this->assertCount(1, $this->reloadProject($feedback)->getResult()->getComments());
    $this->assertCount(1, $this->completeEvents);
  }

  /**
   * Tests that an organization feedback is completed at step 5.
   *
   * @covers ::preSave
   * @covers ::isFinalStep
   */
  public function testOrganizationFeedbackCompleted(): void {

    $feedback = $this->createFeedback('organization');
    $feedback->set('step', 4)->save();
    $this->assertFalse($feedback->isCompleted());

    $feedback->set('step', 5)->save();
    $this->assertTrue($feedback->isCompleted());

    $comments = $this->reloadProject($feedback)->getResult()->getComments();
    $this->assertCount(1, $comments);
    $this->assertEquals($feedback->getOwnerId(), reset($comments)->getOwnerId());
  }

  /**
   * Tests that the author may not edit the fields set by the backend.
   *
   * @covers \Drupal\feedback\Access\FeedbackFieldAccess::checkFieldAccess
   */
  public function testFieldEditAccess(): void {

    $feedback = $this->createFeedback();
    $author = $feedback->getOwner();

    foreach (['author', 'completed', 'created', 'project'] as $field_name) {
      $this->assertFalse($feedback->get($field_name)->access('edit', $author), "The author may edit $field_name.");
      $this->assertTrue($feedback->get($field_name)->access('view', $author), "The author may not view $field_name.");
    }
    foreach (['project_comment', 'step'] as $field_name) {
      $this->assertTrue($feedback->get($field_name)->access('edit', $author), "The author may not edit $field_name.");
    }

    $role = Role::create(['id' => 'feedback_admin', 'label' => 'Feedback admin']);
    $role->grantPermission('administer feedback types');
    $role->save();
    $admin = Creative::create(['name' => $this->randomString()]);
    $admin->addRole('feedback_admin');
    $admin->save();
    $this->assertTrue($feedback->get('project')->access('edit', $admin));
  }

}
