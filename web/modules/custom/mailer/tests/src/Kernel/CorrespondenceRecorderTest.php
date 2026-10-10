<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Tests the correspondence recorder.
 *
 * @group mailer
 */
class CorrespondenceRecorderTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'filter',
    'text',
    'correspondence',
    'youvo',
    'symfony_mailer',
    'mailer_policy',
    'mailer_transport',
    'mailer',
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
   * A failed send is recorded with result failed.
   */
  public function testRecordsFailedMail(): void {
    $project = $this->createProject(ProjectState::Open);
    $project->save();
    $recorder = $this->container->get('mailer.correspondence_recorder');
    $entry = $recorder->recordMail($project, 'mailer.project_transition.publish_organization', ['a@example.com'], 'Betreff', '<p>Text</p>', FALSE);
    $loaded = Correspondence::load($entry->id());
    $this->assertFalse($loaded->isSent());
    $this->assertSame('automatic', $loaded->get('origin')->value);
    $this->assertSame(['a@example.com'], json_decode($loaded->get('recipients')->value, TRUE));
  }

  /**
   * Recording without a project does not fail.
   */
  public function testNoProjectDoesNotRecord(): void {
    $recorder = $this->container->get('mailer.correspondence_recorder');
    $this->assertNull($recorder->recordMail(NULL, 'mailer.user.register_creative', ['a@example.com'], 'S', 'B', TRUE));
  }

}
