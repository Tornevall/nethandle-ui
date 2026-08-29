# AGENTS.md

## Scope

These rules apply to the entire repository.

## Architecture

- Keep the gateway web service independent of Laravel and ToolsAPI unless explicitly requested otherwise.
- Keep firewall and routing behavior inside the existing `nethandle` command. The web layer must only validate, authenticate, execute, and report.
- Do not introduce API version prefixes such as `v1` or `v2`.

## Security

- Never concatenate unchecked request values into shell commands.
- Keep target and action inputs server-side allowlisted or strictly validated.
- Mutating operations must be authenticated, bounded by an execution timeout, and persistently audited.
- Sudo permissions must remain narrowly scoped to the required `nethandle` executable.
- Never commit real tokens, credentials, or gateway secrets.

## Verification

- Prefer focused deterministic checks for PHP syntax, request validation, command construction, timeout behavior, and audit behavior.
- Smoke tests must not alter connectivity unless the test explicitly opts into a mutating action.
- Update README and CHANGELOG when behavior or deployment requirements change.
