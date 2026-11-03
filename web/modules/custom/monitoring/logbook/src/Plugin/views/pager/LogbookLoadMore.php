<?php

namespace Drupal\logbook\Plugin\views\pager;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsPager;
use Drupal\views\Plugin\views\pager\SqlBase;

/**
 * Shows the first items and a button that loads more.
 *
 * Like the mini pager, it fetches one extra item to know whether to offer the
 * button, so no count query runs.
 */
#[ViewsPager(
  id: 'logbook_load_more',
  title: new TranslatableMarkup('Load more button'),
  short_title: new TranslatableMarkup('Load more'),
  help: new TranslatableMarkup('Shows the first items and a button that loads more.'),
)]
class LogbookLoadMore extends SqlBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['items_per_page'] = ['default' => 5];
    $options['step'] = ['default' => 10];
    return $options;
  }

  /**
   * Adds the number of items loaded with each click.
   *
   * @param array $form
   *   The options form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['items_per_page']['#title'] = $this->t('Items shown first');
    $form['step'] = [
      '#type' => 'number',
      '#title' => $this->t('Items loaded with each click'),
      '#default_value' => $this->options['step'],
      '#min' => 1,
      '#weight' => -5,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle(): TranslatableMarkup {
    return $this->t('@first items, @step more per click', [
      '@first' => $this->getItemsPerPage(),
      '@step' => $this->options['step'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->view->query;
    $query->setLimit($this->limit() + 1);
    $query->setOffset((int) $this->options['offset']);
  }

  /**
   * {@inheritdoc}
   */
  public function useCountQuery(): bool {
    return FALSE;
  }

  /**
   * Removes the extra item, which only tells whether there are more.
   *
   * @param array $result
   *   The result rows.
   */
  public function postExecute(&$result): void {
    // The total includes the extra item, because cached results keep only
    // the total and hasMore() needs it.
    $this->total_items = count($result);
    if ($this->hasMore()) {
      array_pop($result);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updatePageInfo(): void {
    // The pager manager builds the links of all pagers on the page from the
    // registered ones, so this one registers too. Its size keeps the current
    // page from being clamped.
    $this->pagerManager->createPager($this->getCurrentPage() + 2, 1, (int) $this->options['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function render($input): array {
    if (!$this->hasMore()) {
      return [];
    }
    $query = $this->pagerManager->getUpdatedParameters($input, (int) $this->options['id'], $this->getCurrentPage() + 1);
    // Views binds its AJAX to the links in "js-pager__items".
    return [
      '#theme' => 'item_list',
      '#items' => [
        [
          '#type' => 'link',
          '#title' => $this->t('Load more'),
          '#url' => Url::fromRoute('<none>', [], ['query' => $query]),
          '#attributes' => ['class' => ['button', 'button--small']],
        ],
      ],
      '#attributes' => ['class' => ['js-pager__items', 'logbook-load-more']],
      // Keeps the place in the list after a click.
      '#attached' => ['library' => ['logbook/load_more']],
    ];
  }

  /**
   * Whether there are more items than shown.
   */
  protected function hasMore(): bool {
    return $this->getTotalItems() > $this->limit();
  }

  /**
   * Gets the number of items to show on the current page.
   */
  protected function limit(): int {
    return $this->getItemsPerPage() + (int) $this->options['step'] * (int) $this->getCurrentPage();
  }

}
