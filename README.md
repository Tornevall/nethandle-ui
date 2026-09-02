# nethandle-ui

Minimal gateway-side web bridge for the existing `nethandle` command.

The implementation is intentionally independent of Laravel and ToolsAPI. Apache/PHP validates and authenticates requests, then executes the existing operator command.

Mutating requests execute:

```text
nethandle <target> on
nethandle <target> off
```

Status requests execute bare `nethandle`:

```text
nethandle
```

The PHP service contains no firewall logic.

## API

The service exposes one endpoint, `public/index.php`.

- Method: `POST`
- Authentication header: `X-API-TOKEN`
- Optional requester label: `X-Client-ID`
- Body: JSON or normal form data
- `action`: `on`, `off`, or `status`
- `target`: required for `on` and `off`, optional for `status`

Targets are normalized to lowercase before invoking `nethandle`.

Mutation example:

```bash
curl --request POST \
  --header 'X-API-TOKEN: replace-me' \
  --header 'X-Client-ID: android-nethandle' \
  --header 'Content-Type: application/json' \
  --data '{"target":"MAX","action":"off"}' \
  https://gateway.example.net/
```

Current status example:

```bash
curl --request POST \
  --header 'X-API-TOKEN: replace-me' \
  --header 'X-Client-ID: android-nethandle' \
  --header 'Content-Type: application/json' \
  --data '{"action":"status"}' \
  https://gateway.example.net/
```

A successful status request preserves the raw command output and also returns parsed user state:

```json
{
  "ok": true,
  "action": "status",
  "users": [
    {
      "name": "emily",
      "mode": "ON",
      "profile": "FULL",
      "devices": [
        {"name": "emily-main", "ip": "10.1.1.53"}
      ]
    }
  ]
}
```

## Validation and execution safety

- Only POST is accepted.
- `NETHANDLE_API_TOKEN` must be configured server-side.
- The request token is compared using `hash_equals()`.
- Targets are limited to `A-Z`, `a-z`, `0-9`, `_`, `.`, and `-`, maximum 64 characters.
- Actions are hard-whitelisted to `on`, `off`, and `status`.
- `target` is required for `on` and `off`; full status does not require one.
- Optional `X-Client-ID` is restricted to a small safe character set.
- Every command component is shell-escaped before execution.
- GNU `timeout` bounds every execution. The default deadline is 10 seconds and can be configured from 1 to 60 seconds.
- Timeout exit code 124 is returned as HTTP 504 with `timed_out: true`.

## Audit

`on` and `off` are always audited as JSON Lines records. The service writes an `intent` record before command execution and a `result` record afterwards.

Default path:

```text
/var/log/nethandle-api/audit.log
```

Create it for the Apache/PHP account:

```bash
sudo install -d -o www-data -g www-data -m 0750 /var/log/nethandle-api
sudo touch /var/log/nethandle-api/audit.log
sudo chown www-data:www-data /var/log/nethandle-api/audit.log
sudo chmod 0640 /var/log/nethandle-api/audit.log
```

If the initial audit intent cannot be persisted, a mutation is not executed.

## Gateway installation

Configure Apache with `deploy/apache/nethandle.conf` as a baseline. The defaults used by this deployment are:

```text
NETHANDLE_BIN=/usr/local/tornevall/nethandle
NETHANDLE_SUDO_BIN=/usr/bin/sudo
NETHANDLE_TIMEOUT_BIN=/usr/bin/timeout
NETHANDLE_TIMEOUT_SECONDS=10
NETHANDLE_AUDIT_LOG=/var/log/nethandle-api/audit.log
```

Use HTTPS whenever the endpoint is reachable outside a trusted isolated network.

## Sudoers

The Apache account only needs permission to execute the nethandle binary:

```text
www-data ALL=(root) NOPASSWD: /usr/local/tornevall/nethandle
```

Install it as `/etc/sudoers.d/nethandle` and validate it:

```bash
visudo -cf /etc/sudoers.d/nethandle
sudo -u www-data sudo -n /usr/local/tornevall/nethandle
```

## Smoke test

```bash
NETHANDLE_API_TOKEN='your-token' \
BASE_URL='https://gateway.example.net/' \
bash tests/smoke.sh
```

The smoke test only requests read-only full status.
