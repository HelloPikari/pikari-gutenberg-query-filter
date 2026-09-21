# Query Filter 1.0 — B2: Inherited Query Loops Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the filters work on archive and search templates, where the Query Loop inherits the main query — today only the Search block does anything there — without letting a filter take over the page it sits on.

**Architecture:**

- A new `Integrations\MainQueryFilter` applies the same `FilterState` to the main query through `pre_get_posts`, but only on listing requests.
- Taxonomy filters are added as `posts_where` subqueries, never through the `tax_query` query var, so the archive's own term, title and template stay untouched.
- The option lists and the Sort block read the main query's **unfiltered** values, recorded before anything is changed, so choosing a value doesn't narrow the choices.
- Custom loops keep working exactly as B1 left them.

**Tech Stack:** PHP 8.4, PHPUnit 9.6 with Brain\Monkey and Mockery; Playwright 1.63 with `@wordpress/e2e-test-utils-playwright`; wp-env with Twenty Twenty-Five.

**Spec:** `docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md` (revision 2, approved). B2 implements §3.4, §4.4, §4.5 and the inherited rows of §3.1, plus §10.1's B2 row.

## Global Constraints

- **Branch:** `feature/inherited-loops`, cut from `main` after PR #99 and the 1.0.0 bump have landed.
- **Label:** `feature`. The draft is already at 1.0.0 from B1, so nothing extra is needed.
- **Scope:** inherited loops only. Do **not** add the loop `<form>`, control `name`/`form` attributes, `<noscript>` buttons or the store rewrite — those are B3. Don't change how custom loops behave.
- **Code style:** PHP follows WordPress Coding Standards with **4 spaces, not tabs**. `phpcs.xml` skips `tests/`, so check test files with `grep -c $'\t' <file>`, which must print 0.
- **Commits:** `type: Brief description`, no attribution trailers, never `--no-verify`.
- **TDD:** every behaviour change starts with a failing test.
- **Environment:** this plugin's wp-env (5884 development, 5885 tests). Never touch other Docker containers or ddev sites, and never run `wp-env destroy` or `wp-env clean`. Run `npm run build` before E2E.
- **Suites at the start:** PHP 114, Jest 32, E2E 18. All must stay green.

## The classes B2 builds on

Already shipped in B1, and used as they are:

- `Url\QueryParams`: `__construct( ?int $query_id, bool $inherit = false )`, `::from_block()`, `->prefix()`, `->key( string $name )`, `->page_key()`, `->is_inherit()`. For an inherited loop the keys are `query-{name}`, search is `s` and the page key is `paged`.
- `Url\FilterState`: `::from_array( array $get, QueryParams $params )`, `::for_loop( QueryParams $params )`, `::reset_cache()`, `->post_types()`, `->taxonomies()`, `->author_ids()`, `->search()`, `->sort()`, `->has_filters()`.
- `Query\QueryArgs::apply( array $args, FilterState $state )` — for custom loops, which build a `WP_Query` from arguments. The main query is already built, so B2 sets query vars instead; see Task 2.
- `Query\SortOptions`: `::all()`, `::find( string $key )`, `::match( string $orderby, string $order )`.

## File Structure

| File                                                                    | Responsibility                                                                                           |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `includes/Integrations/MainQueryFilter.php` (create)                    | Gates on the request, records the unfiltered query, applies filters to the main query, and handles 404s. |
| `includes/Query/TaxonomySubquery.php` (create)                          | Turns taxonomy slugs into a `posts_where` fragment with no join.                                         |
| `pikari-gutenberg-query-filter.php` (modify)                            | Instantiates `MainQueryFilter` on `init`, beside the existing handlers.                                  |
| `includes/Helpers/FilterHelper.php` (modify)                            | Post-type options in an inherited loop come from the recorded unfiltered value.                          |
| `src/blocks/sort/render.php` (modify)                                   | An inherited loop's default order comes from the main query, not block context.                          |
| `tests/php/MainQueryFilterTest.php` (create)                            | The gate matrix, the query vars set, the archive rules, and the 404 rule.                                |
| `tests/php/TaxonomySubqueryTest.php` (create)                           | Term resolution and the SQL fragment.                                                                    |
| `tests/php/FilterHelperTest.php` (modify)                               | Inherited post-type options.                                                                             |
| `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js` (modify) | A category archive template and a search template with filters.                                          |
| `tests/e2e/specs/inherited-loops.spec.js` (create)                      | Archive and search behaviour in a browser.                                                               |
| `docs/hooks.md`, `CHANGELOG.md`, `README.md` (modify)                   | Inherited loops are supported; the parameter table gains its inherited column.                           |

