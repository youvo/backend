<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a test base for the mailer tests on the installed site.
 *
 * Intercepts every mail and removes the logbook listeners, whose log entries
 * would outlive the test. The mailer listeners stay in place.
 */
abstract class MailerExistingSiteTestBase extends ExistingSiteBase {

  use MailInterceptTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();
    $this->interceptMails();

    $event_dispatcher = $this->container->get('event_dispatcher');
    foreach ($event_dispatcher->getListeners() as $listeners) {
      foreach ($listeners as $listener) {
        if (is_array($listener) && str_starts_with($listener[0]::class, 'Drupal\logbook\\')) {
          $event_dispatcher->removeSubscriber($listener[0]);
        }
      }
    }
  }

  /**
   * Creates a project whose organization and manager have mail addresses.
   *
   * The manager is named and the organization has a contact person.
   */
  protected function createMailableProject(ProjectState $state = ProjectState::Open, bool $prospect = FALSE): ProjectInterface {

    $manager = $this->createCreative('manager');
    $manager->addRole('manager');
    $manager->save();

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => 'Organization ' . $this->randomMachineName(),
      'field_contact' => 'Contact ' . $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'field_manager' => $manager,
      'status' => 1,
    ]);
    $organization->addRole($prospect ? Organization::ROLE_PROSPECT : Organization::ROLE_ORGANIZATION);
    $organization->save();

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => 'Project ' . $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();

    // The project goes first: its correspondence is deleted with it.
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    $this->markEntityForCleanup($organization);
    return $project;
  }

  /**
   * Creates a named creative with a mail address.
   */
  protected function createCreative(string $prefix = 'creative', ?string $mail = NULL): Creative {
    $name = ucfirst($prefix) . ' ' . $this->randomMachineName();
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $name,
      'mail' => $mail ?? strtolower($this->randomMachineName()) . '@example.com',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Gets the site mail address.
   */
  protected function siteMail(): string {
    return (string) $this->container->get('config.factory')->get('system.site')->get('mail');
  }

  /**
   * Gets the site name.
   */
  protected function siteName(): string {
    return (string) $this->container->get('config.factory')->get('system.site')->get('name');
  }

  /**
   * Loads the recorded correspondence of a project, oldest first.
   *
   * @return array<int, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface>
   *   The entries.
   */
  protected function loadEntries(ProjectInterface $project): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('correspondence');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->sort('id')
      ->execute();
    /** @var array<int, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface> $entries */
    $entries = array_values($storage->loadMultiple($ids));
    return $entries;
  }

  /**
   * Loads the recorded correspondence of a project grouped by subtype.
   *
   * @return array<string, array<int, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface>>
   *   The entries, keyed by the full subtype.
   */
  protected function loadEntriesBySubtype(ProjectInterface $project): array {
    $grouped = [];
    foreach ($this->loadEntries($project) as $entry) {
      $grouped[$entry->getSubtype()][] = $entry;
    }
    return $grouped;
  }

  /**
   * Counts the recorded correspondence sent to an address.
   */
  protected function countEntriesTo(string $mail): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('correspondence')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('recipients', '%' . $mail . '%', 'LIKE')
      ->count()
      ->execute();
  }

}
