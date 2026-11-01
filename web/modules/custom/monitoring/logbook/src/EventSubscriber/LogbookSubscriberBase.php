<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\logbook\LogPatternInterface;
use Drupal\projects\ProjectInterface;
use Drupal\youvo\Event\TimestampedEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a base class for logbook subscribers.
 */
abstract class LogbookSubscriberBase implements EventSubscriberInterface {

  const EVENT_CLASS = NULL;
  const LOG_PATTERN = NULL;
  const PRIORITY = 0;

  /**
   * Constructs a LogbookSubscriberBase object.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected TimeInterface $time,
  ) {}

  /**
   * Creates log with log pattern.
   */
  public function createLog(Event $event): ?LogInterface {
    if (static::LOG_PATTERN === NULL) {
      $this->logger->error('Logbook event subscriber does not define log pattern.');
      return NULL;
    }
    try {
      $log_pattern = $this->entityTypeManager
        ->getStorage('log_pattern')
        ->load(static::LOG_PATTERN);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('Unable to load log pattern (%id).', ['%id' => static::LOG_PATTERN]);
      return NULL;
    }
    if (!$log_pattern instanceof LogPatternInterface) {
      $this->logger->error('Log pattern does not exist (%id).', ['%id' => static::LOG_PATTERN]);
      return NULL;
    }
    if (!$log_pattern->isEnabled()) {
      return NULL;
    }
    $timestamp = $event instanceof TimestampedEventInterface ? $event->getTimestamp() : NULL;
    return Log::create([
      'type' => static::LOG_PATTERN,
      'created' => $timestamp ?? $this->time->getCurrentTime(),
    ]);
  }

  /**
   * Archives or re-activates the logs of a project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param bool $archived
   *   TRUE archives the active logs, FALSE re-activates the archived logs.
   * @param int $from
   *   Only logs from this id on.
   */
  protected function setProjectLogsArchived(ProjectInterface $project, bool $archived = TRUE, int $from = 0): void {
    try {
      $log_storage = $this->entityTypeManager->getStorage('log');
      $log_ids = $log_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('project', $project->id())
        ->condition('status', (int) $archived)
        ->condition('id', $from, '>=')
        ->execute();
      /** @var \Drupal\logbook\LogInterface $log */
      foreach ($log_storage->loadMultiple($log_ids) as $log) {
        $log->setArchived($archived);
        $log->save();
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('Unable to update the logs of project %id', ['%id' => $project->id()]);
    }
  }

  /**
   * Writes log during event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  abstract public function log(Event $event): void;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (static::EVENT_CLASS === NULL) {
      \Drupal::logger('logbook')
        ->error('Logbook event subscriber does not define event class.');
      return [];
    }
    return [static::EVENT_CLASS => ['log', static::PRIORITY]];
  }

}
