# Release process

> Current release target: **3.3.0** (`v3.3.0`).

## Creating a new version (e.g. v3.3.0)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has the target version with date and full entry; `[Unreleased]` is at the top (empty or “_No changes yet._”).
   - [UPGRADING.md](UPGRADING.md) has a section "Upgrading to X.Y.Z" with what's new and upgrade steps.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.
   - Prefer `make release-check` (uses `check-no-cursor-coauthor-since-release` since the previous tag).

2. **Commit and push** any last changes to your default branch:
   ```bash
   git add -A
   git commit -m "Prepare v3.2.1 release"
   git push origin HEAD
   ```

3. **Create and push the tag**
   ```bash
   git tag -a v3.2.1 -m "Release v3.2.1"
   git push origin v3.2.1
   ```

4. **GitHub Actions** (if configured) may create the GitHub Release from the tag.

5. **Packagist** will pick up the new tag; users can then `composer require nowo-tech/performance-bundle`.

## After releasing

- Keep `[Unreleased]` at the top of [CHANGELOG.md](CHANGELOG.md) for the next version.

After creating the release commit and tag, run `make check-no-cursor-coauthor-since-release` again **before** `git push` (REQ-GIT-001 for commits since the previous release). Full-history `make check-no-cursor-coauthor` may still fail until older Cursor trailers are stripped with `make strip-cursor-coauthor-from-history` (requires a coordinated force-push; do not do that casually on `main`).
