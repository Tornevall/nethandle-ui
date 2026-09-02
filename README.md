# nethandle-ui

Minimal gateway-side web bridge for the existing `nethandle` command.

The implementation is intentionally independent of Laravel and ToolsAPI. Apache/PHP receives a small authenticated request and executes the already existing operator command.

Mutating requests execute:

```text
nethandle <target> on
nethandle <target> off
```

Status requests execute the command without arguments:

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
- Fields:
  - `action`: `on`, `off`, or `status`
  - `target`: required for `on` and `off`; optional for `status`

Mutation example:

```bash
curl --request POST \
  --header 'X-API-TOKEN: replace-me' \
  --header 'X-Client-ID: android-thomas' \
  --header 'Content-Type: application/json' \
  --data '{"target":"thomas","action":"off"}' \
  https://gateway.example.net/
```

Successful mutation response:

```json
{
  "ok": true,
  "request_id": "0123456789abcdef",
  "target": "thomas",
  "action": "off",
  "exit_code": 0,
  "timed_out": false,
  "output": []
}
```

Current status example:

```bash
curl --request POST \
  --header 'X-API-TOKEN: replace-me' \
  --header 'X-Client-ID: android-thomas' \
  --header 'Content-Type: application/json' \
  --data '{"action":"status"}' \
  https://gateway.example.net/
```

A successful status request executes bare `nethandle`, preserves the command output, and also returns parsed user state suitable for clients:

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

The endpoint deliberately has a very small input surface.

- Only POST is accepted.
- `NETHANDLE_API_TOKEN` must be configured server-side.
- The request token is compared using `hash_equals()`.
- Targets are limited to `A-Z`, `a-z`, `0-9`, `_`, `.`, and `-`, maximum 64 characters.
- Actions are hard-whitelisted to `on`, `off`, and `status`.
- `target` is required for `on` and `off`. Status runs bare `nethandle` and therefore does not require a target.
- Optional `X-Client-ID` is restricted to a small safe character set.
- Every command component is shell-escaped before execution.
- GNU `timeout` bounds every `nethandle` execution. The default deadline is 10 seconds and can be configured from 1 to 60 seconds.
- Timeout exit code 124 is returned as HTTP 504 with `timed_out: true`.

## Audit

`on` and `off` are always audited as JSON Lines records. The service writes an `intent` record before command execution and a `result` record after execution.

Records include:

- UTC timestamp
- request ID
- requester IP
- optional client ID
- target/action
- exit code and timeout state for results
- up to 50 output lines from the command

The default path is:

```text
/var/log/nethandle-api/audit.log
```

Create the directory before deployment and make it writable by the Apache account:

```bash
sudo install -d -o www-data -g www-data -m 0750 /var/log/nethandle-api
sudo touch /var/log/nethandle-api/audit.log
sudo chown www-data:www-data /var/log/nethandle-api/audit.log
sudo chmod 0640 /var/log/nethandle-api/audit.log
```

If the initial audit intent cannot be persisted, a mutation is not executed. If the result record cannot be persisted after execution, the response explicitly reports `operation_executed: true` to discourage unsafe automatic retries.

## Gateway installation

Place the repository somewhere Apache can serve, for example:

```text
/var/www/nethandle
```

Configure Apache with `deploy/apache/nethandle.conf` as a baseline. At minimum set:

```text
NETHANDLE_API_TOKEN
NETHANDLE_BIN
NETHANDLE_SUDO_BIN
NETHANDLE_TIMEOUT_BIN
NETHANDLE_TIMEOUT_SECONDS
NETHANDLE_AUDIT_LOG
```

The defaults in the PHP endpoint are:

```text
NETHANDLE_BIN=/usr/local/bin/nethandle
NETHANDLE_SUDO_BIN=/usr/bin/sudo
NETHANDLE_TIMEOUT_BIN=/usr/bin/timeout
NETHANDLE_TIMEOUT_SECONDS=10
NETHANDLE_AUDIT_LOG=/var/log/nethandle-api/audit.log
```

Use HTTPS when the endpoint is reachable over anything except a trusted isolated network.

## Sudoers

The Apache account only needs permission to execute the existing `nethandle` binary.

Example file: `deploy/sudoers/nethandle`

```text
www-data ALL=(root) NOPASSWD: /usr/local/bin/nethandle
```

Install it as `/etc/sudoers.d/nethandle` and validate before use:

```bash
visudo -cf /etc/sudoers.d/nethandle
```

Then verify read-only status execution from the Apache account:

```bash
sudo -u www-data sudo /usr/local/bin/nethandle
```

## Smoke test

After deployment:

```bash
NETHANDLE_API_TOKEN='your-token' \
BASE_URL='https://gateway.example.net/' \
bash tests/smoke.sh
```

The smoke test intentionally only calls the read-only full status action so running it cannot disable a connection.
