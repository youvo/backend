<?php

/**
 * @file
 * Sends every transactional mail variation to Mailpit for review.
 *
 * Development only. Run with:
 *   ddev drush php:script scripts/send-test-mails.php.
 *
 * Clears the Mailpit inbox first, then calls the mailers with projects and
 * users from the dummy content. Mails sent for a project are recorded in its
 * correspondence log, like real ones.
 */

use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;

$etm = \Drupal::entityTypeManager();
$transition = \Drupal::service(ProjectTransitionMailerInterface::class);
$action = \Drupal::service(ProjectActionMailerInterface::class);
$user_mailer = \Drupal::service(YouvoUserMailerInterface::class);
$manager_mailer = \Drupal::service(ManagerMailerInterface::class);

// Clear the Mailpit inbox (Mailpit runs in the web container).
@file_get_contents('http://127.0.0.1:8025/api/v1/messages', FALSE, stream_context_create(['http' => ['method' => 'DELETE']]));

$results = [];
$run = static function (string $label, callable $send) use (&$results): void {
  try {
    $results[$label] = $send() ? 'sent' : 'FAILED';
  }
  catch (\Throwable $e) {
    $results[$label] = 'ERROR: ' . $e->getMessage();
  }
};

$projects = array_filter(
  $etm->getStorage('project')->loadMultiple(),
  static fn (ProjectInterface $p) => $p->getOwner() instanceof Organization && $p->getOwner()->getEmail(),
);
$find = static function (callable $condition) use ($projects): ?ProjectInterface {
  foreach ($projects as $project) {
    if ($condition($project)) {
      return $project;
    }
  }
  return NULL;
};
$users = $etm->getStorage('user')->loadMultiple();
$creatives = array_values(array_filter($users, static fn ($u) => $u instanceof Creative && $u->getEmail()));
$organization = current(array_filter($users, static fn ($u) => $u instanceof Organization && $u->getEmail())) ?: NULL;

$any = reset($projects);
// The complete mails link to feedback, which only a completed project takes,
// and only from people who have not finished their feedback yet. The dummy
// content has no project without any finished feedback, so the owner and at
// least one creative who is no staff must still be open.
$feedback_storage = $etm->getStorage('feedback');
$completed = $find(static function (ProjectInterface $p) use ($feedback_storage): bool {
  if (!$p->lifecycle()->isCompleted() || !$p->getParticipants('Creative')) {
    return FALSE;
  }
  $done = [];
  foreach ($feedback_storage->loadByProperties(['project' => $p->id()]) as $feedback) {
    if ($feedback->isCompleted()) {
      $done[(int) $feedback->getOwnerId()] = TRUE;
    }
  }
  if (isset($done[(int) $p->getOwnerId()])) {
    return FALSE;
  }
  foreach ($p->getParticipants('Creative') as $uid => $creative) {
    if (!isset($done[(int) $uid]) && !$creative->hasPermission('access site')) {
      return TRUE;
    }
  }
  return FALSE;
});
$applied = $find(static fn (ProjectInterface $p) => $p->hasApplicant());

// Project transitions.
$run('transition: submit (manager + organization)', static fn () => $transition->submit($any));
$run('transition: publish', static fn () => $transition->publish($any));
$run('transition: mediate (organization + creatives + staff)', static fn () => $transition->mediate($any, array_slice($creatives, 0, 2)));
$run('transition: complete (organization + participants)', static fn () => $completed ? $transition->complete($completed) : throw new \RuntimeException('No completed project with participants.'));

// Project actions.
$applicant = $applied ? (current($applied->getApplicants()) ?: $creatives[0]) : $creatives[0];
$run('action: apply (organization + creative)', static fn () => $action->apply($applied ?? $any, $applicant, "Hallo,\nich würde gerne mitmachen.", '+49 170 1234567'));
$run('action: notify', static fn () => $action->notify($any));
$run('action: invite', static fn () => $action->invite($any));

// User mails.
$run('user: register creative', static fn () => $user_mailer->registerCreative($creatives[0], 'https://youvo.ddev.site/user/reset/0/0/test'));
$run('user: organization created (staff)', static fn () => $organization ? $user_mailer->notifyOrganizationCreated($organization) : throw new \RuntimeException('No organization.'));
$run('user: confirm organization proposal', static fn () => $organization ? $user_mailer->confirmOrganizationProposal($organization) : throw new \RuntimeException('No organization.'));

// Manager mails: reminders, declines and archives. Each is offered only in
// some project states, so use the first project with a preview. The dummy
// content may lack an open project without applicants, so add an unsaved copy
// of an open project with its applicants removed.
if ($open = $find(static fn (ProjectInterface $p) => $p->lifecycle()->isOpen())) {
  $projects[] = (clone $open)->setApplicants([]);
}
$definitions = (new \ReflectionClass($manager_mailer))->getAttributes(MailerInfo::class)[0]->newInstance()->sub_defs;
foreach (array_keys($definitions) as $subtype) {
  $run("manager: $subtype", static function () use ($projects, $manager_mailer, $subtype): bool {
    foreach ($projects as $project) {
      if ($preview = $manager_mailer->preview($project, $subtype)) {
        $sender = $project->getOwner()->getManager() ?? \Drupal::currentUser();
        return $manager_mailer->send($project, $subtype, $preview->subject, $preview->body, $sender);
      }
    }
    throw new \RuntimeException('No project in a state that offers it.');
  });
}

foreach ($results as $label => $result) {
  printf("%-60s %s\n", $label, $result);
}
echo "\nhttps://youvo.ddev.site:8026\n";
