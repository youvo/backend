<?php

namespace Drupal\stats\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\stats\Activity\ActivityAggregator;
use Drupal\stats\Activity\ActivitySnapshots;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands of the stats module.
 */
class StatsCommands extends DrushCommands {

  /**
   * The legacy role ids mapped to the activity roles.
   */
  const array LEGACY_ROLES = [
    3 => 'creative',
    4 => 'organization',
    9 => 'prospect',
  ];

  public function __construct(
    protected ActivitySnapshots $activitySnapshots,
    protected Connection $database,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Creates the commands with their services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('stats.activity_snapshots'),
      $container->get('database'),
      $container->get('config.factory'),
    );
  }

  /**
   * Imports the daily active users from the legacy access log.
   */
  #[CLI\Command(name: 'stats:import-legacy-activity')]
  #[CLI\Option(name: 'database', description: 'The key of the database connection to the legacy site.')]
  #[CLI\Usage(name: 'drush stats:import-legacy-activity --database=migrate', description: 'Import from the migrate connection.')]
  public function importLegacyActivity(array $options = ['database' => 'migrate']): void {

    $legacy = Database::getConnection('default', $options['database']);
    $result = $legacy->select('youvolog_access', 'a')
      ->fields('a', ['uid', 'role', 'timestamp'])
      ->condition('a.role', array_keys(static::LEGACY_ROLES), 'IN')
      ->orderBy('a.timestamp')
      ->execute();
    $visits = (function () use ($result) {
      foreach ($result as $row) {
        yield [(int) $row->uid, static::LEGACY_ROLES[(int) $row->role], (int) $row->timestamp];
      }
    })();

    $timezone = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
    $rows = ActivityAggregator::aggregate($visits, $timezone);

    // Live snapshots are never overwritten.
    $stored = $this->database->select('stats_activity', 'a')
      ->fields('a', ['day'])
      ->distinct()
      ->execute()
      ->fetchCol();
    $stored = array_flip($stored);

    // One transaction, so an interrupted run leaves no partial day.
    $days = 0;
    $written = 0;
    $transaction = $this->database->startTransaction();
    try {
      foreach ($rows as $day => $roles) {
        if (isset($stored[$day])) {
          continue;
        }
        foreach ($roles as $role => $row) {
          $this->activitySnapshots->write($day, $role, $row['active_1d'], $row['active_30d']);
          $written++;
        }
        $days++;
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    $transaction->commitOrRelease();
    $this->logger()->success(dt('Wrote @rows rows for @days days.', ['@rows' => $written, '@days' => $days]));
  }

}
