# Child Entities module

## Summary

The `child_entities` module introduces a parent-child relationship model to establish the references between academy content entities. It is a fork of the Drupal project https://www.drupal.org/project/child_entity. We altered and extended the behavior of the contributed module since it is sparsely maintained and does not cover all of our custom scenarios.

## Academy data structure

The academy package introduces the content entities `Course`, `Lecture`, `Paragraph` and `Question`. They follow a parent-/ child relationship, where we have the following descendants:

- `Course` -> `Lecture` -> `Paragraph` -> `Question`

One may consult https://whimsical.com/youvo-academy-E89MFfEmpwiW8QgGqWwiZu.

## Basic usage

A child entity should extend the `ChildEntityInterface`, define the entity key `parent` in the annotations and include the `ChildEntityTrait` . The base field definitions should initialise the child entity base fields. Also, one needs to assure that the parent is provided on creation. This can be accomplished by resolving the route context explained below in the `preCreate` hook.

```php

class ChildEntity extends EntityBase implements ChildEntityInterface {

  use ChildEntityTrait;

  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['parent_key']) // parent_key from annotation
      && $route_match = \Drupal::service('current_route_match')->getParameter('parent_key')) {
      $values['parent_key'] = $route_match;
    }
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::childBaseFieldDefinitions($entity_type);
    return $fields;
}
```

## Todos

- Track issue https://www.drupal.org/node/2053415 for dependency injection in TypedData plugins.A general overview of the entity structure can be found here:
