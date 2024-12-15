<?php

namespace Drupal\youvo;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters services.
 */
class YouvoServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {

    // Overwrite class for jsonapi_include.parse service.
    if ($container->hasDefinition('jsonapi_include.parse')) {
      $definition = $container->getDefinition('jsonapi_include.parse');
      $definition->setClass(AlterJsonapiParse::class)
        ->addArgument(new Reference('event_dispatcher'))
        ->addArgument(new Reference('file_url_generator'));
    }
  }

}
