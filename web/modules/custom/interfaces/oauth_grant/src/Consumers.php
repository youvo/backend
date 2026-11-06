<?php

namespace Drupal\oauth_grant;

/**
 * Lists the OAuth consumers known to every environment.
 *
 * The UUIDs are fixed, so permissions and config that refer to a consumer are
 * the same on every environment. Each environment creates only the consumers
 * configured in its config/.env.consumers.<environment> file.
 */
class Consumers {

  /**
   * The consumers keyed by environment key: UUID, label and default flag.
   */
  const array KNOWN = [
    'CREATIVE_LOCAL' => ['dd9ace1c-8879-45ae-b137-9cc06b4b5821', 'Creatives (local)', FALSE],
    'CREATIVE' => ['7f56f1d5-154f-42c1-b591-45e4e2fd235e', 'Creatives', TRUE],
    'ORGANIZATION_LOCAL' => ['ff5a1e31-557c-4c17-a7f5-a4a342d3cc19', 'Organizations (local)', TRUE],
    'ORGANIZATION' => ['63b60b9e-dbbc-4214-a520-f8cf4cab65da', 'Organizations', TRUE],
  ];

}
