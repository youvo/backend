<?php

namespace Drupal\jsonapi_obscurity\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Requirements for the JSON:API Obscurity module.
 */
class JsonApiObscurityRequirements {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(param: 'jsonapi_obscurity.prefix')]
    protected string $prefix,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {

    $requirements['jsonapi_obscurity']['title'] = $this->t('JSON:API Obscurity');
    if (empty($this->prefix)) {
      $requirements['jsonapi_obscurity']['description'] = $this->t('Please set the parameter %parameter in the file %file.', [
        '%parameter' => 'jsonapi_obscurity.prefix',
        '%file' => 'sites/default/services.yml',
      ]);
      $requirements['jsonapi_obscurity']['severity'] = RequirementSeverity::Error;
      $requirements['jsonapi_obscurity']['value'] = $this->t('Obscurity prefix not defined!');
    }
    else {
      $requirements['jsonapi_obscurity']['value'] = $this->t('Obscurity prefix is defined.');
    }

    return $requirements;
  }

}
