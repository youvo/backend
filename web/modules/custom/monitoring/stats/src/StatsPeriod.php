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
   * select a custom period; one that covers exactly a calendar year, quarter
   * or month yields that preset. An explicit custom period stays custom, so
   * the range form stays open. Anything invalid, or no period at all, yields
   * the all time period.
   */
  public static function fromQuery(array $query, \DateTimeZone $timezone, \DateTimeImmutable $now): static {
    $now = $now->setTimezone($timezone);
    $current_year = (int) $now->format('Y');
    $default = static::forAll($timezone, $now);

    $period = $query['period'] ?? NULL;
    $explicit = $period !== NULL;
    if (!$explicit) {
      $period = isset($query['from']) || isset($query['to']) ? static::CUSTOM : static::ALL;
    }
    if (!is_string($period)) {
      return $default;
    }

    if ($period === static::ALL) {
      return $default;
    }

    if ($period === static::CUSTOM) {
      $from = static::parseDate($query['from'] ?? NULL, $timezone);
      $last = static::parseDate($query['to'] ?? NULL, $timezone);
      if (!$from || !$last || $last < $from) {
        return $default;
      }
      $to = $last->modify('+1 day');
      // A bare range covering a calendar year, quarter or month is that
      // preset.
      if (!$explicit && (int) $from->format('j') === 1) {
        $month = (int) $from->format('n');
        if ($month === 1 && $to == $from->modify('+1 year')) {
          return static::forYear((int) $from->format('Y'), $timezone);
        }
        if ($month % 3 === 1 && $to == $from->modify('+3 months')) {
          return static::forQuarter($from);
        }
        if ($to == $from->modify('+1 month')) {
          return static::forMonth($from);
        }
      }
      return static::forRange($from, $to);
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
      return static::forQuarter(static::midnight(sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1), $timezone));
    }
    if ($period === static::MONTH) {
      $month = static::parseInt($query['month'] ?? $now->format('n'), 1, 12);
      if ($month === NULL) {
        return $default;
      }
      return static::forMonth(static::midnight(sprintf('%04d-%02d-01', $year, $month), $timezone));
    }
    return $default;
  }

  /**
   * Gets the period of the same kind around the period being viewed.
   *
   * The new period contains the anchor: the last day of this period, or today
   * if this period has not ended. A custom period keeps the days of this one,
   * all time running until today.
   *
   * @param string $type
   *   One of all, year, quarter, month and custom.
   * @param \DateTimeImmutable $now
   *   The current time.
   */
  public function withGranularity(string $type, \DateTimeImmutable $now): static {

    $timezone = $this->from->getTimezone();
    $today = $now->setTimezone($timezone)->setTime(0, 0);
    $anchor = min($this->to->modify('-1 day'), $today);

    $year = (int) $anchor->format('Y');
    $month = (int) $anchor->format('n');
    return match ($type) {
      static::YEAR => static::forYear($year, $timezone),
      static::QUARTER => static::forQuarter($anchor->setDate($year, $month - ($month - 1) % 3, 1)),
      static::MONTH => static::forMonth($anchor->setDate($year, $month, 1)),
      static::CUSTOM => $this->type === static::ALL
        ? static::forRange($this->from, $today->modify('+1 day'))
        : static::forRange($this->from, $this->to),
      default => static::forAll($timezone, $now),
    };
  }

  /**
   * Creates the all time period, until the end of the current year.
   */
  protected static function forAll(\DateTimeZone $timezone, \DateTimeImmutable $now): static {
    $next_year = (int) $now->setTimezone($timezone)->format('Y') + 1;
    $from = static::midnight(static::ALL_START, $timezone);
    return new static(static::ALL, $from, static::midnight($next_year . '-01-01', $timezone), StatsGranularity::Year);
  }

  /**
   * Creates a custom period, with buckets fitting its length.
   *
   * Up to a month has days, up to a quarter weeks, up to a year months and
   * anything longer years.
   */
  protected static function forRange(\DateTimeImmutable $from, \DateTimeImmutable $to): static {
    $days = (int) $from->diff($to)->days;
    $granularity = match (TRUE) {
      $days <= 31 => StatsGranularity::Day,
      $days <= 92 => StatsGranularity::Week,
      $days <= 366 => StatsGranularity::Month,
      default => StatsGranularity::Year,
    };
    return new static(static::CUSTOM, $from, $to, $granularity);
  }

  /**
   * Creates the period of a calendar year.
   */
  protected static function forYear(int $year, \DateTimeZone $timezone): static {
    $from = static::midnight(sprintf('%04d-01-01', $year), $timezone);
    return new static(static::YEAR, $from, $from->modify('+1 year'), StatsGranularity::Month);
  }

  /**
   * Creates the period of the calendar quarter starting at a date.
   */
  protected static function forQuarter(\DateTimeImmutable $from): static {
    return new static(static::QUARTER, $from, $from->modify('+3 months'), StatsGranularity::Week);
  }

  /**
   * Creates the period of the calendar month starting at a date.
   *
   * Its buckets are weeks, as 31 days would crowd the charts.
   */
  protected static function forMonth(\DateTimeImmutable $from): static {
    return new static(static::MONTH, $from, $from->modify('+1 month'), StatsGranularity::Week);
  }

  /**
   * Creates a date at local midnight from a valid Y-m-d string.
   */
  protected static function midnight(string $date, \DateTimeZone $timezone): \DateTimeImmutable {
    return new \DateTimeImmutable($date . ' 00:00:00', $timezone);
  }

  /**
   * Parses a strict Y-m-d string in the accepted years, or returns NULL.
   *
   * The range form validates its days with it, so it accepts what the query
   * accepts.
   */
  public static function parseDate(mixed $value, \DateTimeZone $timezone): ?\DateTimeImmutable {
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
   * Gets the type: all, year, quarter, month or custom.
   */
  public function type(): string {
    return $this->type;
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
   * Gets a value for every bucket, keyed by bucket.
   *
   * @return array<string, mixed>
   *   The value for each bucket.
   */
  public function emptyBuckets(mixed $value = 0): array {
    return array_fill_keys(array_keys($this->buckets()), $value);
  }

  /**
   * Gets short chart labels of the buckets, in their order.
   *
   * Days read "05.11.", ISO weeks "W45", months "Jan" and years "2026". Months
   * get a two-digit year, "Jan 26", when the period spans more than one year.
   *
   * @return string[]
   *   The labels.
   */
  public function bucketLabels(): array {
    $last_day = $this->to->modify('-1 day');
    $format = match ($this->granularity) {
      StatsGranularity::Day => 'd.m.',
      StatsGranularity::Week => '\WW',
      StatsGranularity::Month => $this->from->format('Y') === $last_day->format('Y') ? 'M' : 'M y',
      StatsGranularity::Year => 'Y',
    };
    $labels = [];
    foreach ($this->buckets() as [$start]) {
      $labels[] = (new \DateTimeImmutable('@' . $start))->setTimezone($this->from->getTimezone())->format($format);
    }
    return $labels;
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
