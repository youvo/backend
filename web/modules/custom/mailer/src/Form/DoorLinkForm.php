<?php

namespace Drupal\mailer\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Door\DoorLinkGenerator;
use Drupal\mailer\Door\DoorTarget;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes door links for any project, user and target without a mail.
 *
 * A link can log its recipient in, so it is shown only in the response that
 * made it. It is kept in a temporary form state value and added after the
 * form was cached, so neither the cached form nor its state holds it.
 */
class DoorLinkForm extends FormBase {

  /**
   * The temporary form state key of the made link.
   */
  const string RESULT = 'mailer_door_link';

  /**
   * Constructs a DoorLinkForm object.
   */
  public function __construct(
    protected DoorLinkGenerator $generator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Creates the form with its services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(DoorLinkGenerator::class),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Gets the form ID.
   */
  public function getFormId(): string {
    return 'mailer_door_link_form';
  }

  /**
   * Builds the fields and the suggestions of the chosen project.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['#cache']['max-age'] = 0;

    // Filled after the form was cached, see static::addResult().
    $form['result'] = [
      '#type' => 'container',
      '#after_build' => ['::addResult'],
    ];

    $form['project'] = [
      '#type' => 'door_link_tags',
      '#kind' => 'project',
      '#title' => $this->t('Projekt'),
      '#required' => TRUE,
    ];
    $form['recipient'] = [
      '#type' => 'door_link_tags',
      '#kind' => 'recipient',
      '#title' => $this->t('Empfänger'),
      '#required' => TRUE,
    ];
    $options = [];
    foreach (DoorTarget::cases() as $target) {
      $options[$target->value] = $target->heading();
    }
    $form['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Ziel'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['subject'] = [
      '#type' => 'door_link_tags',
      '#kind' => 'subject',
      '#title' => $this->t('Bewerbung (Kreative)'),
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => DoorTarget::Applicant->value],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Link erzeugen'),
        '#button_type' => 'primary',
      ],
      'suggest' => [
        '#type' => 'submit',
        '#value' => $this->t('Vorschläge zeigen'),
        '#limit_validation_errors' => [['project']],
        '#submit' => ['::submitSuggest'],
      ],
    ];

    $project_id = $form_state->get('suggestions');
    $project = $project_id ? $this->entityTypeManager->getStorage('project')->load($project_id) : NULL;
    if ($project instanceof ProjectInterface) {
      $form['suggestions'] = $this->buildSuggestions($project);
    }

    return $form;
  }

  /**
   * Builds the list of suggested links of a project.
   */
  protected function buildSuggestions(ProjectInterface $project): array {

    $element = [
      '#type' => 'details',
      '#title' => $this->t('Vorschläge für %project', ['%project' => $project->getTitle()]),
      '#open' => TRUE,
    ];

    $suggestions = $this->generator->suggestions($project);
    if ($suggestions === []) {
      $element['empty'] = [
        '#markup' => $this->t('Für diesen Projektstand gibt es keine Vorschläge.'),
      ];
      return $element;
    }

    $element['list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Empfänger'), $this->t('Ziel'), $this->t('Ergebnis'), ''],
    ];
    // The applicant rows differ only in the creative, so it is named.
    $applicants = [];
    foreach ($project->getApplicants() as $applicant) {
      $applicants[$applicant->uuid()] = $applicant->getDisplayName();
    }
    foreach ($suggestions as $delta => $suggestion) {
      $target = $suggestion['target']->heading();
      if ($suggestion['target'] === DoorTarget::Applicant) {
        $target = 'Bewerbung: ' . ($applicants[$suggestion['subject']] ?? $suggestion['subject']);
      }
      $element['list'][$delta] = [
        'recipient' => ['#plain_text' => $suggestion['recipient']->getDisplayName()],
        'target' => ['#plain_text' => $target],
        'outcome' => ['#plain_text' => $this->generator->outcome($project, $suggestion['recipient'], $suggestion['target'], $suggestion['subject'])],
        'generate' => [
          '#type' => 'submit',
          '#value' => $this->t('Link erzeugen'),
          '#name' => 'suggestion_' . $delta,
          '#limit_validation_errors' => [['project']],
          '#submit' => ['::submitSuggestion'],
          '#door_link' => [
            'project' => (int) $project->id(),
            'recipient' => (int) $suggestion['recipient']->id(),
            'target' => $suggestion['target']->value,
            'subject' => $suggestion['subject'],
          ],
        ],
      ];
    }