---

### Task 1: `TaxonomySubquery`

Spec §4.4 forbids the obvious approach. Setting the `tax_query` query var makes core treat the filter's term as the page's own term: the title, the template and `get_queried_object()` all follow it, and an unknown slug 404s a valid archive. A fresh `WP_Tax_Query`'s SQL doesn't work either — its first `IN` clause joins `term_relationships` with no alias, and the archive's own clause already did, which is a MySQL error.

So the filter becomes a `WHERE ... IN (SELECT ...)` fragment with no join.

**Files:**

- Create: `includes/Query/TaxonomySubquery.php`, `tests/php/TaxonomySubqueryTest.php`

**Interfaces:**

- Consumes: nothing from B2.
- Produces: `TaxonomySubquery::where( array $taxonomies ): string`, where `$taxonomies` is the `taxonomy => slugs` shape `FilterState::taxonomies()` returns. It returns SQL to append to a `WHERE` clause, starting with a space, or `''` when there's nothing to add.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/TaxonomySubqueryTest.php`. Stub `$wpdb` with a Mockery mock exposing `$posts = 'wp_posts'` and `$term_relationships = 'wp_term_relationships'`, and set it as the `$wpdb` global in `setUp()`. Stub `get_terms()` and `get_term_children()` with Brain\Monkey.

Tests:

1. **`test_no_taxonomies_produce_no_sql`** — `''`.
2. **`test_one_taxonomy_becomes_an_id_in_subquery`** — slugs `news` and `events` resolve to term_taxonomy_ids 11 and 12, and the result is ` AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11,12) )`. Compare with whitespace normalized so formatting isn't pinned.
3. **`test_two_taxonomies_produce_two_and_clauses`** — one `AND ... IN ( SELECT ... )` per taxonomy, so a post must match both.
4. **`test_hierarchical_taxonomies_include_child_terms`** — `get_terms()` returns a parent, `get_term_children()` returns two children, and all three IDs appear. This matches core's `include_children` default.
5. **`test_non_hierarchical_taxonomies_do_not_look_up_children`** — `get_term_children()` is never called. Assert with `Functions\expect( 'get_term_children' )->never()`.
6. **`test_slugs_that_match_no_term_match_no_posts`** — the fragment is `AND 1=0`. A filter for a term that doesn't exist must return nothing, not everything.
7. **`test_ids_are_cast_to_integers`** — a term object whose `term_taxonomy_id` is the string `'11'` still produces `IN (11)`, with no quotes.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/TaxonomySubqueryTest.php`
Expected: class not found.

- [ ] **Step 3: Write the class**

Create `includes/Query/TaxonomySubquery.php` in namespace `Pikari\GutenbergQueryFilter\Query`, with the `ABSPATH` guard and full PHPDoc. A class comment must explain why this exists rather than `tax_query`, naming both problems from spec §4.4.

`where()`:

- for each taxonomy, call `get_terms( array( 'taxonomy' => $taxonomy, 'slug' => $slugs, 'hide_empty' => false ) )`;
- collect `term_taxonomy_id` values as integers; for a hierarchical taxonomy, add each term's `get_term_children()` results as well, de-duplicated;
- with no IDs for a taxonomy, return `AND 1=0` for the whole fragment, since every taxonomy must match;
- otherwise append one `AND {$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (…) )` per taxonomy.

The IDs are integers you produced, so `$wpdb->prepare()` isn't needed; add a short comment saying so, since a reviewer will ask.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit tests/php/TaxonomySubqueryTest.php --testdox`
Expected: 7 passing.

- [ ] **Step 5: Commit**

```bash
git add includes/Query/TaxonomySubquery.php tests/php/TaxonomySubqueryTest.php
git commit -m "feat: Filter taxonomies with a subquery that leaves the archive alone"
```

---

### Task 2: `MainQueryFilter`

**Files:**

- Create: `includes/Integrations/MainQueryFilter.php`, `tests/php/MainQueryFilterTest.php`
- Modify: `pikari-gutenberg-query-filter.php`

**Interfaces:**

