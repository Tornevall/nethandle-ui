# nethandle-web (planning baseline)

Web UI and API bridge for controlling internet access per user/device using the existing `nethandle` and `run-nethandle` scripts.

## Goal

Build a secure web-to-terminal control surface in Tools without breaking current router-side script workflows.

- Keep original scripts intact:
  - `F:/LOCAL-DEV/system/usr/local/tornevall/nethandle`
  - `F:/LOCAL-DEV/system/etc/firewall/gateway/run-nethandle`
- Move device/user config out of script hardcoding into shared config files over time.
- Expose controlled API endpoints for web, Android, and Google Home trigger paths.

## Current script facts (as analyzed)

- `nethandle` is the operator command surface (`<target> on|off [strict]`, or status).
- `run-nethandle` applies firewall chains per user from resolver state files.
- Resolver data currently lives in `system/etc/resolver`:
  - `iplist.conf` (source domains)
  - `iplist.resolved` (resolved CIDRs)
  - `state-*` (per-user state)

## Proposed architecture (phase-by-phase)

1. **Execution adapter (Tools backend)**
   - New service layer in Tools wraps script calls.
   - No raw shell concatenation from user input.
   - Allowlist targets/actions only.

2. **Shared config migration (non-breaking)**
   - New config file for user/device mapping.
   - Keep old scripts runnable while adding support for reading shared config.
   - Keep `run-nethandle` path and behavior for router trigger compatibility.

3. **API contract (`/api/nethandle/*`)**
   - `GET /api/nethandle/status`
   - `POST /api/nethandle/targets/{target}/on`
   - `POST /api/nethandle/targets/{target}/off`
   - `POST /api/nethandle/targets/{target}/on-strict`
   - Strict auth/permission gate and audit logging.

4. **Web UI (`projects/nethandle-ui`)**
   - Device cards with real-time status.
   - Per-device and per-user action buttons.
   - Safety UX: confirm dialogs, command preview, last operation log.

5. **Google Home bridge**
   - Google Home API endpoints in Tools call same nethandle service layer.
   - No duplicate shell execution path in Google-specific controllers.

## Security requirements (must-have)

- Permission gate on all mutating actions.
- Server-side allowlist for target and action.
- Timeout and fail-closed behavior for command execution.
- Audit trail of actor, command intent, exit code, and result.
- Optional execution lock to avoid concurrent conflicting runs.

## Environment/config scaffold added in Tools

The following env entries are now present for the upcoming implementation:

- `NETHANDLE_EXEC_ENABLED`
- `NETHANDLE_EXEC_BIN`
- `NETHANDLE_APPLY_FW_BIN`
- `NETHANDLE_RESOLVER_BASE`
- `NETHANDLE_EXEC_TIMEOUT`
- `NETHANDLE_EXEC_SUDO`

And existing Google push entries for mobile notifications:

- `GOOGLE_HOME_API_BASE_URL`
- `GOOGLE_API_KEY`
- `GOOGLE_HOMEGRAPH_BEARER`
- `GOOGLE_HOME_TIMEOUT`
- `GOOGLE_FCM_SERVER_KEY`
- `GOOGLE_FCM_ENDPOINT`

## Next implementation milestone

- Build minimal `NethandleService` + `NethandleApiController` in Tools.
- Add readonly status endpoint first.
- Add one mutating endpoint (`off`) with strict guard and logging.
- Wire first UI card in this project to the status endpoint.

