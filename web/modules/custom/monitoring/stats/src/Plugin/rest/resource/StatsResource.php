<?php

namespace Drupal\stats\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponseInterface;
use Drupal\stats\StatsCalculator;
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
   * The stats calculator service.
   */
  protected StatsCalculator $statsCalculator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->statsCalculator = $container->get('stats.calculator');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponseInterface {

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
