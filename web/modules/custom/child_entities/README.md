# Child Entities module

## Summary

The `child_entities` module introduces a parent-child relationship model to establish the references between academy content entities. It is a fork of the Drupal project https://www.drupal.org/project/child_entity. We altered and extended the behavior of the contributed module since it is sparsely maintained and does not cover all of our custom scenarios.

## Academy data structure

The academy package introduces the content entities `Course`, `Lecture`, `Paragraph` and `Question`. They follow a parent-/ child relationship, where we have the following descendants:

- `Course` -> `Lecture` -> `Paragraph` -> `Question`

A general overview of the entity structure can be found here: [Entities Chart](https://whimsical.com/youvo-academy-E89MFfEmpwiW8QgGqWwiZu).

## Basic usage

A child entity should extend the `ChildEntityInterface`, define the entity keys `parent` and `weight` in the annotations and include the `ChildEntityTrait`. The base field definitions should initialise the child entity base fields. The `parent` key must equal the parent's entity type ID, which is also the name of the route parameter.

```php
/**
 * @ContentEntityType(
 *   id = "child",
 *   handlers = {
 *     "form" = {"add" = "Drupal\child_entities\Form\ChildEntityForm"},
 *     "route_provider" = {"html" = "Drupal\child_entities\Routing\ChildContentEntityHtmlRouteProvider"},
 *   },
 *   cascade_delete = TRUE,
 *   entity_keys = {"parent" = "parent_type", "weight" = "weight", ...},
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

**Files:** `ChildEntityInterface` `ChildEntityTrait` `ChildEntityEnsureTrait` `ChildEntityListBuilder`

The `ChildEntityInterface` and `ChildEntityTrait` define and provide the basic functionality of a child entity, e.g. getting the referenced parent entity. In some cases, we want to make sure that a child entity is properly defined. In that case the `ChildEntityEnsureTrait` can be utilized. The `ChildEntityListBuilder` amends the base methods and is extended for the list builders of the respective entities in the academy.

### Child Entity Access Handling

**Files:** `ChildEntityAccessControlHandler`

Governs access for child entities. Note that the handler should be defined in the entity annotations. At the moment we take the approach that access gets inherited by the origin entity (may be the grand parent entity). Further, admin and editor permissions are respected. Note the hook `child_entities_check_access` that may alter the access result.

### Computed child references field

**Files:** `ComputedChildEntitityReferenceFieldItemList`

### Build routes

**Files:** see `/Routing` and `child_entities.services.yml`

### Provide route contexts

**Files:** see `/Context` and `child_entities.services.yml`

## Todos

- Track issue https://www.drupal.org/node/2053415 for dependency injection in TypedData plugins.
