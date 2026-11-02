<?php

namespace Drupal\Tests\correspondence\ExistingSite;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the correspondence entity and its access rules with the site roles.
 *
 * Viewing, and editing notes, needs the permission and the assigned manager
 * of the project or the permission to administer projects. Mails are edited
 * with the administer permission only. The entries are not marked for
 * cleanup: they are deleted with their project.
 */
#[Group('correspondence')]
class CorrespondenceAccessTest extends ProjectResourceTestBase {

  /**
   * The note permissions of the manager role.
   */
  protected const array NOTE_PERMISSIONS = ['view correspondence', 'manage correspondence notes', 'access content'];

  /**
   * Notes are manageable with their own permission; mails stay admin-only.
   */
  public function testAccess(): void {

    $project = $this->createProject(ProjectState::Open);
    $note = $this->createEntry($project, 'note');
    $mail = $this->createEntry($project, 'mail');
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('correspondence');
    $manager = $project->getOwner()->getManager();
    $other_manager = $this->createManager();
    // Supervisors administer projects.
    $supervisor = $this->createSupervisor();
    $no_manager = $this->createCreativeWithPermissions(static::NOTE_PERMISSIONS);
    $admin = $this->createCreativeWithPermissions(['administer correspondence']);
    $anonymous = new AnonymousUserSession();

    // The assigned manager and supervisors pass, other managers and users
    // with the permissions only do not.
    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertTrue($note->access($operation, $manager), "manager $operation note");
      $this->assertTrue($note->access($operation, $supervisor), "supervisor $operation note");
      $this->assertFalse($note->access($operation, $other_manager), "other manager $operation note");
      $this->assertFalse($note->access($operation, $no_manager), "no manager $operation note");
      $this->assertFalse($note->access($operation, $anonymous), "anonymous $operation note");
    }
    $this->assertTrue($handler->createAccess('note', $manager));
    $this->assertFalse($handler->createAccess('mail', $manager));
    // The project itself is viewable, its correspondence is not.
    $this->assertTrue($project->access('view', $other_manager));
    $this->assertTrue($project->access('view', $this->createCreative()));
    $this->assertFalse($mail->access('view', $this->createCreative()));

    // Mails: view needs the manager too; update, delete and create need the
    // administer permission, which alone does not allow to edit notes.
    $this->assertTrue($mail->access('view', $manager));
    $this->assertTrue($mail->access('view', $supervisor));
    $this->assertFalse($mail->access('view', $other_manager));
    $this->assertFalse($mail->access('view', $no_manager));
    $this->assertFalse($mail->access('view', $anonymous));
    foreach (['update', 'delete'] as $operation) {
      $this->assertFalse($mail->access($operation, $manager), "manager $operation mail");
      $this->assertFalse($mail->access($operation, $supervisor), "supervisor $operation mail");
      $this->assertTrue($mail->access($operation, $admin), "admin $operation mail");
      $this->assertFalse($mail->access($operation, $anonymous), "anonymous $operation mail");
    }
    $this->assertTrue($handler->createAccess('mail', $admin));
    $this->assertFalse($note->access('update', $admin));

