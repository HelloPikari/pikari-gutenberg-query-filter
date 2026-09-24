# Query Filter 1.0 — Editor UX (C) and Result Announcements (D)

- **Status:** Draft, 2026-09-23. The design was approved in conversation, section by section; this file awaits Steve's review.
- **Scope:** sub-projects C and D of the 1.0 effort. Sub-project B's spec is `2026-09-16-query-filter-1.0-design.md`, and it stays the authority for the URL contract, markup and store.
- **Sources:** todo #588 and roadmap #34, the #522 review's "should-include" list, and a read of `edit.js`, `view.js`, `render.php` and WordPress 7.1.1's `interactivity-router` bundle.

## 1. Why

1.0 freezes the public contract: URL parameters, markup and classes, block attributes, and PHP hooks. Session 9's rule decides what lands before the release: **anything that changes the contract, or makes a documented claim true, lands before 1.0. Anything additive waits for 1.x.**

C and D were two words each ("editor UX", "frontend behaviour"). Scoped by that rule, they come down to:

1. **D:** a screen reader hears nothing useful when filtered results change. Core's router says "Page loaded." whether the filter matched 40 posts or none. Announcing the count needs a new piece of markup, which makes it contract work.
2. **C:** the editor writes a translated default label into post content, and keeps two dead Sort attributes. Both change the block attributes, so they land now or never.
3. **C:** B3's radio-group merge has no guard. Two filters on the same taxonomy in one loop share a `name`, so arrow keys and "3 of 7" counts span both `<fieldset>`s. The shared name _is_ the URL contract, so the fix is to stop the setup in the editor.
4. **C:** the editor preview shows options the frontend won't render, and doesn't warn when the frontend will render nothing.

## 2. Decisions

| Decision            | Choice                                                                                                         |
| ------------------- | -------------------------------------------------------------------------------------------------------------- |
| Size of C and D     | The minimum the freeze rule requires. Everything additive is deferred to 1.x (§6).                             |
| What is announced   | The loop's total result count (`found_posts`), e.g. "12 results found" or "No results found".                  |
| How it is announced | `speak()` from `@wordpress/a11y`, with the router's own announcement turned off for this plugin's navigations. |
| Radio-group merge   | An editor notice on duplicate filters. No frontend change.                                                     |
| Delivery            | Two PRs to `main`: C, then D (§7).                                                                             |

## 3. D: announcing the result count

### 3.1 Why `speak()`, not a live region in our markup

- Every Query Filter and Sort block sits inside the loop's router region, so an `aria-live` element there would be **replaced** by each navigation rather than updated, and screen readers announce a replaced live region unreliably.
- `@wordpress/a11y` keeps its live regions (`#a11y-speak-polite`, `#a11y-speak-assertive`) in `<body>`, outside every region. Core's router already uses it (`interactivity-router/index.js`, WP 7.1.1, `a11ySpeak()`).
- Keeping the router's "Page loaded." and adding the count would announce twice on every filter change.

### 3.2 Getting the count

Custom loops build their `WP_Query` inside core's post-template render, which the plugin can't reach directly.

- **Custom loops.** `QueryLoopHandler::modify_query()` adds a private query var to the loop's arguments, carrying the loop's form id, for example `pikari_gutenberg_query_filter_loop => 'pikari-gutenberg-query-filter-form-3'`. A `the_posts` filter reads that var from the `WP_Query` it receives and records `$query->found_posts` against the form id for the rest of the request. It hooks `the_posts`, not `found_posts`: `WP_Query::set_found_posts()` returns before running the `found_posts` filter whenever `$this->posts` is an empty array, and a query-cache hit sets `found_posts` straight from the cache without running it either — `the_posts` runs on both paths.
  - Core builds a custom loop's query several times per page (post template, pagination, no-results). Every build returns the same total, so the last write wins.
  - The var goes on every custom loop, filtered or not, because an unfiltered loop still needs a count once a visitor starts filtering from it.
- **Inherited loops.** The count is the main query's `found_posts`, read in `render_block_query()`. `MainQueryFilter` has already applied the filters by then.
- **Stamping it.** `BlockFilters::inject_loop_form()` runs after the whole loop has rendered. It adds two attributes to the injected form:
  - `data-query-found-posts="12"`: the integer count. This is **public contract**, documented in `docs/hooks.md`.
  - `data-query-results-message="12 results found"`: the text to announce, built in PHP with `_n()` so it goes through the normal PHP translation workflow. "No results found" when the count is 0.
