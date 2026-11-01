<?php

namespace Drupal\Tests\feedback\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\feedback\Access\FeedbackEntityAccess;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\Entity\FeedbackType;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the feedback entity access.
 */
#[CoversClass(FeedbackEntityAccess::class)]
#[Group('feedback')]
class FeedbackAccessTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'feedback',
    'file',
    'link',
    'projects_result_test',
    'system',
  ];

  /**
   * The feedback under test, not completed.
   */
  protected FeedbackInterface $feedback;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('feedback');
    $this->installEntitySchema('project_comment');
    $this->installConfig('projects_result_test');
    FeedbackType::create(['id' => 'creative', 'label' => 'creative'])->save();
    // The first user bypasses all permissions. Create it up front.
    $this->createCreative();
    Role::create(['id' => 'supervisor', 'label' => 'Supervisor'])
      ->grantPermission('administer feedback')
      ->save();
    Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE])->save();

    $project = $this->createProject(ProjectState::Completed);
    $project->save();
    $this->feedback = Feedback::create([
      'bundle' => 'creative',
      'project' => $project->id(),
      'author' => $this->createCreative()->id(),
    ]);
    $this->feedback->save();
  }

  /**
   * Tests that the supervisor role in config/sync holds the permission.
   */
  public function testSupervisorPermissionIsPinned(): void {
    $role = Yaml::decode(file_get_contents(DRUPAL_ROOT . '/../config/sync/user.role.supervisor.yml'));
    $this->assertContains('administer feedback', $role['permissions']);
  }

  /**
   * Tests that the author views and edits until the feedback is completed.
   */
  public function testAuthorAccess(): void {
    $author = $this->feedback->getOwner();
    $this->assertAccess($author, TRUE, TRUE);

    $this->feedback->complete()->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('feedback')->resetCache();
    $this->assertAccess($author, FALSE, FALSE);
  }

  /**
   * Tests that other users have no access.
   */
  public function testOtherUserAccess(): void {
    $this->assertAccess($this->createCreative(), FALSE, FALSE);
  }

  /**
   * Tests that supervisors and administrators only view the feedback.
   */
  public function testAdministerFeedbackAccess(): void {
    foreach (['supervisor', 'administrator'] as $role) {
      $account = $this->createCreative()->addRole($role);
      $account->save();
      $this->assertAccess($account, TRUE, FALSE);
    }
    $this->feedback->complete()->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('feedback')->resetCache();
    $this->assertAccess($account, TRUE, FALSE);
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