    // Each denial is due to exactly the missing condition: the assigned
    // manager lacking the view permission cannot view, but update.
    $no_view = $this->createCreativeWithPermissions(['manage correspondence notes']);
    $no_view_note = $this->createEntry($this->createManagedProject($no_view), 'note');
    $this->assertFalse($no_view_note->access('view', $no_view));
    $this->assertTrue($no_view_note->access('update', $no_view));
    // The assigned manager lacking the note permission only views.
    $no_notes = $this->createCreativeWithPermissions(['view correspondence']);
    $no_notes_note = $this->createEntry($this->createManagedProject($no_notes), 'note');
    $this->assertTrue($no_notes_note->access('view', $no_notes));
    $this->assertFalse($no_notes_note->access('update', $no_notes));
    $this->assertFalse($no_notes_note->access('delete', $no_notes));
    $this->assertFalse($handler->createAccess('note', $no_notes));
  }

  /**
   * Access does not depend on the project being viewable.
   *
   * The assigned manager may not view draft and pending projects.
   */
  public function testUnpublishedProjects(): void {

    foreach ([ProjectState::Draft, ProjectState::Pending] as $state) {
      $name = $state->name;
      $project = $this->createProject($state);
      $note = $this->createEntry($project, 'note');
      $mail = $this->createEntry($project, 'mail');
      $manager = $project->getOwner()->getManager();
      $supervisor = $this->createSupervisor();
      $other_manager = $this->createManager();
      $stranger = $this->createCreativeWithPermissions(static::NOTE_PERMISSIONS);

      foreach (['view', 'update', 'delete'] as $operation) {
        $this->assertTrue($note->access($operation, $manager), "$name: manager $operation note");
        $this->assertTrue($note->access($operation, $supervisor), "$name: supervisor $operation note");
        $this->assertFalse($note->access($operation, $other_manager), "$name: other manager $operation note");
        $this->assertFalse($note->access($operation, $stranger), "$name: stranger $operation note");
      }
      $this->assertTrue($mail->access('view', $manager), "$name: manager views mail");
      $this->assertTrue($mail->access('view', $supervisor), "$name: supervisor views mail");
      $this->assertFalse($mail->access('view', $other_manager), "$name: other manager views mail");
      $this->assertFalse($mail->access('view', $stranger), "$name: stranger views mail");
      $this->assertFalse($mail->access('update', $manager), "$name: manager updates mail");
    }
  }

  /**
   * Entries belong to a project, are deleted with it and cache per user.
   */
  public function testEntryData(): void {

    $project = $this->createProject(ProjectState::Draft);
    $mail = $this->createEntry($project, 'mail', [
      'body' => ['value' => '<p>Text</p>', 'format' => 'email_html'],
      'recipients' => json_encode(['a@example.com']),
      'origin' => 'automatic',
      'result' => 'sent',
    ]);
    $note = $this->createEntry($project, 'note', ['occurred' => 1700000000]);
    $this->assertSame($project->id(), $mail->getParentEntity()->id());
    $this->assertFalse($mail->isNote());
    $this->assertTrue($note->isNote());
    $this->assertSame(1700000000, $note->getOccurredTime());
    // The occurred time falls back to the created time.
    $note->set('occurred', NULL);
    $this->assertSame((int) $note->get('created')->value, $note->getOccurredTime());
    $this->assertGreaterThan(0, $note->getOccurredTime());

    // The access result depends on the user, the owner and the project.
    $result = $note->access('update', $project->getOwner()->getManager(), TRUE);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertContains('user', $result->getCacheContexts());
    $this->assertContains('correspondence:' . $note->id(), $result->getCacheTags());
    $this->assertContains('project:' . $project->id(), $result->getCacheTags());
    $this->assertContains('user:' . $project->getOwner()->id(), $result->getCacheTags());

    // Deleting the project deletes its entries.
    $project->delete();
    $storage = $this->container->get('entity_type.manager')->getStorage('correspondence');
    $storage->resetCache();
    $this->assertNull($storage->load($mail->id()));
    $this->assertNull($storage->load($note->id()));

    // A dangling or missing parent is denied instead of throwing.
    $supervisor = $this->createSupervisor();
    $dangling = Correspondence::create(['type' => 'note', 'project' => $project->id(), 'subject' => 'N']);
    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertFalse($dangling->access($operation, $supervisor), $operation);
    }
    $new = Correspondence::create(['type' => 'note', 'subject' => 'N']);
    $this->assertFalse($new->access('update', $supervisor));
  }

  /**
   * Creates a saved entry of the project.
   */
  protected function createEntry(ProjectInterface $project, string $type, array $values = []): CorrespondenceInterface {
    $entry = Correspondence::create($values + [
      'type' => $type,
      'project' => $project->id(),
      'subject' => 'Hallo',
      'subtype' => $type === 'mail' ? 'mailer.project_transition.publish_organization' : NULL,
      'body' => ['value' => 'Text', 'format' => 'plain_text'],
    ]);
    $entry->save();
    return $entry;
  }

  /**
   * Creates an open project whose organization the creative manages.
   */
  protected function createManagedProject(Creative $manager): ProjectInterface {
    $project = $this->createProject(ProjectState::Open);
    $project->getOwner()->set('field_manager', $manager)->save();
    return $project;
  }

  /**
   * Creates a creative with the permissions through a temporary role.
   */
  protected function createCreativeWithPermissions(array $permissions): Creative {
    $creative = $this->createCreative();
    $creative->addRole($this->createRole($permissions));
    $creative->save();
    return $creative;
  }

}
