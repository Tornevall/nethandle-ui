# nethandle-ui Changelog

## 2026-09-02

- Changed the read-only `status` action to execute bare `nethandle`, matching the command's actual status interface.
- Added machine-readable parsing of user mode, profile, and devices to status responses.
- Made `target` optional for status requests while keeping it mandatory for `on` and `off`.
- Updated the smoke test to request full read-only status without a target.

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
