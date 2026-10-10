<?php

namespace Drupal\Tests\child_entities\Kernel;

use Drupal\child_entities_test\Entity\TestChild;
use Drupal\child_entities_test\Entity\TestGrandchild;
use Drupal\child_entities_test\Entity\TestLoose;
use Drupal\child_entities_test\Entity\TestOrigin;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\Route;

/**
 * Tests the shared behavior of child entities.
 *
 * @group child_entities
 */
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
   * Tests that new children are appended after their siblings.
   */
  public function testWeightIsAppendedToSiblings(): void {

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
  }

  /**
   * Tests that deleting an origin cascades only to opted-in children.
   */
  public function testCascadeDeleteIsOptIn(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $child = TestChild::create(['child_test_origin' => $origin->id()]);
    $child->save();
    $grandchild = TestGrandchild::create(['child_test_child' => $child->id()]);
    $grandchild->save();
    $loose = TestLoose::create(['child_test_origin' => $origin->id()]);
    $loose->save();

    $origin->delete();

    $this->assertNull(TestChild::load($child->id()));
    $this->assertNull(TestGrandchild::load($grandchild->id()));
    $this->assertNotNull(TestLoose::load($loose->id()));
  }

  /**
   * Tests that deleting through the storage cascades as well.
   */
  public function testBulkDeleteCascades(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $child = TestChild::create(['child_test_origin' => $origin->id()]);
    $child->save();
    $grandchild = TestGrandchild::create(['child_test_child' => $child->id()]);
    $grandchild->save();

    $this->container->get('entity_type.manager')
      ->getStorage('child_test_origin')
      ->delete([$origin]);

    $this->assertNull(TestChild::load($child->id()));
    $this->assertNull(TestGrandchild::load($grandchild->id()));
  }

  /**
   * Tests that deleting a child invalidates the cache tag of its parent.
   */
  public function testDeleteInvalidatesParentCacheTag(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $child = TestChild::create(['child_test_origin' => $origin->id()]);
    $child->save();

    $tag = 'child_test_origin:' . $origin->id();
    $this->container->get('cache.default')->set('child_test_item', 1, Cache::PERMANENT, [$tag]);
    $this->assertNotFalse($this->container->get('cache.default')->get('child_test_item'));

    $child->delete();
    $this->assertFalse($this->container->get('cache.default')->get('child_test_item'));
  }

  /**
   * Tests that the form base class takes the parent from the route.
   */
  public function testFormSetsParentFromRoute(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();

    $route = new Route('/test/{child_test_origin}/add');
    $route_match = new RouteMatch('test.add', $route, ['child_test_origin' => $origin], ['child_test_origin' => $origin->id()]);

    $form = $this->container->get('entity_type.manager')
      ->getFormObject('child_test_child', 'default');
    $entity = $form->getEntityFromRouteMatch($route_match, 'child_test_child');

    $this->assertTrue($entity->isNew());
    $this->assertEquals($origin->id(), $entity->getParentId());
  }

  /**
   * Tests resolving the hierarchy of child entity types.
   */
  public function testHierarchy(): void {

    $hierarchy = $this->container->get('child_entities.hierarchy');
    $type = $this->container->get('entity_type.manager')->getDefinition('child_test_grandchild');

    $this->assertSame(['child_test_child', 'child_test_origin'], $hierarchy->getAncestorEntityTypeIds($type));
    $this->assertSame('child_test_origin', $hierarchy->getOriginEntityType($type)->id());
    $this->assertSame('child_test_origin', $hierarchy->getChildEntityTypes()['child_test_loose']);
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

  /**
   * Tests that a missing parent is reported with a meaningful exception.
   */
  public function testMissingParentThrows(): void {

    $origin = TestOrigin::create(['name' => 'origin']);
    $origin->save();
    $loose = TestLoose::create(['child_test_origin' => $origin->id()]);
    $loose->save();
    // The loose child does not cascade, so it is orphaned.
    $origin->delete();

    $loose = TestLoose::load($loose->id());
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('child_test_origin');
    $loose->getParentEntity();
  }

}
