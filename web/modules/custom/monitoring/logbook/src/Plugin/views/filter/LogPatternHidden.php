<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Excludes hidden log patterns unless a checkbox is set.
 *
 * Views drops a filter whose exposed input is empty. This filter accepts every
 * input, so that an unchecked box still excludes the hidden patterns.
 */
#[ViewsFilter('log_pattern_hidden')]
class LogPatternHidden extends FilterPluginBase implements ContainerFactoryPluginInterface {

  use LogPatternFilterTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value']['default'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show hidden logs'),
      '#default_value' => (bool) $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('Hide hidden logs');
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }
    $this->value = !empty($input[$this->options['expose']['identifier']]);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (!empty($this->value)) {
      return;
    }
    $this->restrictTypes($this->matcher->visibleIds());
  }

}
