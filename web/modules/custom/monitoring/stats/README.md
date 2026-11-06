# Stats

The staff stats page at `/stats` (permission `view statistics`) and the
public stats REST resource.

## Daily cron

The active users come from a daily snapshot in the `stats_activity` table.
`hook_cron()` writes the snapshot of yesterday once per local day, from the
last access time of each user (`users.access`).

- Cron must run every day, for example `drush cron` from a crontab.
  `automated_cron` is not enabled.
- A day without a cron run after midnight is lost for good: the users table
  keeps only the last access, so past days cannot be recorded later.
- The status report warns when the last recorded day is more than two days
  ago, or when no day is recorded while the table has rows.

## Approximation

The snapshots are approximate:

- A user counts as active since a start when the last access is not before
  it. So a user who visited only today, before cron ran, counts for yesterday
  too.
- Drupal updates `users.access` at most every few minutes, not on every
  request.
- The value of a running week, month, quarter or year covers the days up to
  yesterday.

## Legacy import

After the users are migrated, import the history of the active users once
from the legacy access log (`youvolog_access`):

```
drush stats:import-legacy-activity --database=migrate
```

- The import computes the days from the legacy visits and never overwrites a
  day that a live snapshot already wrote.
- The legacy counts include staff members with the creative role. The live
  snapshots leave staff creatives out (`ActivitySnapshots::STAFF_ROLES`), so
  expect a small step at the switch-over.

## Deploy check

`stats_update_11002()` installs the `charts` and `charts_chartjs` modules
before the configuration import. Run `drush updb` once against a copy of a
database that has the stats module without the charts modules before the
first deploy to such a site.
