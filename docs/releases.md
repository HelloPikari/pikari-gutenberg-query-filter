# Release Management Guide

This project uses [Release Drafter](https://github.com/release-drafter/release-drafter) for automated release management. This guide explains how releases work and how to create them.

## Overview

Our release process is designed to be simple and automated:

1. **Development**: Work on features in branches, create PRs
2. **Auto-Draft**: Release Drafter automatically updates a draft release as PRs are merged
3. **Release**: When ready, review and publish the draft release
4. **Assets**: GitHub Actions automatically builds and attaches the plugin ZIP

## How Release Drafter Works

### Automatic Release Drafting

Every time a PR is merged to `main`, Release Drafter:

- Updates the draft release with the PR title
- Categorizes the change based on PR labels
- Calculates the next version number based on change types
- Credits the contributor

### Version Calculation

Version numbers are calculated automatically based on PR labels:

| Label                                         | Version Bump | Example       |
| --------------------------------------------- | ------------ | ------------- |
| `breaking`, `breaking-change`                 | **Major**    | 1.0.0 → 2.0.0 |
| `feature`, `enhancement`                      | **Minor**    | 1.0.0 → 1.1.0 |
| `bug`, `fix`, `docs`, `dependencies`, `chore` | **Patch**    | 1.0.0 → 1.0.1 |

If no relevant labels are found, it defaults to a **patch** version bump.

## Creating Releases

### Method 1: Publish Draft Release (Recommended)

This is the standard workflow for most releases:

1. **Navigate to Releases**

   - Go to your GitHub repository
   - Click on "Releases" in the right sidebar
   - You'll see a draft release at the top

2. **Review Draft Release**

   - Check the auto-generated version number
   - Review the changelog entries
   - Verify all recent changes are included

3. **Edit if Needed**

   - Click "Edit draft"
   - Modify version number if required
   - Edit release title or description
   - Add any additional notes

4. **Publish Release**
   - Click "Publish release"
   - GitHub Actions will automatically:
     - Build the production plugin
     - Create `pikari-query-filter.zip`
     - Generate checksums
     - Attach files to the release

### Method 2: Manual Release Creation

For special cases or when no draft exists:

1. **Go to Releases** → **Create a new release**
2. **Choose a tag**: Create new tag (e.g., `v1.2.0`)
3. **Target**: Select `main` branch
4. **Release title**: Version number (e.g., `v1.2.0`)
5. **Description**: Write release notes manually
6. **Publish release**

The build process will run automatically regardless of creation method.

## PR Labeling Guide

Proper labeling ensures accurate changelog categorization and version bumping:

### Breaking Changes

- `breaking`: Major architectural changes
- `breaking-change`: API changes that break compatibility

### Features

- `feature`: New functionality
- `enhancement`: Improvements to existing features

### Bug Fixes

- `bug`: General bug fixes
- `fix`: Alternative label for bug fixes

### Maintenance

- `docs`: Documentation updates
- `dependencies`: Dependency updates (usually from Dependabot)
- `chore`: Maintenance tasks, refactoring
- `ci`: CI/CD improvements

### Special Labels

- `skip-changelog`: Exclude from release notes (use sparingly)

## Release Notes Format

Release Drafter automatically organizes changes into sections:

```markdown
## What's Changed

🚨 **Breaking Changes**

- Major API restructuring @developer (#123)

🎉 **New Features**

- Add dark mode support @contributor (#124)
- Implement keyboard shortcuts @developer (#125)

🐛 **Bug Fixes**

- Fix accordion animation glitch @contributor (#126)

📚 **Documentation** (collapsible after 3 items)

- Update installation guide @maintainer (#127)

🧰 **Maintenance** (collapsible after 3 items)

- Update ESLint configuration @developer (#128)

⬆️ **Dependencies** (collapsible after 3 items)

- Bump @wordpress/scripts from 26.1.0 to 26.2.0 @dependabot (#129)
```

## Best Practices

### For Developers

1. **Use descriptive PR titles** - They become your changelog entries

   - ✅ "Add keyboard navigation to accordion items"
   - ❌ "Fix stuff"

2. **Label your PRs correctly** - Ensures proper version bumping

   - Use `feature` for new functionality
   - Use `bug` for fixes
   - Use `breaking` for compatibility-breaking changes

3. **Review draft releases regularly** - Don't let them accumulate too many changes

### For Maintainers

1. **Release frequently** - Smaller releases are easier to manage and debug
2. **Review before publishing** - Check the auto-generated content makes sense
3. **Add context when needed** - Edit release notes to add important details
4. **Test the ZIP file** - Download and verify the attached plugin works

## Troubleshooting

### Draft Release Missing

If no draft release exists:

- Check if Release Drafter workflow is enabled
- Ensure PRs are being merged to `main` branch
- Look at Actions tab for workflow failures

### Version Management Note

**Important**: Plugin PHP version numbers in the main plugin file are optional. Git tags are the authoritative source for version numbers. The release system automatically uses git tags for all distribution channels (GitHub releases, Composer packages, etc.).

### Wrong Version Number

The auto-calculated version can be edited:

1. Edit the draft release
2. Change the tag to desired version (e.g., `v1.3.0`)
3. Update the release title to match
4. Publish normally

### Missing Changes

If recent PRs don't appear in draft:

- Check PR labels - unlabeled PRs might be excluded
- Verify PRs were merged to `main` branch
- Check if PR has `skip-changelog` label

### Build Failures

If ZIP file doesn't attach:

1. Check Actions tab for release workflow failures
2. Look for build errors in the workflow logs
3. Common issues:
   - Missing `build/` directory
   - npm/composer dependency issues
   - File permission problems

### Asset Upload Issues

If checksums or ZIP files are missing:

- Re-run the failed workflow from Actions tab
- Check file permissions and path issues in workflow logs
- Verify the release exists before workflow tries to upload

### Workflow Re-runs

**Important**: When re-running workflows, remember that GitHub Actions runs workflows from the commit being released (the tag commit), not from the current main branch. If you've fixed workflow issues after tagging:

1. Either create a new release with a new tag
2. Or use manual workflow dispatch from the main branch with the tag parameter

## Automation Features

### Dependabot Integration

- Dependabot automatically creates PRs for dependency updates
- These are auto-labeled with `dependencies`
- Safe updates (patch/minor) can auto-merge if CI passes
- All dependency updates are grouped in collapsible release section

### Label Synchronization

- Repository labels are managed via `.github/labels.yml`
- Labels automatically sync when configuration changes
- Ensures consistent labeling across the project

### Branch Protection

- `main` branch requires PR review (even from admins)
- Status checks must pass before merging
- This ensures all changes go through proper review and CI

## Examples

### Example 1: Feature Release

```
PR: "Add dark mode toggle to settings panel" (labeled: feature)
Result: v1.0.0 → v1.1.0
Changelog: Listed under "🎉 New Features"
```

### Example 2: Bug Fix Release

```
PR: "Fix accordion collapse animation timing" (labeled: bug)
Result: v1.1.0 → v1.1.1
Changelog: Listed under "🐛 Bug Fixes"
```

### Example 3: Breaking Change Release

```
PR: "Restructure block attributes for better performance" (labeled: breaking)
Result: v1.1.1 → v2.0.0
Changelog: Listed under "🚨 Breaking Changes"
```

## Release Checklist

Before publishing a release:

- [ ] Review all changes in the draft
- [ ] Verify version number is appropriate
- [ ] Check that breaking changes are properly documented
- [ ] Test the current main branch works correctly
- [ ] Add any additional context to release notes if needed
- [ ] Ensure all CI checks are passing on main

After publishing:

- [ ] Verify ZIP file was attached successfully
- [ ] Download and test the plugin ZIP
- [ ] Check that checksums file was generated
- [ ] Update any external documentation if needed
- [ ] Announce the release if significant

## Getting Help

- **GitHub Issues**: Report problems with the release process
- **Discussions**: Ask questions about release management
- **Actions Tab**: Debug workflow failures
- **Release Drafter Docs**: <https://github.com/release-drafter/release-drafter>
