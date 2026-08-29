# nethandle-ui Changelog

## 2026-08-29

- Added standalone PHP web service for `nethandle <target> on|off|status`.
- Added token authentication and strict target/action validation.
- Added Apache and sudoers deployment examples.
- Added a read-only status smoke test.
- Reworked README around the minimal gateway-first implementation.

## 2026-03-22

- Added initial planning baseline for `nethandle-web` architecture.
- Documented non-breaking migration strategy from script-embedded device config to shared config.
- Documented required security controls for web-to-terminal execution.
- Documented planned API surface (`/api/nethandle/*`) and Google Home bridge direction.
