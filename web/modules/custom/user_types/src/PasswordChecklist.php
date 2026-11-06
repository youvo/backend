<?php

namespace Drupal\user_types;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The live checklist of password rules below a new password.
 *
 * The mail door and the creative registration let a visitor choose a
 * password. Both list the rules of the password policies of the role the
 * account gets, and js/password-checklist.js ticks them off while typing.
 * The script expects the password_confirm element under the key "password".
 * The server still checks every rule; rules without a German text here are
 * only left out of the list.
 */
class PasswordChecklist {

  /**
   * Constructs a PasswordChecklist object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds the checklist of the rules for a role.
   *
   * Without JavaScript it is a plain list; the script ticks the rules off
   * while typing and holds the submit button back until all are met.
   *
   * @param string $role
   *   The role whose password policies apply to the new password.
   *
   * @return array
   *   The render array of the checklist.
   */
  public function build(string $role): array {
    $rules = $this->rules($role);
    return [
      '#theme' => 'item_list',
      '#items' => array_map(static fn (array $rule) => [
        '#markup' => $rule['label'],
        '#wrapper_attributes' => ['data-rule' => $rule['id']],
      ], $rules),
      '#attributes' => ['class' => ['password-checklist'], 'id' => 'password-checklist'],
      '#attached' => [
        'library' => ['user_types/password_checklist'],
        'drupalSettings' => ['passwordChecklist' => ['rules' => $rules]],
      ],
    ];
  }

  /**
   * Lists the password rules of a role for the checklist.
   *
   * @param string $role
   *   The role whose password policies apply.
   *
   * @return array<int, array<string, int|string>>
   *   The rules, each with an id and a label, and the values the script
   *   checks.
   */
  public function rules(string $role): array {

    $rules = [];
    $policies = $this->entityTypeManager->getStorage('password_policy')->loadMultiple();
    foreach ($policies as $policy) {
      /** @var \Drupal\password_policy\PasswordPolicyInterface $policy */
      if (!in_array($role, $policy->getRoles(), TRUE)) {
        continue;
      }
      foreach ($policy->getConstraints() as $constraint) {
        $length = (int) ($constraint['character_length'] ?? 0);
        if ($constraint['id'] === 'password_length' && $constraint['character_operation'] === 'minimum') {
          $min = max($length, $rules['length_min']['min'] ?? 0);
          $rules['length_min'] = [
            'id' => 'length_min',
            'label' => sprintf('Mindestens %d Zeichen', $min),
            'min' => $min,
          ];
        }
        elseif ($constraint['id'] === 'password_length' && $constraint['character_operation'] === 'maximum') {
          $max = min($length, $rules['length_max']['max'] ?? PHP_INT_MAX);
          $rules['length_max'] = [
            'id' => 'length_max',
            'label' => sprintf('Höchstens %d Zeichen', $max),
            'max' => $max,
          ];
        }
      }
    }
    $rules['match'] = ['id' => 'match', 'label' => 'Beide Passwörter stimmen überein'];

    return array_values($rules);
  }

  /**
   * Names the two password fields in German, as the rest of the page.
   *
   * An #after_build callback of a password_confirm element. The site has no
   * translations, so core's titles would stay English.
   */
  public static function germanPasswordTitles(array $element): array {
    $element['pass1']['#title'] = 'Passwort';
    $element['pass2']['#title'] = 'Passwort wiederholen';
    return $element;
  }

}
