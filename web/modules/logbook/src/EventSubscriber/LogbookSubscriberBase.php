<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\logbook\Entity\LogEvent;
use Drupal\logbook\LogEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a base class for logbook subscribers.
 */
abstract class LogbookSubscriberBase implements EventSubscriberInterface {

  const EVENT_CLASS = NULL;
  const LOG_PATTERN = NULL;

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
  public function createLog(): ?LogEventInterface {
    if (static::LOG_PATTERN === NULL) {
      $this->logger->error('Log event subscriber does not define log pattern.');
      return NULL;
    }
    try {
      $log_patterns = $this->entityTypeManager
        ->getStorage('log_pattern')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('Unable to load log patterns.');
      return NULL;
    }
    if (!in_array(static::LOG_PATTERN, array_map(fn($p) => $p->id(), $log_patterns))) {
      $this->logger->error('Log pattern does not exist (%id).', ['%id' => static::LOG_PATTERN]);
      return NULL;
    }
    return LogEvent::create([
      'type' => static::LOG_PATTERN,
    ]);
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
        ->error('Log event subscriber does not define event class.');
      return [];
    }
    return [static::EVENT_CLASS => 'log'];
  }

}