    return $element;
  }

  /**
   * Shows the made link above the fields.
   *
   * Runs after the unprocessed form was cached. The read-only field has no
   * name, so the link is not posted back.
   */
  public function addResult(array $element, FormStateInterface $form_state): array {

    $result = $form_state->getTemporaryValue(static::RESULT);
    if ($result === NULL) {
      return $element;
    }

    $element['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link'),
      '#id' => 'edit-door-link',
      '#value' => $result['url'],
      '#maxlength' => NULL,
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $element['outcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $result['outcome'],
    ];
    $element['hint'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Öffne den Link in einem privaten Fenster. Solange du angemeldet bist, meldet die Tür nicht an.'),
    ];

    return $element;
  }

  /**
   * Requires a creative for the applicant target.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#parents'] ?? []) !== ['submit']) {
      return;
    }
    if ($form_state->getValue('target') !== DoorTarget::Applicant->value) {
      return;
    }
    $subject = $form_state->getValue('subject');
    if (!$subject || !$this->entityTypeManager->getStorage('user')->load($subject) instanceof Creative) {
      $form_state->setErrorByName('subject', $this->t('Wähle die Kreative oder den Kreativen der Bewerbung.'));
    }
  }

  /**
   * Makes the link of the chosen project, user and target.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $this->entityTypeManager->getStorage('project')->load($form_state->getValue('project'));
    /** @var \Drupal\user\UserInterface $recipient */
    $recipient = $this->entityTypeManager->getStorage('user')->load($form_state->getValue('recipient'));
    $target = DoorTarget::from($form_state->getValue('target'));

    // The hidden subject field still posts, so only the applicant target signs
    // a subject.
    $subject = '';
    if ($target === DoorTarget::Applicant) {
      $subject = (string) $this->entityTypeManager->getStorage('user')->load($form_state->getValue('subject'))->uuid();
    }

    $this->generateLink($form_state, $project, $recipient, $target, $subject);
  }

  /**
   * Shows the suggestions of the chosen project.
   */
  public function submitSuggest(array &$form, FormStateInterface $form_state): void {
    $form_state->set('suggestions', (int) $form_state->getValue('project'));
    $form_state->setRebuild();
  }

  /**
   * Makes the link of a suggestion.
   *
   * The button carries its project, so the link matches the shown list.
   */
  public function submitSuggestion(array &$form, FormStateInterface $form_state): void {

    $door_link = $form_state->getTriggeringElement()['#door_link'];
    $project = $this->entityTypeManager->getStorage('project')->load($door_link['project']);
    $recipient = $this->entityTypeManager->getStorage('user')->load($door_link['recipient']);
    if (!$project instanceof ProjectInterface || !$recipient instanceof UserInterface) {
      $this->messenger()->addError($this->t('Das Projekt oder der Empfänger existiert nicht mehr.'));
      $form_state->setRebuild();
      return;
    }

    $this->generateLink($form_state, $project, $recipient, DoorTarget::from($door_link['target']), $door_link['subject']);
  }

  /**
   * Makes a link and keeps it for this response only.
   */
  protected function generateLink(FormStateInterface $form_state, ProjectInterface $project, UserInterface $recipient, DoorTarget $target, string $subject): void {
    $url = $this->generator->generate($project, $recipient, $target, $subject, $this->currentUser);
    $form_state->setTemporaryValue(static::RESULT, [
      'url' => $url,
      'outcome' => $this->generator->outcome($project, $recipient, $target, $subject),
    ]);
    $form_state->set('suggestions', (int) $project->id());
    $form_state->setRebuild();
  }

}
