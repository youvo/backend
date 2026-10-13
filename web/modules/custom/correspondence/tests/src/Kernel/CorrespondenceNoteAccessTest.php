<?php

namespace Drupal\Tests\correspondence\Kernel;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests the note bundle and its access rules.
 *
 * @group correspondence
 */
class CorrespondenceNoteAccessTest extends ProjectEventSubscriberTestBase {

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
    // User 1 bypasses all permission checks: use it up.
    $this->createCreative();
  }

  /**
   * Creates a saved note on a new project and returns it.
   */
  protected function createNote(ProjectState $state = ProjectState::Open): Correspondence {
    $project = $this->createProject($state);
    $project->save();
    $note = Correspondence::create([
      'type' => 'note',
      'project' => $project->id(),
      'subject' => 'Anruf',
      'body' => ['value' => 'Text', 'format' => 'plain_text'],
      'occurred' => 1700000000,
    ]);
    $note->save();
    return $note;
  }

  /**
   * Gets the project of an entry.
   */
  protected function projectOf(Correspondence $entry): ProjectInterface {
    $project = $entry->getParentEntity();
    $this->assertInstanceOf(ProjectInterface::class, $project);
    return $project;
  }

  /**
   * Creates a mail entry on the project of the entry.
   */
  protected function createMail(Correspondence $note): Correspondence {
    $mail = Correspondence::create(['type' => 'mail', 'project' => $note->get('project')->target_id, 'subject' => 'M']);
    $mail->save();
    return $mail;
  }

  /**
   * Gives a user the permissions through a new role and returns a fresh copy.
   *
   * User entities memoize permission checks, so every case needs its own.
   */
  protected function withPermissions(UserInterface $user, array $permissions): UserInterface {
    $user->addRole($this->createRole($permissions));
    $user->save();
    return $user;
  }

  /**
   * Gets the manager of the project of the entry, with the permissions.
   */
  protected function managerOf(Correspondence $entry, array $permissions): UserInterface {
    $manager = $this->projectOf($entry)->getOwner()->getManager();
    return $this->withPermissions($manager, $permissions);
  }

  /**
   * Gets a manager of another organization, with the permissions.
   */
  protected function otherManager(array $permissions): UserInterface {
    $other = $this->createProject(ProjectState::Open);
    $other->save();
    return $this->withPermissions($other->getOwner()->getManager(), $permissions);
  }

  /**
   * Notes are manageable with their own permission; mails stay admin-only.
   */
  public function testNoteAccess(): void {
    $note = $this->createNote();
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('correspondence');
    $user = $this->managerOf($note, ['view correspondence', 'manage correspondence notes', 'access content']);
    $this->assertTrue($note->access('update', $user));
    $this->assertTrue($note->access('delete', $user));
    $this->assertTrue($handler->createAccess('note', $user));
    $this->assertFalse($handler->createAccess('mail', $user));
    $this->assertTrue($note->isNote());
    $this->assertSame(1700000000, $note->getOccurredTime());

    // The assigned manager with only the view permission cannot edit.
    $own = $this->createNote();
    $viewer = $this->managerOf($own, ['view correspondence']);
    $this->assertTrue($own->access('view', $viewer));
    $this->assertFalse($own->access('update', $viewer));
    $this->assertFalse($own->access('delete', $viewer));
    $this->assertFalse($handler->createAccess('note', $viewer));

    // The note permission does not grant access to mails.
    $mail = $this->createMail($note);
    $this->assertFalse($mail->isNote());
    $this->assertFalse($mail->access('update', $user));
    $this->assertFalse($mail->access('delete', $user));
    $admin = $this->createUser(['administer correspondence']);
    $this->assertTrue($mail->access('update', $admin));
    // The administer permission alone does not allow to edit notes.
    $this->assertFalse($note->access('update', $admin));
  }

  /**
   * The assigned manager and administrators pass, other managers do not.
   */
  public function testOnlyTheAssignedManagerOrAdministratorHasAccess(): void {
    $note = $this->createNote();
    $mail = $this->createMail($note);
    $permissions = ['view correspondence', 'manage correspondence notes', 'access content'];
    $manager = $this->managerOf($note, $permissions);
    $other = $this->otherManager($permissions);
    $admin = $this->createUser([...$permissions, 'administer projects']);
    $no_manager = $this->createUser($permissions);

    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertTrue($note->access($operation, $manager), "manager $operation note");
      $this->assertTrue($note->access($operation, $admin), "admin $operation note");
      $this->assertFalse($note->access($operation, $other), "other manager $operation note");
      $this->assertFalse($note->access($operation, $no_manager), "no manager $operation note");
    }
    // Mails: view needs the manager too; update and delete stay admin-only.
    $this->assertTrue($mail->access('view', $manager));
    $this->assertTrue($mail->access('view', $admin));
    $this->assertFalse($mail->access('view', $other));
    $this->assertFalse($mail->access('view', $no_manager));
    $this->assertFalse($mail->access('update', $manager));
    $this->assertFalse($mail->access('delete', $manager));
  }

  /**
   * Each denial is due to exactly the missing condition.
   */
  public function testEachConditionIsRequired(): void {
    $note = $this->createNote();
    $manager_permissions = ['view correspondence', 'manage correspondence notes', 'access content'];
    // The manager of another organization lacking nothing else is denied.
    $other = $this->otherManager($manager_permissions);
    $this->assertTrue($this->projectOf($note)->access('view', $other), 'The project itself is viewable.');
    $this->assertFalse($note->access('update', $other));
    // The assigned manager lacking the view permission cannot view.
    $no_view = $this->managerOf($note, ['manage correspondence notes']);
    $this->assertFalse($note->access('view', $no_view));
    $this->assertTrue($note->access('update', $no_view));
    // The assigned manager lacking the note permission cannot update.
    $own = $this->createNote();
    $no_notes = $this->managerOf($own, ['view correspondence']);
    $this->assertFalse($own->access('update', $no_notes));
  }

  /**
   * Access does not depend on the project being viewable (unpublished states).
   */
  public function testManagerAccessOnUnpublishedProjects(): void {
    $permissions = ['view correspondence', 'manage correspondence notes', 'access content'];
    foreach ([ProjectState::Draft, ProjectState::Pending, ProjectState::Open] as $state) {
      $name = $state->name;
      $note = $this->createNote($state);
      $mail = $this->createMail($note);
      $manager = $this->managerOf($note, $permissions);
      $other = $this->otherManager($permissions);
      $admin = $this->createUser([...$permissions, 'administer projects']);
      $stranger = $this->createUser($permissions);

      foreach (['view', 'update', 'delete'] as $operation) {
        $this->assertTrue($note->access($operation, $manager), "$name: manager $operation note");
        $this->assertTrue($note->access($operation, $admin), "$name: admin $operation note");
        $this->assertFalse($note->access($operation, $other), "$name: other manager $operation note");
        $this->assertFalse($note->access($operation, $stranger), "$name: stranger $operation note");
      }
      $this->assertTrue($mail->access('view', $manager), "$name: manager views mail");
      $this->assertTrue($mail->access('view', $admin), "$name: admin views mail");
      $this->assertFalse($mail->access('view', $other), "$name: other manager views mail");
      $this->assertFalse($mail->access('view', $stranger), "$name: stranger views mail");
      $this->assertFalse($mail->access('update', $manager), "$name: manager updates mail");
    }
  }

  /**
   * The access result depends on the user, the owner and the project.
   */
  public function testCacheMetadata(): void {
    $note = $this->createNote();
    $manager = $this->managerOf($note, ['view correspondence', 'manage correspondence notes']);
    $result = $note->access('update', $manager, TRUE);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertContains('user', $result->getCacheContexts());
    $this->assertContains('correspondence:' . $note->id(), $result->getCacheTags());
    $project = $this->projectOf($note);
    $this->assertContains('project:' . $project->id(), $result->getCacheTags());
    $this->assertContains('user:' . $project->getOwner()->id(), $result->getCacheTags());
  }

  /**
   * The occurred time falls back to the created time.
   */
  public function testOccurredFallsBackToCreated(): void {
    $note = $this->createNote();
    $note->set('occurred', NULL);
    $this->assertSame((int) $note->get('created')->value, $note->getOccurredTime());
    $this->assertGreaterThan(0, $note->getOccurredTime());
  }

  /**
   * A dangling parent is denied instead of throwing.
   */
  public function testDanglingParentIsDenied(): void {
    $user = $this->createUser(['view correspondence', 'manage correspondence notes']);
    $note = Correspondence::create(['type' => 'note', 'project' => 99999, 'subject' => 'N']);
    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertFalse($note->access($operation, $user), $operation);
    }
    $new = Correspondence::create(['type' => 'note', 'subject' => 'N']);
    $this->assertFalse($new->access('update', $user));
  }

}
