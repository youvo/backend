<?php

namespace Drupal\academy;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Alters services for academy use.
 */
class AcademyServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Overwrite class for jsonapi_include.parse service.
    if ($container->hasDefinition('jsonapi_include.parse')) {
      $definition = $container->getDefinition('jsonapi_include.parse');
      $definition->setClass('Drupal\academy\AcademyJsonapiParse');
    }
  }

}
