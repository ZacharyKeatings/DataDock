# Privacy & Data

DataDock is built for people who want to **keep control of their data**. This document describes how the software behaves with respect to your information.

---

## Your data stays on your server

- **No cloud storage** — Uploads are stored wherever you point the application (by default the project’s storage directory, or a custom path you configure). No data is sent to third-party file storage.
- **No telemetry or analytics** — DataDock does not phone home. There are no tracking scripts, no usage analytics, and no reporting to the developers or anyone else.
- **No required external services** — The app runs on PHP, MySQL, and your web server. Optional features (e.g. QR code generation) may use external URLs at your discretion; the core flow does not depend on them for operation.

You can run DataDock fully offline (except for your own users’ access). What you store and who can access it is determined only by your instance and your server.

---

## What is stored and where

- **Uploaded files** — On disk in your configured storage path (and thumbnails if enabled).
- **Metadata** — Filenames, sizes, expiry, checksums, and ownership are stored in your MySQL database.
- **User accounts** — Usernames, password hashes, and roles in your database. No account data is sent elsewhere.
- **Sessions** — Stored using PHP’s native session mechanism (typically on your server).

All of this lives entirely under your control: your server, your database, your filesystem. There is no “sync” to a vendor or backup to a third party built into the application.

---

## Why this matters

DataDock was created out of a desire to **stop trusting personal and sensitive data to large services** that scrape, collect, and monetize user data—and to avoid the supply-chain risks that come with heavy dependency stacks. The goal is to give you a simple, self-hosted way to share and manage files so that you know exactly how your data is handled and who can access it. Your instance, your rules.

For more on security practices (HTTPS, access control, hardening), see the [Security](README.md#-security) section in the README.
