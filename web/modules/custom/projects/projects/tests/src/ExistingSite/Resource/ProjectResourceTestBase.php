<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\RequestTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a test base for project resources.
 */
abstract class ProjectResourceTestBase extends ExistingSiteBase implements EventSubscriberInterface {

  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    // Only listen to project events.
    $event_dispatcher = $this->container->get('event_dispatcher');
    foreach ($event_dispatcher->getListeners() as $event => $listeners) {
      if (str_contains($event, 'projects')) {
        foreach ($listeners as $listener) {
          if (!str_contains($listener[0]::class, 'projects')) {
            $event_dispatcher->removeSubscriber($listener[0]);
          }
        }
      }
    }

    // Add the test as an event subscriber for specific test cases.
    $event_dispatcher->addSubscriber($this);
  }

  /**
   * Creates a minimal project for testing purposes.
   */
  protected function createProject(ProjectState $state = ProjectState::DRAFT, string $role = 'organization'): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomString(),
      'mail' => 'manager@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomString(),
      'mail' => 'test@example.org',
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomString(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);

    $project_result = $project->getResult();
    $this->markEntityForCleanup($project_result);

    return $project;
  }

  /**
   * Creates a creative for testing purposes.
   */
  protected function createCreative(string $role = 'creative'): Creative {
    $creative = Creative::create([
      'name' => $this->randomString(),
      'mail' => 'test@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $creative->addRole($role);
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Creates a manager for testing purposes.
   */
  protected function createManager(): Creative {
    $manager = Creative::create([
      'name' => $this->randomString(),
      'mail' => 'other_manager@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);
    return $manager;
  }

  /**
   * Creates a creative for testing purposes.
   */
  protected function createSupervisor(): Creative {
    $supervisor = Creative::create([
      'name' => $this->randomString(),
      'mail' => 'supervisor@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $supervisor->addRole('supervisor');
    $supervisor->save();
    $this->markEntityForCleanup($supervisor);
    return $supervisor;
  }

  /**
   * Creates a organization for testing purposes.
   */
  protected function createOrganization(): Organization {
    $organization = Organization::create([
      'name' => $this->randomString(),
      'mail' => 'other@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Listens to the project events to throw an exception for testing purposes.
   *
   * @throws \LogicException
   */
  public function onProjectEvent(Event $event): void {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ProjectApplyEvent::class => 'onProjectEvent',
      ProjectCompleteEvent::class => 'onProjectEvent',
      ProjectMediateEvent::class => 'onProjectEvent',
      ProjectPublishEvent::class => 'onProjectEvent',
      ProjectResetEvent::class => 'onProjectEvent',
      ProjectSubmitEvent::class => 'onProjectEvent',
    ];
  }

}