- Consumes: `QueryParams`, `FilterState`, `SortOptions`, `TaxonomySubquery`.
- Produces:

  - `new MainQueryFilter()` registers `pre_get_posts` (priority 10) and `pre_handle_404`;
  - `->filter_main_query( \WP_Query $query ): void`;
  - `MainQueryFilter::original( string $var )` returns a main-query value recorded before filtering (`post_type`, `orderby`, `order`), for Tasks 3 and 4;
  - `MainQueryFilter::applied(): bool` says whether this request had filters applied.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/MainQueryFilterTest.php`. Build `$query` as a Mockery mock of `WP_Query` with `is_main_query()`, `is_home()`, `is_archive()`, `is_search()`, `is_singular()`, `is_404()`, `is_feed()`, `is_post_type_archive()`, `is_author()`, `get()` and `set()`, and stub `is_admin()`. Reset `$_GET` and `FilterState::reset_cache()` between tests.

**The gate** (spec §4.4), one test each, all asserting `set()` is never called:

1. not the main query;
2. in the admin;
3. a feed;
4. a singular request;
5. a 404;
6. a request with no inherited-style parameter;
7. **`query-page` alone doesn't trigger it** — that's core's page key for a loop with no query ID, not a filter.

Then the positive cases, with `query-category=news` present: 8. **`test_it_runs_on_a_home_request`**, and the same for an archive and a search request.

**What it sets:** 9. **`test_post_types_are_set`** — `set( 'post_type', … )`. 10. **`test_post_types_are_ignored_on_a_post_type_archive`** (§3.4) — `is_post_type_archive()` true means `post_type` is never set, while a taxonomy filter still applies. 11. **`test_authors_are_set`** — `set( 'author__in', … )`. 12. **`test_author_archives_intersect`** (§3.4) — on an author archive for user 7, a filter naming only user 9 sets `author__in` to `array( 0 )`; a filter naming 7 and 9 sets it to `array( 7 )`. 13. **`test_sort_sets_orderby_and_order`**, and a meta sort also sets `meta_key`. 14. **`test_search_is_left_to_core`** — an `s` parameter alone never calls `set( 's', … )`. 15. **`test_any_filter_ignores_sticky_posts`** — `set( 'ignore_sticky_posts', true )`. 16. **`test_taxonomies_do_not_touch_the_tax_query_var`** — with a taxonomy filter, `set()` is never called with `tax_query`. This is the rule that keeps the archive's own identity, so name it clearly and give it a docblock citing §4.4. 17. **`test_a_taxonomy_filter_adds_a_posts_where_callback`** — after the call, `has_filter( 'posts_where', … )` is truthy.

**The `posts_where` callback:** 18. **`test_the_where_callback_only_acts_on_its_own_query`** — called with another `WP_Query`, it returns the clause unchanged. 19. **`test_the_where_callback_appends_the_subquery`** — for its own query, the result ends with what `TaxonomySubquery::where()` returned. 20. **`test_the_where_callback_removes_itself_after_running`**.

**Recording and 404s:** 21. **`test_the_unfiltered_post_type_is_recorded_before_filtering`** — `MainQueryFilter::original( 'post_type' )` returns what `get( 'post_type' )` gave before any `set()`. Assert the ordering, since Tasks 3 and 4 depend on it. 22. **`test_orderby_and_order_are_recorded`**. 23. **`test_a_filtered_date_archive_does_not_404`** (§4.4) — with filters applied, `is_date()` true and `is_paged()` false, the `pre_handle_404` callback returns true. 24. **`test_an_unfiltered_request_is_left_to_core`** — the callback returns the value it was given. 25. **`test_a_paged_date_archive_still_404s`** — `is_paged()` true means the callback doesn't interfere.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/MainQueryFilterTest.php`
Expected: class not found.

- [ ] **Step 3: Write the class**

Create `includes/Integrations/MainQueryFilter.php`. Structure:

- **Constructor:** `add_action( 'pre_get_posts', array( $this, 'filter_main_query' ) )` and `add_filter( 'pre_handle_404', array( $this, 'skip_404_for_filtered_archives' ), 10, 2 )`.
- **`filter_main_query( \WP_Query $query )`:**
  1. return unless `$query->is_main_query() && ! is_admin() && ! $query->is_feed() && ! $query->is_singular() && ! $query->is_404() && ( $query->is_home() || $query->is_archive() || $query->is_search() )`;
  2. return unless some `$_GET` key matches `/^query-(?!\d+-|page$)/`. Do this with one loop over `array_keys( $_GET )` before anything expensive;
  3. record `post_type`, `orderby` and `order` from `$query->get()` into the static store;
  4. build `new QueryParams( null, true )` and `FilterState::for_loop( … )`;
  5. return when `! $state->has_filters()`;
  6. apply, per spec §4.4: post types unless `is_post_type_archive()`; authors, intersected when `is_author()`; sort's `orderby`, `order` and `meta_key`; `ignore_sticky_posts`;
  7. for taxonomies, add a `posts_where` callback bound to this query object that appends `TaxonomySubquery::where()` and then removes itself;
  8. mark the request as filtered.
