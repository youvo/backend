<?php

namespace Drupal\projects\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shows the links of a project result in their order.
 *
 * Only http(s) values become links, as on the profiles, and a link that leaves
 * the website is marked. Values without an address are left out.
 */
#[FieldFormatter(
  id: 'project_result_links',
  label: new TranslatableMarkup('Result links'),
  field_types: ['weighted_string'],
)]
class ResultLinksFormatter extends FormatterBase {

  /**
   * The request stack, for the address of the website.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    // Url::isExternal() is TRUE for every absolute address, also for one of
    // this site, so a link leaves the website only if it is not local.
    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request ? $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' : '';

    $elements = [];
    foreach ($items as $item) {
      $link = trim((string) $item->get('value')->getValue());
      $name = rtrim(preg_replace('{^(https?://)?(www\.)?}i', '', $link), '/');
      if ($name === '') {
        continue;
      }
      $url = preg_match('{^https?://}i', $link) ? Url::fromUri($link) : NULL;
      $description = trim((string) $item->get('description')->getValue());
      $elements[] = [
        '#theme' => 'project_result_item',
        '#url' => $url?->toString(),
        '#label' => $description !== '' ? $description : $name,
        '#name' => $description !== '' ? $name : NULL,
        '#kind' => $this->t('Link'),
        '#external' => $url && $url->isExternal() && !UrlHelper::externalIsLocal($link, $base_url),
        '#weight' => (int) $item->get('weight')->getValue(),
      ];
    }
    usort($elements, static fn (array $a, array $b): int => $a['#weight'] <=> $b['#weight']);
    if ($elements) {
      $elements[0]['#cache']['contexts'][] = 'url.site';
    }
    return $elements;
  }

}
