<?php

namespace Drupal\stats\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\stats\StatsCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides stats resource.
 *
 * @RestResource(
 *   id = "stats:public",
 *   label = @Translation("Public Stats Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/stats"
 *   }
 * )
 */
class StatsResource extends ResourceBase {

  /**
   * Constructs a ProjectActionResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\stats\StatsCalculator $statsCalculator
   *   The stat calculator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected StatsCalculator $statsCalculator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('youvo'),
      $container->get('stats.calculator')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function get() {

    $stats['creatives'] = $this->statsCalculator->countCreatives();
    $stats['organizations'] = $this->statsCalculator->countOrganizations();
    $stats['projects_open'] = $this->statsCalculator->countOpenProjects();
    $stats['projects_ongoing'] = $this->statsCalculator->countOngoingProjects();
    $stats['projects_completed'] = $this->statsCalculator->countCompletedProjects();
    $stats['projects_mediated'] = $this->statsCalculator->countMediatedProjects();
    $stats['proposals_unmanaged'] = $this->statsCalculator->countUnmanagedProposals();

    return new ModifiedResourceResponse(['data' => $stats], 200);
  }

}
