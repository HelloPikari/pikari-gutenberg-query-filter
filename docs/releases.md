# Release Guide

Releases are drafted by [Release Drafter](https://github.com/release-drafter/release-drafter), versioned by an automatic bump PR, and built by `release.yml` when the draft is published. The monorepo's `CLAUDE.md` ("Release Process") is the reference for the shared workflows; this page is the checklist for this plugin.

## How a version is chosen

Release Drafter recalculates the draft on every push to `main`. The version comes from the labels on the PRs merged since the last release, and the autolabeler sets those labels from the **branch name** and changed files:

| Label                                         | Bump  | Set by                               |
| --------------------------------------------- | ----- | ------------------------------------ |
| `breaking`, `breaking-change`                 | Major | Hand only                            |
| `feature`, `enhancement`                      | Minor | `feature/…` or `feat/…` branches     |
| `bug`, `fix`, `docs`, `dependencies`, `chore` | Patch | `fix/…` or `bugfix/…` branches, docs |
| _(none)_                                      | Patch | —                                    |

`skip-changelog` keeps a PR out of the notes. A draft with only `skip-changelog` PRs is titled "not ready to publish" and gets no bump.

## How the version is written

The version must be committed, because the update checker reads the `Version` header at the release tag. When the draft's version moves, the Release Drafter workflow opens `chore: Bump version to X` from `release/bump-version`. The PR merges itself once CI passes. It writes:

- the plugin header `Version`;
- the `PIKARI_GUTENBERG_QUERY_FILTER_VERSION` constant;
- `package.json` and `package-lock.json`.

`readme.txt`'s `Stable tag` is only rewritten when it is numeric. Here it is `trunk`, so it is left alone.

Until the bump lands, the draft is titled `vX.Y.Z — not ready to publish`. A clean title means ready. If the `RELEASE_BUMP_TOKEN` secret is missing, the draft's footer gives the manual command: `node .github/bump-version.js pikari-gutenberg-query-filter <version>`, run from the monorepo root.

## Publishing

1. **Update the changelogs** in one PR to `main`:
   - `CHANGELOG.md`: rename `[Unreleased]` to `[X.Y.Z] - YYYY-MM-DD`, add a fresh `[Unreleased]`, and update the compare links at the bottom.
   - `readme.txt`: add `= X.Y.Z =` under `== Changelog ==`, and under `== Upgrade Notice ==` when site owners must act. Sites installed from a ZIP show this upgrade notice next to the update.
2. **Check the draft**: a clean title, the expected version, and every merged PR listed.
3. **Publish it.** Don't edit the draft's body before the last merge to `main`: Release Drafter rewrites the body on every push.
4. **`release.yml` builds the ZIP.** It checks that the plugin header and `package.json` both match the tag, and refuses to build if they don't. It then attaches `pikari-gutenberg-query-filter-vX.Y.Z.zip` and its checksums to the release. The tag stays on `main`.
5. **Run "Regenerate package index"** in `HelloPikari/packages` so Composer sees the new version. A daily cron catches up eventually, but not at a predictable time.

## Distribution

One artifact goes out through two channels:

- **Manual install and admin updates:** the release ZIP. Sites installed from a ZIP get update notices through the plugin update checker, which reads GitHub releases.
- **Composer:** the same ZIP, served by the package index at `https://hellopikari.github.io/packages/`. Consumers need that `composer` repository entry, because the package is not on Packagist.

The `dist` branch is orphaned. Nothing writes to it, and nothing should read from it.

## When a release goes wrong

- **The ZIP is missing:** `release.yml` refused to build, usually because the versions don't match the tag. Fix the version on `main`, delete the release and its tag, then publish again.
- **The version is wrong:** fix the PR labels, not the draft's tag. A tag that disagrees with the plugin header produces a release with no ZIP.
- **An urgent fix while `main` holds unreleased breaking changes:** branch from the latest release tag, and release from that branch.
