<?php

namespace Drupal\stats\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\stats\StatsCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for stats pages.
 */
class StatsController implements ContainerInjectionInterface {

  /**
   * Construct stats overview controller with services.
   */
  public function __construct(protected StatsCalculator $statsCalculator) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('stats.calculator'));
  }

  /**
   * Controls overview.
   */
  public function overview(): array {

    $page['creatives'] = $this->statsCalculator->countCreatives();
    $page['organizations'] = $this->statsCalculator->countOrganizations();
    $page['open_projects'] = $this->statsCalculator->countOpenProjects();
    $page['ongoing_projects'] = $this->statsCalculator->countOngoingProjects();
    $page['completed_projects'] = $this->statsCalculator->countCompletedProjects();
    $page['mediated_projects'] = $this->statsCalculator->countMediatedProjects();

    return [
      '#theme' => 'stats-overview',
      '#page' => $page,
    ];
  }

}
