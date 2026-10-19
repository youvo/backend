<?php

namespace Drupal\Tests\youvo\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601;
use Drupal\serialization\Normalizer\DateTimeIso8601Normalizer;
use Drupal\Tests\UnitTestCase;
use Drupal\youvo\Normalizer\DateOnlyDateTimeIso8601Normalizer;

/**
 * Tests the denormalization of date-only fields.
 *
 * @coversDefaultClass \Drupal\youvo\Normalizer\DateOnlyDateTimeIso8601Normalizer
 * @group youvo
 */
class DateOnlyDateTimeIso8601NormalizerTest extends UnitTestCase {

  /**
   * Denormalizes a value for a field with the given datetime type.
   */
  protected function denormalize(string $value, string $datetime_type): mixed {
    $config_factory = $this->getConfigFactoryStub([
      'system.date' => ['timezone' => ['default' => 'Europe/Berlin']],
    ]);
    $normalizer = new DateOnlyDateTimeIso8601Normalizer(
      new DateTimeIso8601Normalizer($config_factory),
      $config_factory,
    );
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getSetting')->with('datetime_type')->willReturn($datetime_type);
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getFieldDefinition')->willReturn($field_definition);
    return $normalizer->denormalize($value, DateTimeIso8601::class, NULL, ['target_instance' => $item]);
  }

  /**
   * Tests that date-only fields accept dates and datetimes.
   *
   * @covers ::denormalize
   * @dataProvider provideDates
   */
  public function testDateOnly(string $value, string $expected): void {
    $this->assertSame($expected, $this->denormalize($value, 'date'));
  }

  /**
   * Data provider for date-only values.
   */
  public static function provideDates(): array {
    return [
      'date' => ['2026-10-29', '2026-10-29'],
      'midnight in Berlin' => ['2026-10-28T23:00:00+0000', '2026-10-29'],
      'midnight in Berlin (summer time)' => ['2026-07-14T22:00:00+00:00', '2026-07-15'],
      'with offset' => ['2026-10-29T00:00:00+01:00', '2026-10-29'],
    ];
  }

  /**
   * Tests that datetime fields are not affected.
   *
   * @covers ::denormalize
   */
  public function testDatetimeFieldIsUnchanged(): void {
    $this->assertSame('2026-10-28T23:00:00', $this->denormalize('2026-10-28T23:00:00+0000', 'datetime'));
  }

}
