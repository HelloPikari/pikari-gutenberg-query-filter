# Query Filter 1.0 — Sub-project E: Docs and the Release

- **Status:** Scoped and carried out on 2026-09-24 without a review round. Steve asked for it to be done "without my input", so every call below is a ruling for review, not an approved decision.
- **Scope:** documentation and release preparation only. There are no code changes. Publishing the v1.0.0 draft stays with todo #529.

## Why

1.0 freezes the public contract, so every public claim has to be true at the tag:

- README;
- `docs/hooks.md`;
- `readme.txt`, which the update checker reads at the tag;
- `CHANGELOG.md`.

Sub-projects B–D documented their own changes as they went. E is the pass across the whole set: it checks claims against the code, and fixes the release documentation that the automation has overtaken.

## Items

| #   | Item                                                                                | Outcome                                                                       |
| --- | ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| 1   | Audit README and `docs/hooks.md` against the code and specs §3, §5, §7, §9          | Done. A subagent audit; findings fixed in this PR                             |
| 2   | README "Upgrading to 1.0" (spec §10.3, generalised)                                 | Done                                                                          |
| 3   | `CHANGELOG.md`: coherence across B–D; `[Unreleased]` → `[1.0.0]`                    | Done. Stamped 2026-09-25                                                      |
| 4   | `readme.txt`: changelog 0.3.2–1.0.0, 1.0.0 upgrade notice, fake Screenshots section | Done                                                                          |
| 5   | `docs/releases.md`                                                                  | Rewritten                                                                     |
| 6   | Translations                                                                        | Checked. `fr_CA.po` has 0 untranslated and 0 fuzzy strings; E adds no strings |
| 7   | Plugin `CLAUDE.md` stale lines                                                      | Fixed. WP 6.0+ → 6.8+, and the release pointer                                |
| 8   | The release draft's body                                                            | Deferred to #529                                                              |

## Rulings

1. **Bounded, not architectural.** There is no plan document; this file stands in for the in-chat design.
2. **The CHANGELOG is stamped `2026-09-25`, not left `[Unreleased]`.** This follows the 0.3.4 precedent: the changelog was dated in a PR before publishing. If #529 publishes on a later day, correcting the date is a one-line docs PR.
3. **`Stable tag: trunk` stays.** The bump workflow deliberately leaves a non-numeric Stable tag alone. `release.yml` doesn't check it. The plugin isn't on wordpress.org.
4. **`readme.txt`'s 1.0.0 upgrade notice is kept under 300 characters** (257). That is WordPress's display limit, and ZIP-installed sites see this text on their update row.
5. **CCLF-specific notes stay out of the public docs.** The README states them generally: the Composer constraint and a renamed plugin folder. The site-specific steps stay in #478.
6. **The draft's body isn't edited in E.** Release Drafter rewrites it on every push to `main`, and merging E is a push. #529 gets suggested release-notes text instead.
7. **`docs/releases.md` is rewritten, not patched.** It said git tags are authoritative and the plugin header is optional. It also told readers to fix a wrong version by retagging. All three are false, because `release.yml` refuses to build when the header and the tag disagree. Modals has the same stale template, which is out of scope here.
8. **Unverifiable README claims are cut, not kept on trust.** A frozen contract shouldn't promise what no test or code shows.

9. **Code defects found by the audit are documented as they behave, not fixed in E.** None changes the frozen contract, so all can be fixed in 1.x:
   - The author list's cache invalidation deletes `wp_options` rows directly. On a persistent object cache it never invalidates. Nothing hooks `profile_update`, and it runs two `DELETE … LIKE` queries on every `save_post`, autosaves included.
   - On an inherited loop, the Sort block never matches the archive's default order, because core doesn't set it as a query variable at `pre_get_posts`. Its unit test injects `orderby` by hand.
   - The editor offers a taxonomy that is `publicly_queryable` but not `public`, and `FilterState` ignores its parameter.
   - Script modules need import maps (Chrome/Edge 89+, Firefox 108+, Safari 16.4+). An older browser with JavaScript on gets neither in-place updates nor the `<noscript>` button.
10. **The audit's 0.3.x → 1.0 differences went into the CHANGELOG and the README's upgrade section:**
    - an author option's `value` in the options filter;
    - the Sort block's "Default" choice;
    - stricter URL validation;
    - `query-*` parameters filtering archives site-wide;
    - the form as the Query Loop's last child.

## Not in E

- Publishing (#529). After that: Kindler (#574) and CCLF (#478).
- Running Playwright on CI (roadmap #28).
- 1.x work deferred by C and D (roadmap #39).