- **No count, no attributes.** A loop with no plugin controls gets no form (unchanged), and a loop whose count wasn't recorded gets neither attribute, so JavaScript announces nothing rather than a wrong number.
- **Known inaccuracy:** a loop with an `offset` reports core's `found_posts`, which includes the skipped posts. This matches core's own Query Total block, and the README's existing offset gotcha covers it.

### 3.3 Announcing it

In `view.js`:

- `run()` and `actions.navigate` take the triggering form's id alongside the URL.
- `actions.navigate` calls the router with `screenReaderAnnouncement: false`.
- It restores the "loading" cue itself: if the navigation is still in flight after 400 ms (the router's own delay), it speaks core's localized loading text. It reads that text from the `wp-script-module-data-@wordpress/interactivity-router` JSON, the way the router does, and skips the cue if the JSON is missing.
- After the router resolves, and only if this navigation is still the current one (`inFlightUrl === url`), it finds the form by id in the **new** DOM and speaks its `data-query-results-message` politely.
- `@wordpress/a11y` is loaded with a dynamic `import()`, and a failure is ignored, as the router does.

**What stays the same:**

- Core's enhanced pagination never calls this store, so it keeps "Page loaded.". That's accurate for a page change.
- The no-JavaScript path is a full page load; the count is on the page and nothing is spoken, as with any other page.

### 3.4 Contract additions

- **Markup:** `data-query-found-posts` and `data-query-results-message` on `form.wp-block-pikari-gutenberg-query-filter__form`.
- **PHP:** the private query var name. It is documented as internal and not to be relied on.
- **No new filter hook.** Rewording goes through translation files. A `…_results_message` filter is additive and can come in 1.x if a site asks for one.

### 3.5 Testing

- **PHP (Brain\Monkey):**
  - `modify_query()` adds the loop var for custom loops and not for inherited ones.
  - The `the_posts` filter records `$query->found_posts` by form id, passes the posts through unchanged, and ignores queries without the var.
  - `inject_loop_form()` stamps both attributes, uses the inherited loop's main-query count, and omits both when no count was recorded.
  - `_n()` is called with the count.
- **Jest:**
  - `navigate` passes `screenReaderAnnouncement: false`.
  - It speaks the new form's message after resolving.
  - It stays silent when a newer navigation took over, or when the attribute is missing.
  - It speaks the loading text only after 400 ms.
- **Playwright:**
  - Filter to a known fixture count and read `#a11y-speak-polite`; filter to zero results; do the same on an inherited loop.
  - Every assertion must be shown to fail by removing the `speak()` call.

## 4. C: the editor

### 4.1 Contract fixes

- **`label` is only stored when the user types one.**
  - Today the three variations in `variations.js` set `label` to a translated default, and the taxonomy auto-select in `edit.js` sets it to the first taxonomy's name. Both freeze the editor's language into post content, and the auto-selected one goes stale when the taxonomy changes.
  - After the change, neither sets `label`. The default is resolved at render by `render.php`, as it already is for a block with no `label`, and in the editor by `getDefaultLabel()`.
  - **Existing content is untouched:** a saved `label` still wins. No migration or deprecation is needed, because the attribute itself doesn't change.
- **The taxonomy auto-select moves out of the `useSelect` selector** into a `useEffect`. It still picks the first viewable taxonomy for a new taxonomy filter.
- **The Sort block loses `width` and `widthUnit`.** Nothing reads either. It's a dynamic block, so a stale attribute left in a saved comment delimiter doesn't invalidate the block.

### 4.2 Editor notices

A `<Notice status="warning" isDismissible={ false }>` goes at the top of the block's inspector panel, and a short line of text in the canvas wherever the block would otherwise render nothing on the frontend.

| Condition                                                                                                            | Notice                                                                                                     |
| -------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Another Query Filter in the same Query Loop has the same `filterType`, and for taxonomy filters the same `taxonomy`. | Explains that both blocks control the same URL parameter and behave as one control. Suggests removing one. |
| Taxonomy filter with no taxonomy chosen.                                                                             | Nothing will display until a taxonomy is chosen.                                                           |
| Taxonomy with no terms that have posts (the preview's term query resolved to an empty list).                         | Nothing will display until a term has posts.                                                               |
| Author filter with no authors who have published posts.                                                              | Nothing will display until an author has published posts.                                                  |

- **"Same Query Loop"** is the nearest `core/query` ancestor, from `getBlockParentsByBlockName( clientId, 'core/query', true )[ 0 ]`. Its descendants come from `getClientIdsOfDescendants()`, filtered to this block's name. Nested loops are separate loops.
- **Both blocks show the notice,** so it doesn't matter which one the user selects.
- **The Sort block** can't be duplicated meaningfully either, but two Sort blocks is a far less likely setup. Out of scope.
- **The decision logic is a pure function** in `src/utils/`: attributes and sibling attributes in, notice keys out. It's unit-tested without the editor.

### 4.3 Preview fidelity

The preview should list what `render.php` will output.

| Filter                    | Today                                                                                          | After                                                                                          |
| ------------------------- | ---------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Taxonomy                  | `{ number: 50 }`, which REST ignores, so it gets the default 10 terms, including empty ones.   | `{ per_page: 100, hide_empty: true }`, matching `FilterHelper::get_taxonomy_filter_terms()`.   |
| Author                    | `{ who: 'authors' }`, deprecated since WP 5.9.                                                 | `{ has_published_posts: true, per_page: 100 }`, matching `AuthorHelper::get_filter_authors()`. |
| Post type, inherited loop | Reads `context.query.postType`, which is the block default (`post`), not the template's query. | A short note in place of the options: "Options come from the page's query."                    |

### 4.4 Hygiene, only where the above touches it

- `edit.js` renders `FilterInspectorControls` instead of its inline copy of the same controls.
- The Label help text changes from "If empty then no label will be shown" (false) to "Leave empty to use the default label."
- `useInstanceId()` replaces the `Math.random()` ids in both `edit.js` files, which currently change on every render.

### 4.5 Testing

- **Jest:**
  - The notice function: duplicate detection (same taxonomy, different taxonomy, different `filterType`, nested loop) and each "renders nothing" condition.
  - `variations.js`: no variation carries `label`.
  - `block-metadata.test.js`: Sort has no `width` / `widthUnit`.
- **By hand in a browser (wp-admin):**
  - Insert each variation and confirm no `label` is saved (Code editor view).
  - Duplicate a taxonomy filter in one loop and see both notices.
  - Compare the preview's term and author lists with the frontend's.
  - E2E doesn't cover the editor yet; adding that is not part of C.

## 5. Documentation and translations

- **`docs/hooks.md`:** the two form attributes (§3.4); the duplicate-filter behaviour already in the edge cases now points at the editor notice.
- **`README.md`:** the result announcement, in the accessibility section.
- **`CHANGELOG.md`:** C: the `label` and Sort attribute changes. D: the announcement, and that core's "Page loaded." no longer plays for filter changes.
- **Plugin `CLAUDE.md`:** the render flow gains the count capture; the tests list gains the new files.
- **Translations:** new strings are the results message (`_n()` singular and plural, plus the zero case), the four notices, the inherited post-type note and the new help text. Run the plugin `CLAUDE.md` i18n workflow.

## 6. Deferred to 1.x

Each is additive or a bug fix that doesn't change the contract. Each goes on the roadmap.

- **Taxonomy term controls** (include/exclude, order, limit, hide empty): the only #522 "should-include" item still open. The attributes are additive and their defaults already match the frontend, so there's no 1.0 decision to make.
- **#575 / roadmap #35:** two inherited loops on one page merge filter values on the JavaScript path.
- **Roadmap #16:** router-region fallback for blocks without client navigation.
- **#569:** whether Sort shows "Default" on archive pages.
- **A loading state** (`aria-busy`, a class on the region) while a navigation is in flight.
- **A visible result count:** core's Query Total block already exists; a plugin block isn't needed.
- **The Sort preview honouring `sort_options`:** already documented as a limitation.

## 7. Delivery

| PR  | Branch                    | Contents                                                  | Label     |
| --- | ------------------------- | --------------------------------------------------------- | --------- |
| C   | `feature/editor-ux`       | §4, its docs and strings. This spec lands on this branch. | `feature` |
| D   | `feature/result-announce` | §3, its docs and strings.                                 | `feature` |

- C comes first: it's smaller and independent of D.
- Each PR has an implementation plan, is written test-first, and gets a hands-on browser pass before merging, as in spec §10.1.
- **Sub-project E** follows D. Nothing is published until E is done (todo #529).

## 8. To confirm in the plan, not assumed here

- That `@wordpress/scripts`' module build treats a dynamic `import( '@wordpress/a11y' )` as an external script module, as it does `@wordpress/interactivity-router`. If it doesn't, the fallback is a `speak()` equivalent writing to the same `#a11y-speak-polite` region.
- That the `found_posts` filter sees the post-template's `WP_Query` with the private var intact. It should, since `WP_Query` keeps unknown query vars, but prove it in a live wp-env before building on it.
- That REST's users endpoint accepts `has_published_posts: true` for an editor-role user in the block editor.
