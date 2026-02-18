# Contributing to DataDock

Thanks for your interest in contributing. This guide explains how to submit changes via a pull request.

---

## Workflow

### 1. Fork the repository

Fork [DataDock](https://github.com/ZacharyKeatings/DataDock) on GitHub, then clone your fork locally:

```bash
git clone https://github.com/YOUR_USERNAME/DataDock.git
cd DataDock
```

Add the upstream repo so you can pull the latest changes:

```bash
git remote add upstream https://github.com/ZacharyKeatings/DataDock.git
```

### 2. Create a branch

Create a new branch for your work. Use a prefix that describes the type of change:

| Prefix   | Use for |
|----------|--------|
| `feat/`  | New features |
| `fix/`   | Bug fixes |
| `docs/`  | Documentation only (README, ROADMAP, comments, etc.) |
| `chore/` | Maintenance (deps, tooling, refactors with no behavior change) |
| `style/` | Code style only (formatting, whitespace; no logic change) |

**Branch naming examples:**

- `feat/add-per-file-password`
- `fix/upload-progress-on-safari`
- `docs/update-install-steps`
- `chore/update-php-deps`

Keep branch names short, lowercase, and hyphen-separated.

### 3. Make your changes

- Make your edits on your branch.
- Match existing code style and conventions in the project.
- For larger or breaking changes, consider opening an issue first to discuss the approach.

### 4. Open a pull request

1. Push your branch to your fork:
   ```bash
   git push origin feat/your-branch-name
   ```

2. On GitHub, open a pull request **from your branch into the upstream default branch** (e.g. `main`).

3. Describe what the PR does and, if relevant, reference any issue (e.g. `Fixes #123`).

4. Maintainers will review and may request changes. Once approved, your PR can be merged.

---

## License

By contributing, you agree that your contributions will be licensed under the same terms as the project ([Unlicense](LICENSE)).
