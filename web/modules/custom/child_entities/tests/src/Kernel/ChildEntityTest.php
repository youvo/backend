<?php

namespace Drupal\Tests\child_entities\Kernel;

use Drupal\child_entities\ChildEntityInterface;
use Drupal\child_entities_test\Entity\TestChild;
use Drupal\child_entities_test\Entity\TestGrandchild;
use Drupal\child_entities_test\Entity\TestLoose;
use Drupal\child_entities_test\Entity\TestOrigin;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests the shared behavior of child entities.
 *
 * The tests need the test-only entity types of child_entities_test.
 */
#[Group('child_entities')]
#[RunTestsInSeparateProcesses]
class ChildEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'child_entities_test',
    'content_translation',
    'language',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // Occupy uid 1, which bypasses all access checks.
    User::create(['name' => 'root'])->save();
    $this->installEntitySchema('child_test_origin');
    $this->installEntitySchema('child_test_child');
    $this->installEntitySchema('child_test_grandchild');
    $this->installEntitySchema('child_test_loose');
  }

  /**
   * Tests the hierarchy, the weight of new children and the add form.
   */
  public function testHierarchyWeightAndForm(): void {

    // The hierarchy of child entity types is resolved up to the origin.
    $hierarchy = $this->container->get('child_entities.hierarchy');
    $type = $this->container->get('entity_type.manager')->getDefinition('child_test_grandchild');
    $this->assertSame(['child_test_child', 'child_test_origin'], $hierarchy->getAncestorEntityTypeIds($type));
    $this->assertSame('child_test_origin', $hierarchy->getOriginEntityType($type)->id());
    $this->assertSame('child_test_origin', $hierarchy->getChildEntityTypes()['child_test_loose']);

    // New children are appended after their siblings.
    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $other_origin = TestOrigin::create(['name' => 'other']);
    $other_origin->save();
    $first = TestChild::create(['child_test_origin' => $origin->id()]);
    $first->save();
    $second = TestChild::create(['child_test_origin' => $origin->id()]);
    $second->save();
    $third = TestChild::create(['child_test_origin' => $origin->id()]);
    $third->save();
    $unrelated = TestChild::create(['child_test_origin' => $other_origin->id()]);
    $unrelated->save();
    $this->assertEquals(0, $first->get('weight')->value);
    $this->assertEquals(1, $second->get('weight')->value);
    $this->assertEquals(2, $third->get('weight')->value);
    $this->assertEquals(0, $unrelated->get('weight')->value);
    // Updating does not touch the weight.
    $first->set('name', 'changed')->save();
    $this->assertEquals(0, $first->get('weight')->value);

    // The form base class takes the parent from the route.
    $route = new Route('/test/{child_test_origin}/add');
    $route_match = new RouteMatch('test.add', $route, ['child_test_origin' => $origin], ['child_test_origin' => $origin->id()]);
    $form = $this->container->get('entity_type.manager')->getFormObject('child_test_child', 'default');
    $entity = $form->getEntityFromRouteMatch($route_match, 'child_test_child');
    $this->assertInstanceOf(ChildEntityInterface::class, $entity);
    $this->assertTrue($entity->isNew());
    $this->assertEquals($origin->id(), $entity->getParentId());
  }

  /**
   * Tests that deleting cascades to opted-in children and their caches.
   */
  public function testDeleteCascades(): void {

    // Deleting a child invalidates the cache tag of its parent.
    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $deleted_child = TestChild::create(['child_test_origin' => $origin->id()]);
    $deleted_child->save();
    $cache = $this->container->get('cache.default');
    $cache->set('child_test_item', 1, Cache::PERMANENT, ['child_test_origin:' . $origin->id()]);
    $this->assertNotFalse($cache->get('child_test_item'));
    $deleted_child->delete();
    $this->assertFalse($cache->get('child_test_item'));

    // Deleting an origin cascades only to opted-in children.
    $child = TestChild::create(['child_test_origin' => $origin->id()]);
    $child->save();
    $grandchild = TestGrandchild::create(['child_test_child' => $child->id()]);
    $grandchild->save();
    $loose = TestLoose::create(['child_test_origin' => $origin->id()]);
    $loose->save();
    $origin->delete();
    $this->assertNull(TestChild::load($child->id()));
    $this->assertNull(TestGrandchild::load($grandchild->id()));
    $loose = TestLoose::load($loose->id());
    $this->assertNotNull($loose);

    // Deleting through the storage cascades as well.
    $bulk_origin = TestOrigin::create(['name' => 'bulk']);
    $bulk_origin->save();
    $bulk_child = TestChild::create(['child_test_origin' => $bulk_origin->id()]);
    $bulk_child->save();
    $bulk_grandchild = TestGrandchild::create(['child_test_child' => $bulk_child->id()]);
    $bulk_grandchild->save();
    $this->container->get('entity_type.manager')->getStorage('child_test_origin')->delete([$bulk_origin]);
    $this->assertNull(TestChild::load($bulk_child->id()));
    $this->assertNull(TestGrandchild::load($bulk_grandchild->id()));

    // The orphaned loose child reports its missing parent with a meaningful
    // exception.
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('child_test_origin');
    $loose->getParentEntity();
  }

  /**
   * Tests that children inherit access from their origin entity.
   */
  public function testAccessIsInheritedFromOrigin(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $child = TestChild::create(['child_test_origin' => $origin->id()]);
    $child->save();
    $grandchild = TestGrandchild::create(['child_test_child' => $child->id()]);
    $grandchild->save();

    $viewer = $this->createAccount(['view child test origin']);
    $creator = $this->createAccount(['create child test origin']);
    $admin = $this->createAccount(['administer child test']);
    $nobody = $this->createAccount([]);

    $this->assertTrue($child->access('view', $viewer));
    $this->assertTrue($grandchild->access('view', $viewer));
    $this->assertFalse($child->access('update', $viewer));
    $this->assertFalse($child->access('view', $nobody));
    $this->assertTrue($child->access('delete', $admin));

    $storage = $this->container->get('entity_type.manager');
    $this->assertTrue($storage->getAccessControlHandler('child_test_child')->createAccess(NULL, $creator));
    $this->assertTrue($storage->getAccessControlHandler('child_test_grandchild')->createAccess(NULL, $creator));
    $this->assertTrue($storage->getAccessControlHandler('child_test_child')->createAccess(NULL, $admin));
    $this->assertFalse($storage->getAccessControlHandler('child_test_child')->createAccess(NULL, $nobody));
  }

  /**
   * Creates a user with the given permissions.
   */
  protected function createAccount(array $permissions): User {
    static $uid = 1;
    $role = Role::create(['id' => 'role_' . $uid, 'label' => 'Role ' . $uid]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    $user = User::create(['name' => 'user_' . $uid, 'roles' => [$role->id()]]);
    $user->save();
    $uid++;
    return $user;
  }

}
