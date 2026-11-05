<?php

namespace Drupal\stats;

/**
 * Provides an immutable period of the stats page in the site timezone.
 *
 * The period covers [from, to). Its buckets split it by the granularity;
 * buckets at the edges are clipped to the period.
 */
class StatsPeriod {

  const string ALL = 'all';

  const string YEAR = 'year';

  const string QUARTER = 'quarter';

  const string MONTH = 'month';

  const string CUSTOM = 'custom';

  /**
   * The first day of the "all" period, the start of the old Logbuch.
   */
  const string ALL_START = '2014-01-01';

  /**
   * The earliest and latest year accepted from a query.
   */
  const int MIN_YEAR = 1970;

  const int MAX_YEAR = 2100;

  /**
   * The cached buckets.
   *
   * @var array<string, array{0: int, 1: int}>|null
   */
  protected ?array $buckets = NULL;

  /**
   * Constructs a StatsPeriod.
   */
  protected function __construct(
    protected string $type,
    protected \DateTimeImmutable $from,
    protected \DateTimeImmutable $to,
    protected StatsGranularity $granularity,
  ) {}

  /**
   * Creates a period from request query arguments.
   *
   * Reads period (all, year, quarter, month, custom), year, quarter, month and
   * from/to (Y-m-d, to inclusive). Without a period, a valid from and to
   * select a custom period. Anything invalid yields the current year.
   */
  public static function fromQuery(array $query, \DateTimeZone $timezone, \DateTimeImmutable $now): static {
    $now = $now->setTimezone($timezone);
    $current_year = (int) $now->format('Y');
    $default = static::forYear($current_year, $timezone);

    $period = $query['period'] ?? NULL;
    if ($period === NULL) {
      $period = isset($query['from']) || isset($query['to']) ? static::CUSTOM : static::YEAR;
    }
    if (!is_string($period)) {
      return $default;
    }

    if ($period === static::ALL) {
      $from = static::midnight(static::ALL_START, $timezone);
      $to = static::midnight($current_year + 1 . '-01-01', $timezone);
      return new static(static::ALL, $from, $to, StatsGranularity::Year);
    }

    if ($period === static::CUSTOM) {
      $from = static::parseDate($query['from'] ?? NULL, $timezone);
      $last = static::parseDate($query['to'] ?? NULL, $timezone);
      if (!$from || !$last || $last < $from) {
        return $default;
      }
      $to = $last->modify('+1 day');
      $days = (int) $from->diff($to)->days;
      $granularity = match (TRUE) {
        $days <= 62 => StatsGranularity::Day,
        $days <= 366 => StatsGranularity::Month,
        default => StatsGranularity::Year,
      };
      return new static(static::CUSTOM, $from, $to, $granularity);
    }

    $year = static::parseInt($query['year'] ?? $current_year, static::MIN_YEAR, static::MAX_YEAR);
    if ($year === NULL) {
      return $default;
    }
    if ($period === static::YEAR) {
      return static::forYear($year, $timezone);
    }
    if ($period === static::QUARTER) {
      $quarter = static::parseInt($query['quarter'] ?? intdiv((int) $now->format('n') - 1, 3) + 1, 1, 4);
      if ($quarter === NULL) {
        return $default;
      }
      $from = static::midnight(sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1), $timezone);
      return new static(static::QUARTER, $from, $from->modify('+3 months'), StatsGranularity::Week);
    }
    if ($period === static::MONTH) {
      $month = static::parseInt($query['month'] ?? $now->format('n'), 1, 12);
      if ($month === NULL) {
        return $default;
      }
      $from = static::midnight(sprintf('%04d-%02d-01', $year, $month), $timezone);
      return new static(static::MONTH, $from, $from->modify('+1 month'), StatsGranularity::Day);
    }
    return $default;
  }

  /**
   * Creates the period of a calendar year.
   */
  protected static function forYear(int $year, \DateTimeZone $timezone): static {
    $from = static::midnight(sprintf('%04d-01-01', $year), $timezone);
    return new static(static::YEAR, $from, $from->modify('+1 year'), StatsGranularity::Month);
  }

  /**
   * Creates a date at local midnight from a valid Y-m-d string.
   */
  protected static function midnight(string $date, \DateTimeZone $timezone): \DateTimeImmutable {
    return new \DateTimeImmutable($date . ' 00:00:00', $timezone);
  }

  /**
   * Parses a strict Y-m-d string, or returns NULL.
   */
  protected static function parseDate(mixed $value, \DateTimeZone $timezone): ?\DateTimeImmutable {
    if (!is_string($value)) {
      return NULL;
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    if (!$date || $date->format('Y-m-d') !== $value) {
      return NULL;
    }
    $year = (int) $date->format('Y');
    if ($year < static::MIN_YEAR || $year > static::MAX_YEAR) {
      return NULL;
    }
    return $date;
  }

  /**
   * Parses an integer within bounds, or returns NULL.
   */
  protected static function parseInt(mixed $value, int $min, int $max): ?int {
    if (is_int($value)) {
      $number = $value;
    }
    elseif (is_string($value) && preg_match('/^\d{1,4}$/', $value)) {
      $number = (int) $value;
    }
    else {
      return NULL;
    }
    return $number >= $min && $number <= $max ? $number : NULL;
  }

  /**
   * Gets the start of the period, inclusive.
   */
  public function from(): \DateTimeImmutable {
    return $this->from;
  }

  /**
   * Gets the end of the period, exclusive.
   */
  public function to(): \DateTimeImmutable {
    return $this->to;
  }

  /**
   * Gets the bucket size.
   */
  public function granularity(): StatsGranularity {
    return $this->granularity;
  }

  /**
   * Gets a label for headings.
   */
  public function label(): string {
    $year = $this->from->format('Y');
    return match ($this->type) {
      static::ALL => 'All time',
      static::YEAR => $year,
      static::QUARTER => 'Q' . (intdiv((int) $this->from->format('n') - 1, 3) + 1) . ' ' . $year,
      static::MONTH => $this->from->format('F Y'),
      default => $this->from->format('d.m.Y') . ' – ' . $this->to->modify('-1 day')->format('d.m.Y'),
    };
  }

  /**
   * Gets the period right before this one.
   *
   * Presets move by their own unit and a custom range by its length, keeping
   * the granularity. The "all" period has nothing before it and returns the
   * same "all" period.
   */
  public function previous(): static {
    $days = $this->from->diff($this->to)->days;
    return match ($this->type) {
      static::ALL => $this,
      static::YEAR => $this->shift('-1 year'),
      static::QUARTER => $this->shift('-3 months'),
      static::MONTH => $this->shift('-1 month'),
      default => $this->shift('-' . $days . ' days'),
    };
  }

  /**
   * Gets the period right after this one, moving like previous().
   */
  public function next(): static {
    $days = $this->from->diff($this->to)->days;
    return match ($this->type) {
      static::ALL => $this,
      static::YEAR => $this->shift('+1 year'),
      static::QUARTER => $this->shift('+3 months'),
      static::MONTH => $this->shift('+1 month'),
      default => $this->shift('+' . $days . ' days'),
    };
  }

  /**
   * Gets the same period one year earlier, keeping the granularity.
   *
   * The "all" period has no counterpart and returns the same "all" period.
   */
  public function sameLastYear(): static {
    return $this->type === static::ALL ? $this : $this->shift('-1 year');
  }

  /**
   * Creates a period of the same type moved by relative date modifiers.
   */
  protected function shift(string $modifier): static {
    return new static($this->type, $this->from->modify($modifier), $this->to->modify($modifier), $this->granularity);
  }

  /**
   * Gets the buckets keyed by label with [start, end) timestamps in order.
   *
   * Labels are Y-m-d for days, o-\WW for ISO weeks, Y-m for months and Y for
   * years. The first and last bucket are clipped to the period. PHP turns
   * numeric keys into integers, so year labels like "2026" are integer keys;
   * cast them with (string) where a string is needed.
   *
   * @return array<string, array{0: int, 1: int}>
   *   The timestamp ranges.
   */
  public function buckets(): array {
    if ($this->buckets !== NULL) {
      return $this->buckets;
    }
    $format = match ($this->granularity) {
      StatsGranularity::Day => 'Y-m-d',
      StatsGranularity::Week => 'o-\WW',
      StatsGranularity::Month => 'Y-m',
      StatsGranularity::Year => 'Y',
    };
    $buckets = [];
    $cursor = $this->from;
    while ($cursor < $this->to) {
      $day = $cursor->setTime(0, 0);
      $next = match ($this->granularity) {
        StatsGranularity::Day => $day->modify('+1 day'),
        StatsGranularity::Week => $day->modify('next monday'),
        StatsGranularity::Month => $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1)->modify('+1 month'),
        StatsGranularity::Year => $day->setDate((int) $day->format('Y'), 1, 1)->modify('+1 year'),
      };
      $end = min($next, $this->to);
      $buckets[$cursor->format($format)] = [$cursor->getTimestamp(), $end->getTimestamp()];
      $cursor = $end;
    }
    $this->buckets = $buckets;
    return $buckets;
  }

  /**
   * Gets the label of the bucket containing a timestamp.
   */
  public function bucketOf(int $timestamp): ?string {
    foreach ($this->buckets() as $label => [$start, $end]) {
      if ($timestamp >= $start && $timestamp < $end) {
        return $label;
      }
    }
    return NULL;
  }

  /**
   * Checks whether a timestamp lies in the period.
   */
  public function contains(int $timestamp): bool {
    return $timestamp >= $this->from->getTimestamp() && $timestamp < $this->to->getTimestamp();
  }

  /**
   * Gets the query arguments that rebuild this period for links.
   *
   * @return array<string, string>
   *   The arguments.
   */
  public function query(): array {
    $year = $this->from->format('Y');
    return match ($this->type) {
      static::ALL => ['period' => 'all'],
      static::YEAR => ['period' => 'year', 'year' => $year],
      static::QUARTER => [
        'period' => 'quarter',
        'year' => $year,
        'quarter' => (string) (intdiv((int) $this->from->format('n') - 1, 3) + 1),
      ],
      static::MONTH => ['period' => 'month', 'year' => $year, 'month' => $this->from->format('n')],
      default => [
        'period' => 'custom',
        'from' => $this->from->format('Y-m-d'),
        'to' => $this->to->modify('-1 day')->format('Y-m-d'),
      ],
    };
  }

}