- **`skip_404_for_filtered_archives( $pre, $query )`:** return `true` when this request was filtered, `$query->is_date()` and `! $query->is_paged()`; otherwise return `$pre` unchanged. Add a comment: core exempts matched archives and searches but not date archives (`class-wp.php`), and a filter emptying a date archive shouldn't turn it into a 404.
- **`original( string $var )` and `applied()`** read the static store. Add `reset_for_tests()` if the tests need it, and say in its docblock that it exists for tests.

Then register it in `pikari-gutenberg-query-filter.php`, beside `QueryLoopHandler`, inside the existing `init` function. Add a one-line comment saying core never applies `query_loop_block_query_vars` to inherited loops, which is why this exists.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit tests/php/MainQueryFilterTest.php --testdox`, then `composer test`.
Expected: all green. Report the totals.

- [ ] **Step 5: Commit**

```bash
git add includes/Integrations/MainQueryFilter.php tests/php/MainQueryFilterTest.php pikari-gutenberg-query-filter.php
git commit -m "feat: Apply filters to inherited Query Loops through the main query"
```

---

### Task 3: Option lists and sort defaults read the unfiltered query

Without this, the filters narrow themselves: once you pick "Pages", the post-type filter only offers Pages, and there's no way back.

**Files:**

- Modify: `includes/Helpers/FilterHelper.php`, `src/blocks/sort/render.php`, `tests/php/FilterHelperTest.php`

**Interfaces:**

- Consumes: `MainQueryFilter::original()`.
- Produces: no new public API.

- [ ] **Step 1: Write the failing test**

In `tests/php/FilterHelperTest.php`, add tests for `get_filter_post_types()` with an inherited block context:

1. the post types come from `MainQueryFilter::original( 'post_type' )`, not from `$wp_query->get( 'post_type' )`;
2. an `any` or empty value on a search request gives the public post types that aren't excluded from search;
3. an empty value on a taxonomy archive gives the taxonomy's viewable object types;
4. an empty value anywhere else gives `post`;
5. `attachment` is dropped unless attachment pages are enabled;
6. the returned types **replace** the block context's `postType` rather than merging with it (spec §4.5).

The current code reads a `query-filter-post_type` query var that nothing has ever set, so these will fail for that reason too. Note that in your report.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/FilterHelperTest.php`

- [ ] **Step 3: Update the option list**

Rewrite the inherited branch of `FilterHelper::get_filter_post_types()` per §4.5, deleting the `query-filter-post_type` read. Keep the custom-loop branch, including its Advanced Query Loop `multiple_posts` support, exactly as it is.

- [ ] **Step 4: Sort defaults for inherited loops**

In `src/blocks/sort/render.php`, the default order currently comes from block context (`$block->context['query']['orderBy']`). For an inherited loop, use `MainQueryFilter::original( 'orderby' )` and `original( 'order' )` instead, since block context always says date/desc there. An empty `orderby` on a search request means relevance, which matches no option, so the empty "Default" choice renders. Leave the custom-loop path unchanged.

- [ ] **Step 5: Run everything**

Run: `composer test`, then `npm run build && npm run test:e2e`
Expected: green, E2E still 18.

- [ ] **Step 6: Commit**

```bash
git add includes/Helpers/FilterHelper.php src/blocks/sort/render.php tests/php/FilterHelperTest.php
git commit -m "fix: Offer inherited loops the choices their unfiltered query had"
```

---

### Task 4: End-to-end coverage on real templates

**Files:**

- Modify: `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js`
- Create: `tests/e2e/specs/inherited-loops.spec.js`

**Interfaces:**

- Consumes: the B0 harness — `PAGES`, `POSTS`, `newestTitles`, `visitor`, `resultTitles`, `markDocument`, `isSameDocument`, `param`, `waitForParam`.
- Produces: `TEMPLATES` in `content.js`, holding the two template slugs and their block markup.

- [ ] **Step 1: Add the template fixtures**

