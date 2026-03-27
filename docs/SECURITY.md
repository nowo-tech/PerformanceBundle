# Security — Performance Bundle

## Scope

This Symfony bundle **tracks route performance metrics** (timing, database query counts, optional memory and status codes), may persist data to a **database**, and can expose a **dashboard**, **exports**, and **notifications** (e.g. email, Slack, webhooks). It must be configured with appropriate **access control** and used with **least privilege** in production.

## Attack surface

- **HTTP requests** to dashboard and export endpoints: authenticated users with configured roles; potential for unauthorized access if misconfigured.
- **Database**: stored metrics; queries must use ORM/repository patterns without raw concatenation of user input into SQL.
- **Notifications**: outbound HTTP to webhooks, Slack, email—URLs and tokens must come from **secure configuration**, not from unvalidated user input.
- **CSV/JSON export**: large responses; risk of **data exposure** if roles are too permissive.

## Threats and mitigations

| Threat | Mitigation |
|--------|------------|
| Unauthorized dashboard access | Configure `required_role` / Symfony `access_control`; never expose the dashboard anonymously in production. |
| SQL injection | Use Doctrine parameterized queries; validate sort/filter parameters. |
| SSRF via webhook URLs | Only allow webhook URLs from trusted configuration (env), not from end-user POST bodies. |
| Information leakage in exports | Restrict export actions to trusted roles; avoid exporting secrets from request attributes. |
| DoS via large exports or queries | Use pagination, limits, and infrastructure timeouts. |

## Secrets and cryptography

- **Webhook URLs and API tokens** for Slack/email providers must be in **environment variables** or Symfony secrets, not committed files.
- Do not log full webhook payloads if they can contain tokens.

## Logging

- Log performance events without storing **passwords**, **session IDs**, or **full credit card** data in metric tables.

## Dependencies

- Run `composer audit` in consuming applications and when releasing the bundle.
- Keep Symfony and Doctrine dependencies updated.

## Reporting a vulnerability

If you discover a security issue, report it **responsibly**:

- **Do not** open a public GitHub issue with exploit details.
- Email the maintainers (see `composer.json` or repository security policy) with reproduction steps and impact.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current. |
| **`.gitignore` and `.env`** | `.env` ignored; demos document `.env.example` only. |
| **No secrets in repo** | No webhook URLs with embedded secrets, no SMTP passwords in YAML. |
| **Recipe / Flex** | Defaults safe; no production secrets in recipe. |
| **Input / output** | Dashboard filters validated; exports authorized; Twig escaping in UI. |
| **Dependencies** | `composer audit` clean or triaged. |
| **Logging** | No secrets in application logs from bundle code paths. |
| **Cryptography** | HTTPS/TLS for outbound calls is the deployer’s responsibility; document. |
| **Permissions / exposure** | Dashboard and export routes require appropriate roles. |
| **Limits / DoS** | Export size and query limits documented where applicable. |

Record confirmation in the release PR or tag notes.
