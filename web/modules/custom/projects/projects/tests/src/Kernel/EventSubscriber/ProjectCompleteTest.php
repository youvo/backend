<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;

/**
 * Tests for the project complete event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Transition\ProjectCompleteSubscriber
 * @group projects
 */
class ProjectCompleteTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'projects_result_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('projects_result_test');
  }

  /**
   * Tests the project complete event listener.
   *
   * @covers ::onProjectComplete
   * @covers ::getSubscribedEvents
   */
  public function testProjectComplete(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $this->assertTrue($project->lifecycle()->isOngoing());

    $creative = $this->createCreative();
    $project->appendParticipant($creative);
    $this->assertTrue($project->hasParticipant('Creative'));

    $event = new ProjectCompleteEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertTrue($project->lifecycle()->isCompleted());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::Complete->value, $last->transition);
  }

  /**
   * The project stays ongoing if its results cannot be saved.
   */
  public function testProjectCompleteRollsBack(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $project->appendParticipant($this->createCreative());
    $project->save();
    // Without a result, saving the results fails after the project save.
    $project->set('project_result', NULL);

    try {
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
      $this->fail('The complete event throws without a result.');
    }
    catch (EntityStorageException) {
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('project');
    $storage->resetCache([$project->id()]);
    $this->assertTrue($storage->load($project->id())->lifecycle()->isOngoing());
  }

}
