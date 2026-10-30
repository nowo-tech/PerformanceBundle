# Security — Performance Bundle

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Admin UI guard (REQ-UI-002)](#admin-ui-guard-req-ui-002)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [CSRF (REQ-SEC-005)](#csrf-req-sec-005)
- [Dependencies](#dependencies)
- [Security audits (REQ-SEC-004)](#security-audits-req-sec-004)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

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
| Unauthorized dashboard access | `security.access_roles` / custom `PerformanceAccessCheckerInterface`; Symfony `access_control`; never expose anonymously in production (`allow_unauthenticated: false`). |
| Misconfigured empty role list opens the dashboard | An empty `access_roles` / `dashboard.roles` list denies (`ConfigurablePerformanceAccessChecker`, controller fallback); with the dashboard enabled, `PerformanceExtension` refuses to compile it unless `access_checker` / `allow_unauthenticated` is set. |
| CSRF on dashboard mutations when the host disables form CSRF | `CsrfProtectionPass` fails compilation (fail closed) when the dashboard is enabled without `security.csrf.token_manager` / `form.type_extension.csrf` or with `framework.form.csrf_protection: false`. |
| SQL injection | Use Doctrine parameterized queries; validate sort/filter parameters. |
| SSRF via webhook URLs | Only allow webhook URLs from trusted configuration (env), not from end-user POST bodies. |
| Information leakage in exports | Restrict export actions to trusted roles; avoid exporting secrets from request attributes. |
| DoS via large exports or queries | Route and access-record exports capped by `export.max_rows` (default **5000**, max **50000** for BC); use pagination and infrastructure timeouts. |
| Compile-time switches silently enabled by `%env()%` | `web_ui.enabled` (and the deprecated `dashboard.enabled`) and `security.allow_unauthenticated` must be literal booleans; an env placeholder fails the container build (an unresolved placeholder string used to count as `true`). |
| Malformed query parameters | Malformed date filters (`date_from`, `date_to`, `start_date`, `end_date`) are ignored instead of raising a 500. |
| SSRF via notification webhooks | `WebhookNotificationChannel` allows HTTPS only and rejects localhost / private / link-local destinations. |

## Admin UI guard (REQ-UI-002)

The dashboard (default path `/performance`) must not be public in production.

**Default configuration:**

```yaml
nowo_performance:
    security:
        access_roles: [ROLE_ADMIN]
        access_checker: null
        allow_unauthenticated: false
```

Requirements:

1. Install `symfony/security-bundle` when the dashboard is enabled and `allow_unauthenticated` is `false` (otherwise container load throws `LogicException`).
2. Configure firewalls and `access_control` for the dashboard path:

```yaml
security:
    access_control:
        - { path: ^/performance, roles: ROLE_ADMIN }
```

3. Optionally provide a custom `access_checker` service implementing `PerformanceAccessCheckerInterface`.

**BC:** `dashboard.roles` is still accepted and mapped into `security.access_roles` when `access_roles` is not set explicitly. Prefer `security.access_roles`.

Setting `allow_unauthenticated: true` is supported for local demos only.

## Secrets and cryptography

- **Webhook URLs and API tokens** for Slack/email providers must be in **environment variables** or Symfony secrets, not committed files.
- Do not log full webhook payloads if they can contain tokens.

## Logging

- Log performance events without storing **passwords**, **session IDs**, or **full credit card** data in metric tables.
- Bundle diagnostics use the injected PSR-3 `logger` (REQ-OBS-001): tracking decisions and metric recording at `debug` (only when `enable_logging: true`), notification delivery failures and blocked webhook URLs at `warning`. Messages carry route names, environments, timings and exception messages (an HttpClient or mailer error message may name the target host) — never request bodies, headers, cookies, session ids or credentials; keep secrets out of webhook URLs (use headers).

## CSRF (REQ-SEC-005)

Every state-changing dashboard action (`clear`, `{id}/delete`, `{id}/review`, `purge-records`, `delete-records-by-filter`) is a POST route backed by a Symfony FormType, so the form CSRF token is validated by the Form component; an invalid token aborts the action and redirects to the dashboard without following a foreign `Referer`. Because those form types define their own CSRF options, a host without the form CSRF extension would silently skip the check, so `DependencyInjection\Compiler\CsrfProtectionPass` refuses to compile the container when `dashboard.enabled` is true and `security.csrf.token_manager` / `form.type_extension.csrf` is missing or `framework.form.csrf_protection` is false (`symfony/security-csrf` is a hard dependency). Read-only pages and exports (dashboard, statistics, access records, chart data, CSV/JSON exports, diagnose) are GET and change nothing.

## Dependencies

- Run `composer audit` in consuming applications and when releasing the bundle.
- Keep Symfony and Doctrine dependencies updated.

## Security audits (REQ-SEC-004)

| Date | Scope | Grade | Reference | Outcome |
| --- | --- | --- | --- | --- |
| 2026-10-30 | PR #70 (3.15.0: UiKit page contract, `web_ui` keys, table commands) | Pass (good) / Low | Appendix BP of the nowo-tech bundles security analysis | No request-path regression. L1 (`web_ui.enabled` cast to bool at compile time, so `%env(bool:…)%` could not disable the dashboard) fixed in 3.15.0: literal booleans only, also for `allow_unauthenticated` (I1). Malformed dates no longer 500 (I3). `.gitattributes` keeps demo / tests / tooling out of the dist (I4). Residual: access-record retention is host-owned (`purge-records`). |
| 2026-10-29 | 3.13.0 | — | Appendix AZ | L1–L4 fixed in 3.14.0 (redaction of tokens in stored referers / params, CSV formula guard, referer links, bounded chart window). |

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
| **Permissions / exposure** | Dashboard and export routes require `access_roles` or custom checker; `allow_unauthenticated` is `false` by default. |
| **Limits / DoS** | `export.max_rows` defaults to 5000 (configurable up to 50000). |
| **AI security audit (REQ-SEC-004)** | Latest recorded grade in the nowo-tech bundles security analysis catalog (`BUNDLES_SECURITY_ANALYSIS.md`); re-run when the dashboard, exports or notification channels change. |

Record confirmation in the release PR or tag notes.
