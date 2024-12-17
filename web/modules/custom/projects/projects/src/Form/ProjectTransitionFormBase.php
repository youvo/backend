<?php

namespace Drupal\projects\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a base class for project action forms.
 */
abstract class ProjectTransitionFormBase extends FormBase {

  /**
   * Constructs a ProjectActionFormBase object.
   */
  public function __construct(protected EventDispatcherInterface $eventDispatcher) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('event_dispatcher'));
  }

}
