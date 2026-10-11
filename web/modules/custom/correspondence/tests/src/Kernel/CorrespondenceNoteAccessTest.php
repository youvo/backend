<?php

namespace Drupal\Tests\correspondence\Kernel;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

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
  }

  /**
   * Creates a saved note on a new open project.
   */
  protected function createNote(): Correspondence {
    $project = $this->createProject(ProjectState::Open);
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
   * Notes are manageable with their own permission; mails stay admin-only.
   */
  public function testNoteAccess(): void {
    $note = $this->createNote();
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('correspondence');
    $user = $this->createUser(['view correspondence', 'manage correspondence notes', 'access content']);
    $this->assertTrue($note->access('update', $user));
    $this->assertTrue($note->access('delete', $user));
    $this->assertTrue($handler->createAccess('note', $user));
    $this->assertFalse($handler->createAccess('mail', $user));

    // A user with only view permission cannot edit.
    $viewer = $this->createUser(['view correspondence', 'access content']);
    $this->assertFalse($note->access('update', $viewer));
    $this->assertFalse($note->access('delete', $viewer));
    $this->assertFalse($handler->createAccess('note', $viewer));
    $this->assertTrue($note->isNote());
    $this->assertSame(1700000000, $note->getOccurredTime());

    // The note permission does not grant access to mails.
    $mail = Correspondence::create(['type' => 'mail', 'project' => $note->get('project')->target_id, 'subject' => 'M']);
    $mail->save();
    $this->assertFalse($mail->isNote());
    $this->assertFalse($mail->access('update', $user));
    $this->assertFalse($mail->access('delete', $user));
    $admin = $this->createUser(['administer correspondence']);
    $this->assertTrue($mail->access('update', $admin));
    // The administer permission alone does not allow to edit notes.
    $this->assertFalse($note->access('update', $admin));
  }

  /**
   * Editing a note requires access to its project.
   */
  public function testNoteRequiresProjectAccess(): void {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $note = Correspondence::create(['type' => 'note', 'project' => $project->id(), 'subject' => 'N']);
    $note->save();
    $user = $this->createUser(['view correspondence', 'manage correspondence notes', 'access content']);
    $this->assertFalse($project->access('view', $user));
    $this->assertFalse($note->access('update', $user));
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
