<?php

namespace Drupal\youvo\ParamConverter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\ParamConverter\ParamConverterInterface;

/**
 * Parameter converter for replacing UUIDs with IDs.
 *
 * @see \Drupal\Core\ParamConverter\EntityConverter
 *
 * @todo Remove when https://www.drupal.org/node/2353611 lands.
 */
class UuidParamConverter extends EntityConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\ParamConverter\ParamNotConvertedException
   */
  public function convert($value, $definition, $name, array $defaults) {

    // Convert request with UUIDs to IDs by querying the database.
    if ($this->isRestRequest($defaults) && Uuid::isValid($value)) {
      $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
      try {
        $query = $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('uuid', $value)
          ->execute();
        $value = reset($query);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        // Do nothing.
      }
    }

    return parent::convert($value, $definition, $name, $defaults);
  }

  /**
   * Checks if the current route stems from a REST request.
   *
   * @param array $defaults
   *   The route defaults array.
   */
  private function isRestRequest(array $defaults) {
    $valid_methods = $defaults['_route_object']->getMethods();
    $method = substr($defaults['_route'], strrpos($defaults['_route'], ".") + 1);
    return in_array($method, $valid_methods);
  }

}
