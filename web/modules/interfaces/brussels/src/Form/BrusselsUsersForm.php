<?php

namespace Drupal\brussels\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\creatives\Entity\Creative;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The brussels users form.
 *
 * @internal
 */
final class BrusselsUsersForm extends FormBase {

  /**
   * Constructs a new BrusselsUsersForm object.
   */
  public function __construct(
    protected Client $httpClient,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('http_client'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'brussels_users_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Min. UID + 1000'),
      '#description' => $this->t('Search for UIDs minimum this plus 1000.'),
      '#default_value' => 0,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Migrate'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = $this->makeRequest(intval($form_state->getValue('min')));
    $batch = [
      'title' => $this->t('Migrating users.'),
      'operations' => [],
      'init_message' => $this->t('Migrate process is starting.'),
      'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => $this->t('The process has encountered an error.'),
      'finished' => [
        '\Drupal\brussels\Form\BrusselsUsersForm',
        'migrateUsersFinished',
      ],
    ];
    foreach ($data as $item) {
      $batch['operations'][] = [
        ['\Drupal\brussels\Form\BrusselsUsersForm', 'migrateUser'],
        [$item],
      ];
    }
    batch_set($batch);
  }

  /**
   * Migrate a user in batch.
   */
  public static function migrateUser($item, &$context) {
    $account = Creative::create([
      'uid' => $item['account']['uid'],
      'name' => $item['account']['mail'],
      'mail' => $item['account']['mail'],
      'created' => $item['account']['created'],
      'access' => $item['account']['access'],
      'login' => $item['account']['login'],
      'init' => $item['account']['init'],
      'roles' => ['creative'],
      'status' => $item['account']['status'],
    ]);
    // Do nothing for now.
    $account->id();
    $context['results'][] = $item['account']['name'];
    $context['message'] = t('Created @title', ['@title' => $item['account']['name']]);
  }

  /**
   * Batch finished messages.
   */
  public static function migrateUsersFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(\Drupal::translation()->formatPlural(
        count($results),
        'One user migrated.', '@count users migrated.'
      ));
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'));
    }
  }

  /**
   * Makes request.
   *
   * @throws \Exception
   */
  protected function makeRequest($min) {

    // Check configuration.
    if (empty($this->config('oauth_grant_remote.settings')->get('jwt_expiration')) ||
      empty($this->config('oauth_grant_remote.settings')->get('jwt_key_path')) ||
      empty($this->config('oauth_grant_remote.settings')->get('auth_relay_url'))) {
      $this->messenger()->addError('Auth Relay is not configured. Check the OAuth Grant Remote settings form.');
      return [];
    }

    $path = $this->config('oauth_grant_remote.settings')
      ->get('jwt_key_path');
    $key_path = 'file://' . $path;
    $key = InMemory::file($key_path);
    $config = Configuration::forSymmetricSigner(new Sha512(), $key);
    $config->setValidationConstraints(new LooseValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get()))));

    // Build the JWT.
    $expiry = $this->config('oauth_grant_remote.settings')
      ->get('jwt_expiration');
    $state = bin2hex(random_bytes(16));
    $builder = $config->builder()
      ->issuedAt(new \DateTimeImmutable('@' . $this->time->getCurrentTime()))
      ->issuedBy('youvo.localhost')
      ->expiresAt(new \DateTimeImmutable('@' . ($this->time->getCurrentTime() + $expiry)))
      ->withClaim('state', $state)
      ->withClaim('min', $min);
    $jwt = $builder->getToken($config->signer(), $config->signingKey())->toString();

    try {
      $endpoint = $this->config('oauth_grant_remote.settings')
        ->get('auth_relay_url') . '/api/brussels/user';
      $response = $this->httpClient
        ->post($endpoint, ['json' => ['jwt' => $jwt, 'timeout' => 60]]);
    }
    catch (ClientException $e) {
      $this->messenger()
        ->addError($e->getMessage());
      return [];
    }

    // Decode the response and parse the received JWT.
    $relay_response = json_decode($response->getBody());

    // If the Auth Relay does not deliver a JWT, there was no valid session
    // found on the host site, and we have to log in the user on the original
    // host.
    if (!isset($relay_response->jwt)) {
      $this->messenger()
        ->addError('No JWT response.');
      return [];
    }

    try {
      // Parse JWT.
      /** @var \Lcobucci\JWT\Token\Plain $remote_jwt */
      $remote_jwt = $config->parser()->parse($relay_response->jwt);
    }
    catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound) {
      $this->messenger()
        ->addError('Error decoding response.');
      return [];
    }

    // Validate JWT message.
    $constraints = $config->validationConstraints();
    if (!$config->validator()->validate($remote_jwt, ...$constraints)) {
      $this->messenger()
        ->addError('Unable to validate response.');
      return [];
    }

    // Get the claims delivered by Auth Relay.
    $remote_claims = $remote_jwt->claims()->all();

    // Check if the state was exchanged correctly.
    if ($remote_claims['state'] != $state) {
      $this->messenger()
        ->addError('State response is wrong.');
      return [];
    }

    return $remote_claims['data'];
  }

}
