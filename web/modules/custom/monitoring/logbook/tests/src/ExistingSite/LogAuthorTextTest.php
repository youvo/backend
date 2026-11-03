<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\projects\ProjectInterface;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the logbook text names the author of a log.
 */
#[Group('logbook')]
class LogAuthorTextTest extends ProjectResourceTestBase {

  /**
   * An author is named with role and link, a log without one by the platform.
   */
  public function testAuthorText(): void {

    $project = $this->createProject();
    $manager = $project->getOwner()->getManager();
    $this->assertNotNull($manager);

    $log = $this->createLog($project, (int) $manager->id());
    $markup = (string) $log->get('markup')->value;
    $this->assertStringContainsString('von der Managerin <a href="' . $manager->toUrl()->toString() . '"', $markup);

    // A log written without a user, such as by cron.
    $log = $this->createLog($project, 0);
    $markup = (string) $log->get('markup')->value;
    $this->assertStringContainsString('wurde von der Plattform veröffentlicht.', $markup);
    $this->assertStringNotContainsString('Kreative', $markup);
    $this->assertStringNotContainsString('Anonymous', $markup);
  }

  /**
   * Creates a publish log of a project.
   */
  protected function createLog(ProjectInterface $project, int $author): LogInterface {
    $log = Log::create([
      'type' => 'project_publish',
      'author' => $author,
      'project' => $project->id(),
      'organization' => $project->getOwner()->id(),
      'manager' => $project->getOwner()->getManager()?->id(),
    ]);
    $log->save();
    $this->markEntityForCleanup($log);
    return $log;
  }

}
