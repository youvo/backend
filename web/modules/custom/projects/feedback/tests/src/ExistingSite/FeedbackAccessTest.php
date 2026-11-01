<?php

namespace Drupal\Tests\feedback\ExistingSite;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\feedback\Access\FeedbackEntityAccess;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the feedback entity access with the site roles.
 */
#[CoversClass(FeedbackEntityAccess::class)]
#[Group('feedback')]
class FeedbackAccessTest extends ProjectResourceTestBase {

  /**
   * The feedback under test, not completed.
   */
  protected FeedbackInterface $feedback;

  /**
   * The author of the feedback.
   */
  protected Creative $author;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $project = $this->createProject(ProjectState::Completed);
    $this->author = $this->createCreative();
    $this->feedback = Feedback::create([
      'bundle' => 'creative',
      'project' => $project->id(),
      'author' => $this->author->id(),
    ]);
    $this->feedback->save();
    $this->markEntityForCleanup($this->feedback);
  }

  /**
   * Tests that the author views and edits until the feedback is completed.
   */
  public function testAuthorAccess(): void {
    $this->assertAccess($this->author, TRUE, TRUE);

    $this->feedback->complete()->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('feedback')->resetCache();
    $this->assertAccess($this->author, FALSE, FALSE);
  }

  /**
   * Tests that other users have no access.
   */
  public function testOtherUserAccess(): void {
    $this->assertAccess($this->createCreative(), FALSE, FALSE);
    $this->assertAccess($this->createManager(), FALSE, FALSE);
  }

  /**
   * Tests that supervisors and administrators only view the feedback.
   */
  public function testAdministerFeedbackAccess(): void {
    $accounts = [$this->createSupervisor(), $this->createCreative('administrator')];
    foreach ($accounts as $account) {
      $this->assertAccess($account, TRUE, FALSE);
    }
    $this->feedback->complete()->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('feedback')->resetCache();
    foreach ($accounts as $account) {
      $this->assertAccess($account, TRUE, FALSE);
    }
  }

  /**
   * Tests that the results vary per permissions and per user.
   */
  public function testCacheability(): void {
    $result = $this->feedback->access('view', $this->createCreative(), TRUE);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertContains('user', $result->getCacheContexts());
    $this->assertContains('feedback:' . $this->feedback->id(), $result->getCacheTags());
  }

  /**
   * Asserts the view and update access, and that delete is never allowed.
   */
  protected function assertAccess(UserInterface $account, bool $view, bool $update): void {
    $this->assertSame($view, $this->feedback->access('view', $account));
    $this->assertSame($update, $this->feedback->access('update', $account));
    $this->assertFalse($this->feedback->access('delete', $account));
  }

}
