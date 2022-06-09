<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\logbook\Entity\LogEvent;
use Drupal\logbook\LogEventInterface;
use Drupal\logbook\LogPatternInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a base class for logbook subscribers.
 */
abstract class LogbookSubscriberBase implements EventSubscriberInterface {

  const EVENT_TYPE = NULL;

  /**
   * Constructs a LogbookSubscriberBase object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The mailer logger channel.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger
  ) {}

  /**
   * Creates log event with event type.
   */
  public function createLogEvent(): ?LogEventInterface {
    if (static::EVENT_TYPE === NULL) {
      $this->logger->error('Log event subscriber does not define event type.');
      return NULL;
    }
    try {
      $log_patterns = $this->entityTypeManager
        ->getStorage('log_pattern')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('Unable to verify log patterns for event type.');
      return NULL;
    }
    if (!in_array(static::EVENT_TYPE, array_map(fn($p) => $p->id(), $log_patterns))) {
      $this->logger->error('Log pattern does not exist for requested event type.');
      return NULL;
    }
    return LogEvent::create([
      'type' => static::EVENT_TYPE,
    ]);
  }

  /**
   * Gets the log pattern configuration entity.
   */
  protected function loadLogPattern(string $event_type): ?LogPatternInterface {
    try {
      /** @var \Drupal\logbook\LogPatternInterface|null $log_pattern */
      $log_pattern = $this->entityTypeManager
        ->getStorage('log_pattern')->load($event_type);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $log_pattern = NULL;
    }
    if (empty($log_pattern)) {
      $this->logger->error('Unable to load log pattern entity (%id).', ['%id' => $event_type]);
    }
    return $log_pattern;
  }

  /**
   * {@inheritdoc}
   */
  abstract public static function getSubscribedEvents(): array;

}
