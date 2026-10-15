# Backup outcome / timestamp patch (v1.2.2 trial branch)

This branch is not an upstream release. The agent identifies itself as `0.14.0-fix1`.

## Behavior

- A source archive report is `running`, not proof of a remote copy.
- Snapshot all enabled selected + mandatory storage IDs when a new archive is reported.
- Only all targets acknowledged as uploaded makes the archive successful. One failed target fails the archive; absent acknowledgements remain running. No destination fails closed.
- Upload errors and acknowledgement errors propagate to the self-backup command.
- Notify on upload outcome transitions, not on initial archive creation. Repeated reports do not erase upload outcomes or duplicate notifications.
- Normalize offset-bearing report timestamps to UTC before saving in the UTC application database. Do not change the global database timezone.

## Verification

- Agent: `go test ./...` (includes no target, missing target, partial failure, transfer error, acknowledgement error and success).
- Backend: `php tests/outcome-smoke.php` in an isolated container with existing Composer dependencies. The script forces an in-memory SQLite connection before migration, with a throwaway encryption key. It covers outcome aggregation, notifications, retries, idempotency and timezone conversion.
- The additional `Dockerfile.outcome-fix` layers the backend patch onto an existing local v1.2.2 trial image; it is not a universal installer.

## Boundaries

No automatic historical timestamp rewrite: old records need provenance before correction. The nullable target snapshot preserves legacy compatibility. Acknowledged upload is not a substitute for periodic restore/hash verification. Network loss can leave an unacknowledged job running; a production rollout still requires timeout/staleness alerting and independently tested notification delivery. This change does not add durable retention for failed self-backup temporary archives, nor automatic retry policy.

Take a consistent database snapshot and preserve the old agent binary, image and deployment configuration before applying. Do not blindly restore the snapshot over new user data to roll back code; the added nullable column is backward-compatible.
