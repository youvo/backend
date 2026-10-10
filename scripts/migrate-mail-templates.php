<?php

/**
 * @file
 * Converts mailer.transactional_email configs to mailer_policy YAML files.
 *
 * Reads the old transactional_email config entities from the database and
 * writes one policy file per template to config/sync. It never deletes
 * anything. Policies are generated one category (base tag) at a time:
 *
 * @code
 * ddev drush php:script scripts/migrate-mail-templates.php -- mailer.project_transition
 * ddev exec CATEGORY=mailer.project_action drush php:script scripts/migrate-mail-templates.php
 * @endcode
 *
 * The category is the first script argument or the CATEGORY environment
 * variable. Valid categories are the base tags in ID_MAP:
 * mailer.project_transition, mailer.project_action and mailer.user. Without a
 * category the script lists them and writes nothing.
 */

use Drupal\Core\Serialization\Yaml;

const TOKEN_MAP = [
  '%Contact' => 'contact',
  '%TitleProject' => 'project_title',
  '%Manager' => 'manager',
  '%Link' => 'link',
  '%Name' => 'name',
  '%NameCreative' => 'creative_name',
  '%Message' => 'message',
  '%LinkCreative' => 'creative_link',
  '%EmailCreative' => 'creative_email',
  '%PhoneCreative' => 'creative_phone',
  '%NameOrganization' => 'organization_name',
  '%ContactOrganization' => 'organization_contact',
  '%AddressOrganization' => 'organization_address',
  '%EmailOrganization' => 'organization_email',
  '%PhoneOrganization' => 'organization_phone',
  '%EmailManager' => 'manager_email',
  '%Participants' => 'participants',
  '%FeedbackLink' => 'feedback_link',
  '%ResultsLink' => 'results_link',
  '%SelectedCreatives' => 'selected_creatives',
];

// Old id => [base_tag, subtype].
const ID_MAP = [
  'project_submit_manager' => ['mailer.project_transition', 'submit_manager'],
  'project_submit_organization' => ['mailer.project_transition', 'submit_organization'],
  'project_publish_organization' => ['mailer.project_transition', 'publish_organization'],
  'project_mediate_organization' => ['mailer.project_transition', 'mediate_organization'],
  'project_mediate_creative' => ['mailer.project_transition', 'mediate_creative'],
  'project_complete_organization' => ['mailer.project_transition', 'complete_organization'],
  'project_complete_creative' => ['mailer.project_transition', 'complete_creative'],
  'project_apply_organization' => ['mailer.project_action', 'apply_organization'],
  'project_apply_creative' => ['mailer.project_action', 'apply_creative'],
  'project_notify_organization' => ['mailer.project_action', 'notify_organization'],
  'project_invite_organization' => ['mailer.project_action', 'invite_organization'],
  'user_register_creative' => ['mailer.user', 'register_creative'],
];

/**
 * Replaces tokens with Twig variables.
 */
function convert_tokens(string $text, bool $html): string {
  // Longest tokens first so %NameCreative is not hit by %Name.
  $map = TOKEN_MAP;
  uksort($map, static fn ($a, $b) => strlen($b) <=> strlen($a));
  foreach ($map as $token => $variable) {
    if ($variable === 'message') {
      $replacement = '{{ message|nl2br }}';
    }
    elseif ($html && str_ends_with($variable, 'link')) {
      $replacement = '<a href="{{ ' . $variable . ' }}">{{ ' . $variable . ' }}</a>';
    }
    else {
      $replacement = '{{ ' . $variable . ' }}';
    }
    $text = str_replace($token, $replacement, $text);
  }
  return $text;
}

/**
 * Converts plain text to paragraphs and line breaks.
 */
function to_html(string $text): string {
  // The old bodies are plain text: escape them before the tokens are turned
  // into Twig variables. Tokens start with "%" and are not affected; quotes and
  // umlauts stay as they are.
  $text = htmlspecialchars($text, ENT_NOQUOTES);
  $text = str_replace("\r\n", "\n", $text);
  $paragraphs = preg_split("/\n{2,}/", trim($text));
  $out = '';
  foreach ($paragraphs as $paragraph) {
    // The old bodies have stray whitespace at line ends.
    $lines = array_map('trim', explode("\n", $paragraph));
    $out .= '<p>' . implode('<br>', array_filter($lines, static fn ($l) => $l !== '')) . '</p>';
  }
  return $out;
}

$categories = array_values(array_unique(array_column(ID_MAP, 0)));
$category = $extra[0] ?? (getenv('CATEGORY') ?: '');
if (!in_array($category, $categories, TRUE)) {
  print "Pass one category (script argument or CATEGORY env variable): " . implode(', ', $categories) . "\n";
  return;
}

foreach (ID_MAP as $old => [$base, $sub]) {
  if ($base !== $category) {
    continue;
  }
  $entity = \Drupal::entityTypeManager()->getStorage('transactional_email')->load($old);
  if (!$entity) {
    print "Missing old template: $old\n";
    continue;
  }
  $policy = [
    'langcode' => 'de',
    'status' => TRUE,
    'dependencies' => ['module' => ['mailer']],
    'id' => "$base.$sub",
    'configuration' => [
      'email_subject' => ['value' => convert_tokens($entity->subject(), FALSE)],
      'email_body' => ['content' => ['value' => convert_tokens(to_html($entity->body()), TRUE), 'format' => 'email_html']],
    ],
  ];
  $file = DRUPAL_ROOT . "/../config/sync/mailer_policy.mailer_policy.$base.$sub.yml";
  file_put_contents($file, Yaml::encode($policy));
  print "Wrote $file\n";
}
