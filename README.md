# 📁 DataDock

**A lightweight, self-hosted file manager** for uploading, managing, and sharing files securely. No cloud lock-in, no frameworks—just PHP, MySQL, and your server.

<div align="center">

[![Release](https://img.shields.io/github/v/release/ZacharyKeatings/DataDock)](https://github.com/ZacharyKeatings/DataDock/releases)
[![License](https://img.shields.io/github/license/ZacharyKeatings/DataDock)](https://github.com/ZacharyKeatings/DataDock/blob/main/LICENSE)

</div>

---

## Table of Contents

- [Why DataDock?](#why-datadock)
- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Tech Stack](#-tech-stack)
- [Security](#-security)
- [Documentation](#-documentation)
- [Contributing](#-contributing)
- [License](#-license)

---

## Why DataDock?

DataDock exists because **you shouldn’t have to trust your data to services that scrape and monetize it**. When you rely on big platforms, you rarely know how your data is really handled. DataDock is self-hosted and built with **minimal external dependencies** on purpose—so you avoid the supply-chain attacks and opaque behavior that plague many modern apps. The goal is simple: **regain control over your data and keep your autonomy.**

- **Self-hosted** — Your data stays on your server. No third-party storage or accounts.
- **Lightweight** — Vanilla PHP 8+, MySQL, and GD. No frameworks or heavy dependencies; fewer moving parts means a smaller attack surface and no dependency supply chain to blindly trust.
- **Simple to run** — Web installer or upload to any Apache/Nginx + PHP host; optional custom storage path.
- **Admin-first** — Site settings, user and file management, purging, maintenance mode, and one-click updates from the admin panel.
- **User-friendly** — Drag-and-drop uploads, quotas, expiry, one-time links, ZIP download, QR codes, and optional public file browsing with per-file sharing.

Ideal for small teams, internal file sharing, or anyone who wants a minimal, controllable alternative to cloud file services. For how DataDock handles data in practice, see [PRIVACY.md](PRIVACY.md).

---

## 📦 Features

### Highlights

| Area | Capabilities |
|------|----------------|
| **Users & auth** | Registration (optional or invite-only), login, password reset (user or admin), guest uploads with quotas, role-based access, user-to-user file sharing. User profiles: private (edit username, email, display name, avatar, bio; change password; stats) and public (view by username; avatar, bio, public file stats and list). Usernames link to public profiles. |
| **Files & storage** | Upload with expiry, thumbnails, custom storage path, allowed types, ZIP download, one-time links, QR codes, checksums (MD5/SHA256). Bulk actions on dashboard: zip selected files, toggle public/private, or delete multiple. |
| **Admin panel** | Site settings (branding, limits in B/KB/MB/GB, optional .user.ini override for PHP upload limits), user and file management, manual purge, maintenance mode, site stats, one-click updates. |
| **Security** | Secure password hashing, CSRF-safe forms, brute-force protection, config directory protection, optional install.php warning. |

A full checklist of implemented and planned features is maintained in the **[Roadmap](ROADMAP.md)**.

---

## 📋 Requirements

- **PHP** 8.0 or later (with PDO MySQL, GD, session support)
- **MySQL** 5.7+ or MariaDB equivalent
- **Web server** — Apache or Nginx with PHP support
- **Optional:** writable directory outside the web root for custom storage path

---

## 🚀 Installation

1. **Clone or download the repository**
   ```bash
   git clone https://github.com/ZacharyKeatings/DataDock.git
   cd DataDock
   ```

2. **Point your web server** at the project directory (e.g. set document root to the cloned folder).

3. **Create a MySQL database** (and optionally a dedicated user) for DataDock.

4. **Run the web installer**  
   Open `https://your-domain/install.php` in a browser and complete:
   - Database host, name, user, and password
   - Site name
   - Admin username and password

5. **Remove the installer**  
   Delete or rename `install.php` after setup. If it still exists, a security warning is shown to the logged-in admin on every page until the file is removed (you can disable this warning in Admin Panel → Site Settings).

For detailed upgrade steps and version-specific notes, see [CHANGELOG.md](CHANGELOG.md).

---

## ⚙️ Configuration

Most settings are managed in the **Admin Panel** after installation:

- **General** — Site name, default file expiry, registration toggle, ToS, branding (logo, favicon, welcome message).
- **User permissions** — Registration on/off, invite-only registration (admin-generated signup links), guest upload limits, max files and storage per user (with byte/KB/MB/GB units), unique email enforcement.
- **Storage** — Custom storage path (uploads and thumbnails can live outside the web root).
- **Server PHP limits (optional)** — Override `upload_max_filesize` and `post_max_size` via a `.user.ini` file written from Site Settings; remove overrides when not needed.
- **Security** — Brute-force protection thresholds, session timeout, install.php warning toggle.
- **Logging** — Log file path and verbosity.

There is no separate config file to edit for normal operation; the installer writes initial database and config, and the rest is done via the UI.

---

## ⚙️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8+ (vanilla, no frameworks) |
| **Frontend** | HTML5, CSS3, vanilla JS, inline SVG icons |
| **Database** | MySQL (PDO) |
| **Thumbnails** | GD library |
| **Sessions** | Native PHP session handling |

Security-related choices: CSRF-safe form handling, `password_hash` for passwords, role-based access, and optional install.php reminder.

---

## 🛡️ Security

- **Input handling** — User input is sanitized (e.g. `htmlspecialchars()` and project `sanitize_data()`); file uploads are validated by size and MIME type.
- **Access control** — Admin panel and sensitive actions require an authenticated session and appropriate role.
- **Config protection** — The `config/` directory is protected (e.g. via `.htaccess` on Apache) so credentials are not directly accessible.
- **Installation** — Deleting `install.php` after setup is recommended; a warning is shown to the logged-in admin on every page until it is removed (toggle in Site Settings).

For security-sensitive deployments, use HTTPS, restrict admin access (e.g. by IP or VPN), and keep PHP and MySQL updated. Report vulnerabilities responsibly (e.g. via GitHub issues or contact details in the repo).

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [**PRIVACY.md**](PRIVACY.md) | How DataDock handles your data: no telemetry, no cloud sync; everything stays on your server. |
| [**CONTRIBUTING.md**](CONTRIBUTING.md) | How to contribute: fork, branch naming, and pull requests. |
| [**ROADMAP.md**](ROADMAP.md) | Full feature checklist (done and planned) with short descriptions. |
| [**CHANGELOG.md**](CHANGELOG.md) | Release history and notable changes. |

---

## 🧑‍💻 Contributing

Contributions are welcome. See **[CONTRIBUTING.md](CONTRIBUTING.md)** for the workflow: fork the repo, make changes in a branch (e.g. `feat/` or `fix/`), then open a pull request. For substantial changes, please open an issue first to discuss. By contributing, you agree that your contributions may be licensed under the same terms as the project (Unlicense).

---

## 📄 License

This project is open-source and licensed under the [Unlicense](LICENSE).

---

<div align="center">

**If DataDock is useful to you, consider [starring the repository](https://github.com/ZacharyKeatings/DataDock)** — it helps others find the project.

</div>
