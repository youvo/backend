<?php

namespace Drupal\Tests\correspondence\Kernel;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the correspondence entity.
 *
 * @group correspondence
 */
class CorrespondenceTest extends ProjectEventSubscriberTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'filter',
    'text',
    'system',
    'correspondence',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('correspondence');
    $this->installConfig(['correspondence']);
  }

  /**
   * A mail correspondence belongs to a project and is deleted with it.
   */
  public function testMailBundleChildOfProject(): void {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $entry = Correspondence::create([
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'Hallo',
      'body' => ['value' => '<p>Text</p>', 'format' => 'email_html'],
      'recipients' => json_encode(['a@example.com']),
      'subtype' => 'mailer.project_transition.publish_organization',
      'origin' => 'automatic',
      'result' => 'sent',
    ]);
    $entry->save();
    $this->assertSame($project->id(), $entry->getParentEntity()->id());
    $project->delete();
    $this->assertNull(Correspondence::load($entry->id()));
  }

  /**
   * Creates a saved mail entry for a new open project.
   */
  protected function createEntry(ProjectState $state = ProjectState::Open): Correspondence {
    $project = $this->createProject($state);
    $project->save();
    $entry = Correspondence::create([
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'Hallo',
      'subtype' => 'mailer.project_transition.publish_organization',
    ]);
    $entry->save();
    return $entry;
  }

  /**
   * Viewing requires the view permission and access to the project.
   */
  public function testViewAccess(): void {
    $entry = $this->createEntry();
    // Everyone may view an open project, but not its correspondence.
    $project_only = $this->createUser();
    $this->assertTrue($entry->getParentEntity()->access('view', $project_only));
    $this->assertFalse($entry->access('view', $project_only));

    $viewer = $this->createUser(['view correspondence']);
    $this->assertTrue($entry->access('view', $viewer));

    // The permission alone does not help without access to the project.
    $draft_entry = $this->createEntry(ProjectState::Draft);
    $this->assertFalse($draft_entry->getParentEntity()->access('view', $viewer));
    $this->assertFalse($draft_entry->access('view', $viewer));

    $this->assertFalse($entry->access('view', new AnonymousUserSession()));
  }

  /**
   * Update, delete and create require the administer permission.
   */
  public function testAdministerAccess(): void {
    $entry = $this->createEntry();
    $viewer = $this->createUser(['view correspondence']);
    $admin = $this->createUser(['administer correspondence']);
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('correspondence');

    foreach (['update', 'delete'] as $operation) {
      $this->assertFalse($entry->access($operation, $viewer));
      $this->assertTrue($entry->access($operation, $admin));
      $this->assertFalse($entry->access($operation, new AnonymousUserSession()));
    }
    $this->assertFalse($handler->createAccess('mail', $viewer));
    $this->assertTrue($handler->createAccess('mail', $admin));
  }

  /**
   * Access results carry the permissions cache context.
   */
  public function testAccessCacheContexts(): void {
    $entry = $this->createEntry();
    $result = $entry->access('view', $this->createUser(['view correspondence']), TRUE);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertContains('correspondence:' . $entry->id(), $result->getCacheTags());
  }

}