In `content.js`, export `TEMPLATES` with two entries whose content is an inherited Query Loop (`"inherit":true`, no `queryId`) plus a category checkbox filter labelled `Category`, an author select labelled `Author`, and a Sort block labelled `Sort by`:

- **`category`**, which Twenty Twenty-Five doesn't define, so creating it is safe;
- **`search`**, modelled on Twenty Twenty-Five's own: the Search block sits **outside** the loop, which is the layout that exposed a bug in review, so keep it that way.

In `setup/fixtures.js`, create both with `requestUtils.createTemplate( 'wp_template', { slug, content, theme } )`, and call `requestUtils.deleteAllTemplates( 'wp_template' )` in the cleanup, before creating them.

- [ ] **Step 2: Write the spec**

Create `tests/e2e/specs/inherited-loops.spec.js`, browsing as `visitor`. On the category archive for "News" (`/category/news/`):

1. **the archive still knows what it is** — filter by author, then assert the page title still names the category and the results are that author's News posts. This is §4.4's whole point.
2. **an unknown term doesn't 404** — load the archive with `?query-events=does-not-exist`, and assert a 200 with no results.
3. **filters don't reload the page** — `markDocument`, filter, then `isSameDocument`.
4. **pagination resets** — from `/category/news/page/2/`, filtering lands on page 1 with a 200.
5. **sorting works** — choose "Title (A-Z)" and assert alphabetical order, with `query-sort` in the URL.
6. **post-type options don't collapse** — on the search template, filter by a post type, then assert the filter still offers the other types.

On the search template, for a term matching posts by both authors: 7. **the search term survives a filter change** — filter by author and assert `s` is still in the URL and the results are that author's matches. This is the case that broke in review.

- [ ] **Step 3: Run the suite**

Run: `npm run build && npm run test:e2e`
Expected: green, 25 tests. Report the real number.

Run each new test twice and report any that passed only once.

- [ ] **Step 4: Prove they can fail**

Temporarily comment out the `pre_get_posts` registration in `MainQueryFilter`'s constructor, run the new spec, and confirm the filtering tests fail. Then `git checkout includes/Integrations/MainQueryFilter.php` and confirm `git status --short includes/` is empty.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e
git commit -m "test: Cover archive and search templates end to end"
```

---

### Task 5: Documentation

**Files:**

- Modify: `docs/hooks.md`, `CHANGELOG.md`, `README.md`

- [ ] **Step 1: Update the docs**

- **`docs/hooks.md`:** the URL parameter table gains its inherited column (`query-{key}`, with `s` for search and `paged` or `/page/N/` for pagination). Add a short section on where inherited parameters apply (§3.4): home, archives and search only; never singular requests or 404s; post-type filters ignored on a post type archive; author filters intersected on an author archive. Remove the "search only, until B2" notes.
- **`CHANGELOG.md`**, under `[Unreleased]` → `### Added`: filters, sorting and search now work in Query Loops that inherit the template's query, so archive and search templates can carry them.
- **`README.md`:** remove the line saying inherited loops support search only, and update the feature list.

- [ ] **Step 2: Run every check**

Run: `composer test`, `npm test`, `npm run lint:js`, `npm run lint:css`, `npm run lint:php`, `npm run build`, `npm run test:e2e`. Record the counts.

- [ ] **Step 3: Commit**

```bash
git add docs/hooks.md CHANGELOG.md README.md
git commit -m "docs: Document filtering in inherited Query Loops"
```

- [ ] **Step 4: Stop**

Don't push, and don't open the PR. The controller does that after the whole-branch review.

---

## Self-Review Notes

- **Spec coverage:** §3.4 → Tasks 2 and 5. §4.4 → Tasks 1 and 2. §4.5 → Task 3. §3.1's inherited rows → Tasks 2 and 5. §10.1's B2 row → all.
- **Deliberately not in B2:** the loop form, control names, `<noscript>` and the store rewrite (B3); editor settings (C); the loading state and "clear all" (D). Inherited loops still can't be filtered without JavaScript until B3 gives them a form.
- **Known risk:** Task 2's tests mock `WP_Query` heavily, so they prove the decisions rather than the SQL. Task 4's browser tests are what prove the SQL and the archive identity, which is why they run against real templates.
- **Names used across tasks:** `TaxonomySubquery::where()`; `MainQueryFilter::original()`, `::applied()`, `->filter_main_query()`, `->skip_404_for_filtered_archives()`; `TEMPLATES` in the E2E fixtures.
