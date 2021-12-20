# Academy module

---

## Summary

---

The `academy` module is the root module for the `academy` package. It defines
base functionalities that are inherited throughout the dependents.

## Tasks

---

### Overwrite JSON:API parsing

**Files:** `AcademyJsonapiParse.php` `AcademyServiceProvider.php`

We want to change the behavior of the JSON:API output. Here the content is
parsed before being delivered to the client.

Therefore, we extend the functionality of the `jsonapi_include` module. It
already uses the parser to merge the output of the included entities (as
requested ?include=something by the client) into the output of the referencing
entities.

The service `jsonapi_include.parse` is overwritten in
`AcademyServiceProvider` to call the extending class `AcademyJsonapiParse`.
Therein, we accomplish the following:

- Merge the includes of references from the same entity type with different
  bundles and ensure their sorting by weight.
- Ensure consistent array types for empty outputs.
- Beautify some output _problems_ stemming from resources with cacheable
  items and multi-value fields.

**Considerations:**

- Probably needs to move up to the `youvo` module, when project handling is
  added.
- The maintenance of the `jsonapi_include` module is poor, and we should
  consider forking the project.

---

### Provide fields with cache abilities

**Files:** see `Plugin/Datatype` and `Plugin/Field/FieldType`

We want to alter the caching behavior of computed fields attached to academy
entities. In our case that mainly means setting the cache maximum age to
zero. The current Drupal approach is attach the cache information to the
data type. Therefore, we import the `RefinableCacheableDependencyTrait`, see
e.g. `CacheableBoolean.php`. This works very well, unless the delivered
result is empty - then we alter the output, see above.

**Considerations:**
- See follow-ups on issue https://www.drupal.org/project/drupal/issues/2997123.
- Maybe needs to move up to the `youvo` module if projects use computed fields.

---

### Translation handler for academy entities

We introduce the `AcademyTranslationHandler` to modify the display of
translation-related buttons on the content entity forms.

Further, note that some translation-related routes have to be manually
inherited for academy entities, since they do not have a canonical route,
see `academy.module`. This is essentially a derivative of the method in the
`content_translation` module. The routes are modified further in the
`child_entities` module in `ChildContentTranslationRouteSubscriber` to provide
entity-related route contexts.

**Considerations:**
- Track the issue https://www.drupal.org/node/2155787 for access handling of
  the content translation routes.

---

### Miscellaneous tasks

- Provides the interface `AcademicFormatInterface` to identify academy entities.
- Provides the logger channel `academy`, see `academy.services.yml`.
- Provides `hideweightbutton.js` to hide the weight button on sortable lists,
  see `academy.libraries.yml`.
- Provides `language.css` to alter layout of administrative forms respecting
  different languages, see `academy.module` and `academy.libraries.yml`.
