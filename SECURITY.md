# Security policy

DataDock welcomes reports from researchers and from people who run it. Please send vulnerability reports in private. A public issue, pull request, or commit discussion discloses the problem before users can update.

## How to report

Use GitHub private vulnerability reporting:

[Report a vulnerability](https://github.com/ZacharyKeatings/DataDock/security/advisories/new)

That form is the preferred path. It stays off the public issue tracker, and the maintainer can turn the report into a draft advisory and a CVE when a fix is ready.

If that page says reporting is not enabled, email the address in the repository profile and include `[DataDock security]` in the subject. Do not include exploit details in a public issue.

## What to include

- The DataDock version (`VERSION`, or the release tag) and how it is installed (web server or Docker image).
- The affected URL or code path.
- What an attacker can do, and what they already need (anonymous visitor, signed-in user, or admin).
- Steps to reproduce, or a proof of concept that stays on a system you control.
- Whether you want to be credited by name. Say if you want to stay anonymous.

## What happens next

| Step | Target |
|------|--------|
| Acknowledge the report | 3 business days |
| Confirm or reject it, and say what the next step is | 7 business days |
| Ship a fix on a release, then publish the advisory | As soon as a safe fix is ready |

A report that needs more detail may take longer. The maintainer will say so rather than going silent. Please give a reasonable window before any public write-up so users can install the fix first. 90 days from the acknowledgement is the default if we have not agreed on another date.

Fixed issues are recorded in [CHANGELOG.md](CHANGELOG.md). Credit goes in the advisory and the changelog unless you ask to stay anonymous. GitHub’s Credits field can only name a GitHub account. If you do not have one, you will still be named in the description.

## Supported versions

Security fixes go into the latest release. Older tags are not maintained as separate branches. Please upgrade to the newest release, or pull a new Docker image (`latest` for stable, `dev` for the `dev-testing` branch).

## In scope

- DataDock’s PHP application, installer, updater, and the published Docker image.
- Authentication, sessions, access control, file download and preview, uploads, and admin actions.
- A vulnerability in the default configuration.

## Out of scope

- A site that has already given the attacker an admin account, except where the admin boundary itself is broken.
- Missing security headers when **Admin → Site Settings → Security headers** is set to Off. Turn on Recommended or Strict, or set the headers at the reverse proxy.
- Volumetric denial of service.
- Reports from automated scanners with no demonstrated impact.
- Social engineering of the maintainer, and issues in third-party software (PHP, MySQL, Apache, the browser) rather than DataDock.

## Testing rules

Investigate only systems you own or have permission to test. Do not access, modify, or delete another person’s files. Do not leave a persistent backdoor. Stop once you can show the impact.
