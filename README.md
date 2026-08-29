# nethandle-ui

Minimal gateway-side web bridge for the existing `nethandle` command.

The first implementation is intentionally independent of Laravel and ToolsAPI. Apache/PHP receives a small authenticated request and executes the already existing operator command:

```text
nethandle <target> on
nethandle <target> off
nethandle <target> status
```

The PHP service contains no firewall logic.

## API

The service exposes one endpoint, `public/index.php`.

- Method: `POST`
- Authentication header: `X-API-TOKEN`
- Body: JSON or normal form data
- Fields:
  - `target`: user/device target passed to nethandle
  - `action`: `on`, `off`, or `status`

Example:

```bash
curl --request POST \
  --header 'X-API-TOKEN: replace-me' \
  --header 'Content-Type: application/json' \
  --data '{"target":"thomas","action":"off"}' \
  https://gateway.example.net/
```

Successful response:

```json
{
  "ok": true,
  "target": "thomas",
  "action": "off",
  "exit_code": 0,
  "output": []
}
```

## Validation

The endpoint deliberately has a very small input surface.

- Only POST is accepted.
- `NETHANDLE_API_TOKEN` must be configured server-side.
- The request token is compared using `hash_equals()`.
- Targets are limited to `A-Z`, `a-z`, `0-9`, `_`, `.`, and `-`, maximum 64 characters.
- Actions are hard-whitelisted to `on`, `off`, and `status`.
- All command arguments are shell-escaped before execution.

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
```

The defaults in the PHP endpoint are:

```text
NETHANDLE_BIN=/usr/local/bin/nethandle
NETHANDLE_SUDO_BIN=/usr/bin/sudo
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

Then verify execution from the Apache account:

```bash
sudo -u www-data sudo /usr/local/bin/nethandle thomas status
```

## Smoke test

After deployment:

```bash
NETHANDLE_API_TOKEN='your-token' \
NETHANDLE_TEST_TARGET='thomas' \
BASE_URL='https://gateway.example.net/' \
bash tests/smoke.sh
```

The smoke test intentionally only calls `status` so running it cannot disable a connection.

## Next step

Build the Android client as a similarly small application that stores the gateway URL/token and calls this endpoint directly for `on`, `off`, and `status`.
