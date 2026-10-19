<?php

namespace Drupal\youvo\Normalizer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Accepts full datetime values for date-only fields.
 *
 * The frontend submits the value of date inputs as a full ISO 8601 datetime
 * in the timezone of the browser, which core rejects for date-only fields. We
 * convert such a value to the date in the timezone of the site and leave
 * everything else to the decorated normalizer.
 */
class DateOnlyDateTimeIso8601Normalizer implements CacheableNormalizerInterface, DenormalizerInterface, SerializerAwareInterface {

  /**
   * Constructs a DateOnlyDateTimeIso8601Normalizer object.
   */
  public function __construct(
    protected CacheableNormalizerInterface&DenormalizerInterface&SerializerAwareInterface $inner,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setSerializer(SerializerInterface $serializer): void {
    $this->inner->setSerializer($serializer);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    return $this->inner->normalize($object, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, ?string $format = NULL, array $context = []): bool {
    return $this->inner->supportsNormalization($data, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, string $type, ?string $format = NULL, array $context = []): bool {
    return $this->inner->supportsDenormalization($data, $type, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return $this->inner->getSupportedTypes($format);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, string $type, ?string $format = NULL, array $context = []): mixed {
    $field_definition = isset($context['target_instance'])
      ? $context['target_instance']->getFieldDefinition()
      : ($context['field_definition'] ?? NULL);

    if (is_string($data) && $field_definition?->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      foreach ([\DateTime::RFC3339, \DateTime::ISO8601] as $datetime_format) {
        $datetime = \DateTime::createFromFormat($datetime_format, $data);
        $errors = \DateTime::getLastErrors();
        if ($datetime !== FALSE && empty($errors['errors']) && empty($errors['warnings'])) {
          $timezone = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
          $data = $datetime->setTimezone($timezone)->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
          break;
        }
      }
    }

    return $this->inner->denormalize($data, $type, $format, $context);
  }

}
