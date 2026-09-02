# nethandle-ui Changelog

## 2026-09-02

- Added machine-readable full status from bare `nethandle` output.
- Made `target` optional for read-only status requests while keeping it mandatory for mutations.
- Normalized incoming targets to lowercase before invoking nethandle.
- Aligned default deployment paths with `/usr/local/tornevall/nethandle`.
- Updated the smoke test to request full read-only status.

## 2026-08-29

- Added standalone PHP web service for `nethandle <target> on|off|status`.
- Added token authentication and strict target/action validation.
- Added bounded command execution with configurable timeout handling.
- Added persistent JSON Lines audit records for `on` and `off` mutations.
- Added request IDs, requester IP tracking, and optional client IDs for diagnostics.
- Added Apache and sudoers deployment examples.
- Added a read-only status smoke test.
- Reworked README around the minimal gateway-first implementation.

## 2026-03-22

- Added initial planning baseline for `nethandle-web` architecture.
- Documented non-breaking migration strategy from script-embedded device config to shared config.
- Documented required security controls for web-to-terminal execution.
- Documented planned API surface (`/api/nethandle/*`) and Google Home bridge direction.
