<?php

namespace Drupal\projects\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\image\ImageDerivativeUtilities;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the files of a project result in their order, each with a preview.
 *
 * Images show a preview and an address for the lightbox, both in image
 * styles that image_style_warmer generates up front. Other files show their
 * type instead of a preview.
 */
#[FieldFormatter(
  id: 'project_result_files',
  label: new TranslatableMarkup('Result files'),
  field_types: ['weighted_file'],
)]
class ResultFilesFormatter extends FileFormatterBase {

  /**
   * The entity type manager, to load the image styles.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The image derivative utilities, for the options of the image styles.
   */
  protected ImageDerivativeUtilities $imageDerivativeUtilities;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->imageDerivativeUtilities = $container->get(ImageDerivativeUtilities::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'preview_style' => 'teaser',
      'lightbox_style' => 'content',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $styles = $this->imageDerivativeUtilities->styleOptions(FALSE);
    $form['preview_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style of the preview'),
      '#options' => $styles,
      '#default_value' => $this->getSetting('preview_style'),
      '#required' => TRUE,
    ];
    $form['lightbox_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style of the lightbox'),
      '#options' => $styles,
      '#default_value' => $this->getSetting('lightbox_style'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Preview: @style', ['@style' => $this->getSetting('preview_style')]),
      $this->t('Lightbox: @style', ['@style' => $this->getSetting('lightbox_style')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $storage = $this->entityTypeManager->getStorage('image_style');
    $lightbox_style = $storage->load($this->getSetting('lightbox_style'));

    $elements = [];
    /** @var \Drupal\file\FileInterface $file */
    foreach ($this->getEntitiesToView($items, $langcode) as $file) {
      if (!$file instanceof FileInterface) {
        continue;
      }
      $item = $file->_referringItem;
      $description = trim((string) $item->get('description')->getValue());
      $image = str_starts_with((string) $file->getMimeType(), 'image/');
      $elements[] = [
        '#theme' => 'project_result_item',
        '#url' => $file->createFileUrl(),
        '#label' => $description !== '' ? $description : $file->getFilename(),
        '#name' => $description !== '' ? $file->getFilename() : NULL,
        '#preview' => $image ? [
          '#theme' => 'image_style',
          '#style_name' => $this->getSetting('preview_style'),
          '#uri' => $file->getFileUri(),
          '#alt' => '',
        ] : NULL,
        '#kind' => mb_strtoupper(pathinfo($file->getFilename(), PATHINFO_EXTENSION)),
        '#lightbox' => $image && $lightbox_style instanceof ImageStyleInterface ? $lightbox_style->buildUrl($file->getFileUri()) : NULL,
        '#weight' => (int) $item->get('weight')->getValue(),
        '#cache' => ['tags' => $file->getCacheTags()],
      ];
    }
    usort($elements, static fn (array $a, array $b): int => $a['#weight'] <=> $b['#weight']);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $storage = $this->entityTypeManager->getStorage('image_style');
    foreach (['preview_style', 'lightbox_style'] as $setting) {
      $style = $storage->load($this->getSetting($setting));
      if ($style instanceof ImageStyleInterface) {
        $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
      }
    }
    return $dependencies;
  }

}
