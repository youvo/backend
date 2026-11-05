<?php

namespace Drupal\stats\Stats;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\stats\StatsPeriod;

/**
 * Computes the stats of feedback.
 */
class FeedbackStats {

  /**
   * The feedback bundles.
   */
  const array BUNDLES = ['creative', 'organization'];

  /**
   * The suffix of the scale field names.
   */
  const string SCALE_SUFFIX = '_scale';

  /**
   * Constructs a FeedbackStats object.
   */
  public function __construct(
    protected Connection $database,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Gets the scale fields of a feedback bundle with their labels.
   *
   * @return array<string, string>
   *   The labels keyed by field name.
   */
  public function scaleFields(string $bundle): array {
    $fields = [];
    foreach ($this->entityFieldManager->getFieldDefinitions('feedback', $bundle) as $name => $definition) {
      if (str_ends_with($name, static::SCALE_SUFFIX)) {
        $fields[$name] = (string) $definition->getLabel();
      }
    }
    return $fields;
  }

  /**
   * Averages the feedback completed in the period per bundle.
   *
   * @return array<string, array{count: int, scales: array<string, ?float>, recommend: ?float}>
   *   The count, the average of each scale field and the share of positive
   *   recommendations per bundle. Averages are NULL without answers.
   */
  public function feedback(StatsPeriod $period): array {

    $result = [];
    foreach (static::BUNDLES as $bundle) {
      $ids = $this->feedbackIds($bundle, $period);
      $scales = [];
      foreach (array_keys($this->scaleFields($bundle)) as $field) {
        $average = $this->average($this->values('feedback__' . $field, $field . '_value', $ids));
        $scales[$field] = $average === NULL ? NULL : round($average, 1);
      }
      $recommend = $this->average($this->values('feedback__field_recommend_boolean', 'field_recommend_boolean_value', $ids));
      $result[$bundle] = [
        'count' => count($ids),
        'scales' => $scales,
        'recommend' => $recommend,
      ];
    }
    return $result;
  }

  /**
   * Gets the ids of the feedback of a bundle completed in the period.
   *
   * @return int[]
   *   The feedback ids of existing projects.
   */
  protected function feedbackIds(string $bundle, StatsPeriod $period): array {

    $query = $this->database->select('feedback', 'f');
    $query->innerJoin('project_field_data', 'p', 'p.id = f.project');
    $query->addField('f', 'id');
    $query->condition('f.bundle', $bundle)
      ->condition('f.completed', $period->from()->getTimestamp(), '>=')
      ->condition('f.completed', $period->to()->getTimestamp(), '<');
    return array_map('intval', array_unique($query->execute()->fetchCol()));
  }

  /**
   * Gets the answers of a field of the feedback.
   *
   * @param string $table
   *   The field table.
   * @param string $column
   *   The value column.
   * @param int[] $ids
   *   The feedback ids.
   *
   * @return int[]
   *   The answers.
   */
  protected function values(string $table, string $column, array $ids): array {

    if (!$ids) {
      return [];
    }
    return array_map('intval', $this->database->select($table, 't')
      ->fields('t', [$column])
      ->condition('t.entity_id', $ids, 'IN')
      ->condition('t.deleted', 0)
      ->isNotNull('t.' . $column)
      ->execute()
      ->fetchCol());
  }

  /**
   * Averages values, NULL without any.
   *
   * @param int[] $values
   *   The values.
   */
  protected function average(array $values): ?float {
    return $values ? array_sum($values) / count($values) : NULL;
  }

}
