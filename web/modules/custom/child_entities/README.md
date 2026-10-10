# Child Entities module

## Summary

The `child_entities` module introduces a parent-child relationship model to establish the references between content entities. It is used by the academy (`Lecture`, `Paragraph`, `Question`), the projects (`ProjectResult`, `ProjectComment`) and the logbook (`LogText`). It is a fork of the Drupal project https://www.drupal.org/project/child_entity. We altered and extended the behavior of the contributed module since it is sparsely maintained and does not cover all of our custom scenarios.

## Academy data structure

As an example, the academy package introduces the content entities `Course`, `Lecture`, `Paragraph` and `Question`. They follow a parent-/ child relationship, where we have the following descendants:

- `Course` -> `Lecture` -> `Paragraph` -> `Question`

A general overview of the entity structure can be found here: [Entities Chart](https://whimsical.com/youvo-academy-E89MFfEmpwiW8QgGqWwiZu).

## Basic usage

A child entity should extend the `ChildEntityInterface`, define the entity keys `parent` and `weight` in the annotations and include the `ChildEntityTrait`. The base field definitions should initialise the child entity base fields. The `parent` key must equal the parent's entity type ID (in the example, a parent entity type with the ID `parent`). It is also the name of the parent field and of the route parameter.

```php
/**
 * @ContentEntityType(
 *   id = "child",
 *   handlers = {
 *     "form" = {"add" = "Drupal\child_entities\Form\ChildEntityForm"},
 *     "route_provider" = {"html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider"},
 *   },
 *   cascade_delete = TRUE,
 *   entity_keys = {"parent" = "parent", "weight" = "weight", ...},
 * )
 */
class ChildEntity extends ContentEntityBase implements ChildEntityInterface {

  use ChildEntityTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::childBaseFieldDefinitions($entity_type);
    return $fields;
  }

}
```

The trait appends new children after their siblings (weight) and invalidates the parent's cache tag on save and delete. Forms should extend `ChildEntityForm`, which fills the parent of a new entity from the route. Setting `cascade_delete = TRUE` in the annotation deletes the children when their parent is deleted. The ancestor chain of child entity types is resolved by the `child_entities.hierarchy` service.

## Tasks

### Child Entity Definition

**Files:** `ChildEntityInterface` `ChildEntityTrait` `ChildEntityEnsureTrait` `ChildEntityHierarchy` `ChildEntityListBuilder`

The `ChildEntityInterface` and `ChildEntityTrait` define and provide the basic functionality of a child entity, e.g. getting the referenced parent entity or the origin entity, which is the first ancestor that is not a child entity itself. `getParentEntity()` throws a `\RuntimeException` if the parent is not set or does not exist anymore. The trait also appends new children after their siblings (weight) and invalidates the cache tag of the parent when a child is saved or deleted.

In some cases, we want to make sure that a child entity is properly defined. In that case the `ChildEntityEnsureTrait` can be utilized. The `ChildEntityListBuilder` amends the base methods and is extended for the list builders of the respective entities in the academy.

The `child_entities.hierarchy` service resolves the chain of child entity types, e.g. the ancestors of an entity type or its origin entity type. Use it instead of walking the `parent` keys manually.

### Child Entity Forms

**Files:** `Form/ChildEntityForm`

Forms of child entities should extend `ChildEntityForm`. It takes the parent of a new child entity from the route parameter named after the parent entity type. Entities that are created elsewhere, e.g. through REST or in code, have to pass the parent explicitly.

### Cascade Delete

**Files:** `Hook/ChildEntityHooks`

If a child entity type sets `cascade_delete = TRUE` in its annotation, its entities are deleted when their parent is deleted. This also applies to deletions through the storage and to grandchildren.

### Child Entity Access Handling

**Files:** `ChildEntityAccessControlHandler` `Event/ChildEntityAccessEvent`

Governs access for child entities. Note that the handler should be defined in the entity annotations and that the entity type needs an admin permission. At the moment we take the approach that access gets inherited by the origin entity (may be the grand parent entity). Therefore, the handler asks the access control handler of the origin entity type through its public `access()` and `createAccess()` methods. Further, the admin permission is respected.

Other modules can alter the result with the `ChildEntityAccessEvent`, which is dispatched with the access result of the origin entity. For example, the progress module only reveals the contents of a course once a user is enrolled.

### Computed child references field

**Files:** `Plugin/Field/ComputedChildEntityReferenceFieldItemList`

A computed entity reference field item list that lists the children of a parent entity, sorted by weight. Parent modules attach it as a computed base field, e.g. `lectures` on `course` and `paragraphs` on `lecture`.

### Build routes

**Files:** see `/Routing`, `Controller/ChildEntityController` and `child_entities.services.yml`

The `ChildContentEntityHtmlRouteProvider` adds the upcasting of all ancestors to the routes of a child entity, and the `ChildContentTranslationRouteSubscriber` does the same for the content translation routes. The `ChildEntityController` provides the add page, which adds the parent route parameters to the bundle links, and the title of the edit form.

### Provide route contexts

**Files:** see `/Context` and `child_entities.services.yml`

The `ChildEntityRouteContext` provides the parent entities of the current route as contexts. The `ChildEntityRouteContextTrait` provides the parent entity from the current route to classes that cannot inject it.

## Tests

The tests live in `tests/`. The `child_entities_test` module defines a chain of test entity types, which the Kernel tests use. Run them with `phpunit.ddev.xml`.

## Todos

- Track issue https://www.drupal.org/node/2053415 for dependency injection in TypedData plugins.
