# HOA Dashboard — WordPress Plugin

A WordPress plugin giving HOA members and board a shared dashboard for
reserve fund health, dues/payments, ticketing, PM oversight, calendar, and
newsletters.

**Scope of this repository:** WordPress plugin only. The iOS app (planned
next phase) lives in its own separate repository — see [Related
repositories](#related-repositories) below — so the two can be released,
versioned, and tagged independently.

## Versioning

This repo follows [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`):

- **MAJOR** — breaking changes (DB schema changes requiring migration, removed features)
- **MINOR** — new features, backward-compatible (this is what normal syncs bump)
- **PATCH** — bug fixes only, no new features

Each sync to GitHub increments MINOR by one while the plugin is pre-1.0
(`v0.1.0`, `v0.2.0`, `v0.3.0`, …). Every release is tagged and has a
matching entry in `readme.txt`'s changelog. The internal plugin constant
`HOA_DASH_VERSION` in `hoa-dashboard.php` is kept in sync with the tag.

| Tag | Plugin version | Summary |
|---|---|---|
| `v0.1.0` | 0.0.1 | Initial release: roles, Twilio 2FA, reserve health dashboard, dues/autopay, ticketing, PM audit log, calendar, newsletter |
| `v0.2.0` | 0.0.2 | Automatic page creation on activation + role-specific documentation pages |

## Installation

1. Download the latest release zip from the [Releases](../../releases) page, or clone this repo into `wp-content/plugins/hoa-dashboard`.
2. Activate the plugin in wp-admin.
3. See `readme.txt` for post-activation setup (Twilio, payment gateway, roles).

## Related repositories

- `hoa-dashboard-ios` — companion iOS app (planned; separate repo, separate versioning, not yet created).

## Development

Every commit that lands on `main` corresponds to a tagged, working build.
Feature work should branch off `main` and merge via PR before a version bump/tag.
