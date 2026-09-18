# Query Filter 1.0 — B1: URL Contract and Query Engine for Custom Loops Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the ad-hoc URL parsing in `QueryLoopHandler` with four small classes that define the 1.0 URL contract for custom Query Loops, switch sort to a single allowlisted key, switch authors to nicenames, and fix the three query-result bugs B0 pinned.

**Architecture:**

- `Url\QueryParams` owns every parameter name for one loop.
- `Url\FilterState` parses and validates `$_GET` into typed values, once per loop per request.
- `Query\QueryArgs` merges those values into `WP_Query` arguments as a pure function.
- `Query\SortOptions` owns the sort list, which is both what Sort blocks render and the allowlist requests are checked against.
- `Core\QueryLoopHandler` becomes a thin adapter on the `query_loop_block_query_vars` hook. Inherited loops stay untouched; B2 adds them.

**Tech Stack:** PHP 8.4, PHPUnit 9.6 with Brain\Monkey and Mockery; `@wordpress/interactivity` view module; Playwright 1.63 with `@wordpress/e2e-test-utils-playwright`.

**Spec:** `docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md` (revision 2, approved). B1 implements §3.1–§3.3 and §3.6 for custom loops, §4.1, §4.2, §4.3, §4.5's author rule, and §10.1's B1 row.

## Global Constraints

- **Branch:** `feature/url-contract-custom-loops`, cut from `main` at `de49322` (B0 merged, plugin version 0.3.4).
- **Label:** the PR gets `breaking` **by hand**. No branch prefix maps to it, and the release draft resolves 1.0.0 from it.
- **Scope:** custom Query Loops only. Do **not** add `pre_get_posts`, the loop `<form>`, control `name`/`form` attributes, or the store rewrite. Those are B2 and B3.
- **Code style:** PHP follows WordPress Coding Standards with **4 spaces, not tabs** (`phpcs.xml` excludes `tests/`, so check test files yourself). JavaScript follows the WordPress ESLint config; `tests/e2e/**` and `playwright.config.js` are ESLint-ignored.
- **Commits:** `type: Brief description`, no `Co-Authored-By` lines, and never `--no-verify`.
- **TDD:** every behaviour change starts with a failing test. Characterization tests that B0 wrote may only change where §3.6 or §3.2 says so, and each such change gets its own test named for it.
- **Environment:** this plugin's wp-env (5884 dev, 5885 tests). Never touch other Docker containers or ddev sites, and never run `wp-env destroy` or `wp-env clean`. Run `npm run build` before E2E.
- **Test counts to keep green:** PHP 55 and Jest 30 at the start; both grow. E2E is 13 at the start.

## File Structure

| File                                                                                                | Responsibility                                                                      |
| --------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `includes/Url/QueryParams.php` (create)                                                             | Parameter names for one loop.                                                       |
| `includes/Url/FilterState.php` (create)                                                             | Parsed, validated request values, memoized per loop.                                |
| `includes/Query/QueryArgs.php` (create)                                                             | Pure merge into `WP_Query` args.                                                    |
| `includes/Query/SortOptions.php` (create)                                                           | The filterable sort list, lookup and match.                                         |
| `includes/Core/QueryLoopHandler.php` (modify)                                                       | Thin adapter on `query_loop_block_query_vars`.                                      |
| `includes/Helpers/AbstractQueryHelper.php` (delete)                                                 | Replaced by `QueryParams`.                                                          |
| `includes/Helpers/SortHelper.php` (delete)                                                          | Replaced by `SortOptions` + `QueryParams`.                                          |
| `includes/Helpers/FilterHelper.php` (modify)                                                        | Drops the config and current-value methods; author option values become nicenames.  |
| `includes/Helpers/AuthorHelper.php` (modify)                                                        | Drops `get_author_filter_config()`.                                                 |
| `includes/Integrations/BlockFilters.php` (modify)                                                   | Search naming via `QueryParams`.                                                    |
| `src/blocks/query-filter/render.php` (modify)                                                       | Names and current value via `QueryParams` / `FilterState`.                          |
| `src/blocks/sort/render.php` (modify)                                                               | Single sort key, options from `SortOptions`, loop-default option.                   |
| `src/blocks/query-filter/view.js` (modify)                                                          | `handleSort` writes one `sort` parameter.                                           |
| `src/blocks/query-filter/edit.js` (modify)                                                          | Author preview value becomes the nicename.                                          |
| `tests/php/{QueryParams,FilterState,QueryArgs,SortOptions}Test.php` (create)                        | Unit tests for the new classes.                                                     |
| `tests/php/QueryLoopHandlerTest.php` (modify)                                                       | Becomes the adapter's test; detail moves to the new tests.                          |
| `tests/unit/blocks/query-filter/view.test.js` (modify)                                              | `handleSort` writes the new parameter.                                              |
| `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js` (modify)                             | Sticky post, an uncategorized post, an author whose slug differs from the username. |
| `tests/e2e/specs/{sort,filters}.spec.js` (modify), `tests/e2e/specs/query-results.spec.js` (create) | Sort key in the URL, author nicename in the URL, sticky posts excluded.             |
| `docs/hooks.md`, `CHANGELOG.md`, `CLAUDE.md` (modify)                                               | The public contract, breaking changes, and the render-flow description.             |

---

### Task 1: `QueryParams`

**Files:**

- Create: `includes/Url/QueryParams.php`, `tests/php/QueryParamsTest.php`

**Interfaces:**

- Consumes: nothing.
- Produces, used by every later task:

  - `new QueryParams( ?int $query_id, bool $inherit = false )`
  - `QueryParams::from_block( \WP_Block $block ): self`
  - `->prefix(): string` — `query-3-`, `query-0-`, or `query-` when inherited
  - `->key( string $name ): string` — `$name` is `post_type`, `author`, `sort`, `s`, or a taxonomy name
  - `->page_key(): string`
  - `->is_inherit(): bool`

- [ ] **Step 1: Write the failing test**

Create `tests/php/QueryParamsTest.php`. Use 4-space indentation.

```php
<?php
/**
 * Tests for the URL parameter names of one Query Loop.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Mockery;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Pikari\Tests\TestCase;

class QueryParamsTest extends TestCase {

    public function test_custom_loop_keys_carry_the_query_id(): void {
        $params = new QueryParams( 3 );

        $this->assertSame( 'query-3-', $params->prefix() );
        $this->assertSame( 'query-3-post_type', $params->key( 'post_type' ) );
        $this->assertSame( 'query-3-category', $params->key( 'category' ) );
        $this->assertSame( 'query-3-author', $params->key( 'author' ) );
        $this->assertSame( 'query-3-sort', $params->key( 'sort' ) );
        $this->assertSame( 'query-3-s', $params->key( 's' ) );
        $this->assertSame( 'query-3-page', $params->page_key() );
    }

    /**
     * Core uses `query-page` for a loop with no queryId (post-template.php:50),
     * while the plugin's own keys fall back to 0 (spec §3.1).
     */
    public function test_loop_without_query_id_uses_zero_but_cores_page_key(): void {
        $params = new QueryParams( null );

        $this->assertSame( 'query-0-', $params->prefix() );
        $this->assertSame( 'query-0-category', $params->key( 'category' ) );
        $this->assertSame( 'query-page', $params->page_key() );
    }

    public function test_inherited_loop_keys_have_no_query_id(): void {
        $params = new QueryParams( null, true );

        $this->assertTrue( $params->is_inherit() );
        $this->assertSame( 'query-', $params->prefix() );
        $this->assertSame( 'query-category', $params->key( 'category' ) );
        $this->assertSame( 'query-sort', $params->key( 'sort' ) );
        $this->assertSame( 'paged', $params->page_key() );
    }

    /**
     * Inherited loops search with core's own parameter (spec §3.1).
     */
    public function test_inherited_loop_search_uses_cores_parameter(): void {
        $this->assertSame( 's', ( new QueryParams( null, true ) )->key( 's' ) );
    }

    public function test_from_block_reads_the_query_context(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array(
            'queryId' => 7,
            'query'   => array( 'inherit' => false ),
        );

        $this->assertSame( 'query-7-', QueryParams::from_block( $block )->prefix() );
    }

    public function test_from_block_treats_a_missing_query_id_as_none(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array( 'inherit' => false ) );

        $this->assertSame( 'query-page', QueryParams::from_block( $block )->page_key() );
    }

    public function test_from_block_reads_inherit(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array( 'inherit' => true ) );

        $this->assertTrue( QueryParams::from_block( $block )->is_inherit() );
    }

    public function test_from_block_without_inherit_in_context_treats_the_loop_as_custom(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array() );

        $this->assertFalse( QueryParams::from_block( $block )->is_inherit() );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `vendor/bin/phpunit tests/php/QueryParamsTest.php`
Expected: errors, `Class "Pikari\GutenbergQueryFilter\Url\QueryParams" not found`.

- [ ] **Step 3: Write the class**

Create `includes/Url/QueryParams.php` in namespace `Pikari\GutenbergQueryFilter\Url`, with the `ABSPATH` guard the other classes use and full PHPDoc.

Rules:

- The constructor takes `?int $query_id` and `bool $inherit = false`, and stores `$query_id` as `null` when it's null, otherwise `absint()`.
- `prefix()`: `query-` when inherited, otherwise `sprintf( 'query-%d-', $this->query_id ?? 0 )`.
- `key( string $name )`: `s` in an inherited loop returns `'s'`; everything else returns `prefix() . $name`.
- `page_key()`: `paged` when inherited; `query-page` when `$query_id` is null; otherwise `prefix() . 'page'`.
- `is_inherit()`: the stored flag.
- `from_block()`: reads `$block->context['queryId'] ?? null` and `$block->context['query']['inherit'] ?? false`.

Run `composer dump-autoload` after creating the file.

- [ ] **Step 4: Run the test**

Run: `vendor/bin/phpunit tests/php/QueryParamsTest.php --testdox`
Expected: 8 passing.

- [ ] **Step 5: Commit**

```bash
git add includes/Url/QueryParams.php tests/php/QueryParamsTest.php composer.json composer.lock
git commit -m "feat: Define the 1.0 URL parameter names in QueryParams"
```

(`composer.json` and `composer.lock` only change if `dump-autoload` touched them; drop them from the `git add` if `git status` shows them unchanged.)

---

### Task 2: `SortOptions`

**Files:**

- Create: `includes/Query/SortOptions.php`, `tests/php/SortOptionsTest.php`

**Interfaces:**

- Consumes: nothing.
- Produces:

  - `SortOptions::all(): array` — a list of `array{ key: string, label: string, orderby: string, order: 'ASC'|'DESC', meta_key?: string }`
  - `SortOptions::find( string $key ): ?array`
  - `SortOptions::match( string $orderby, string $order ): ?array` — case-insensitive
  - the filter `pikari_gutenberg_query_filter_sort_options`

- [ ] **Step 1: Write the failing test**

Create `tests/php/SortOptionsTest.php`:

```php
<?php
/**
 * Tests for the sort option list, which is also the request allowlist.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Pikari\GutenbergQueryFilter\Query\SortOptions;
use Pikari\Tests\TestCase;

class SortOptionsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        Functions\stubTranslationFunctions();
        Functions\when( 'sanitize_key' )->alias(
            function ( $key ) {
                return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
            }
        );
    }

    public function test_default_options_are_the_four_date_and_title_orders(): void {
        $this->assertSame(
            array( 'date-desc', 'date-asc', 'title-asc', 'title-desc' ),
            array_column( SortOptions::all(), 'key' )
        );
    }

    public function test_default_options_carry_orderby_and_uppercase_order(): void {
        $option = SortOptions::find( 'title-asc' );

        $this->assertSame( 'title', $option['orderby'] );
        $this->assertSame( 'ASC', $option['order'] );
        $this->assertNotSame( '', $option['label'] );
    }

    public function test_options_are_filterable(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->once();

        SortOptions::all();
    }

    public function test_a_filtered_option_becomes_findable(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) {
                $options[] = array(
                    'key'     => 'menu-order',
                    'label'   => 'Menu order',
                    'orderby' => 'menu_order',
                    'order'   => 'asc',
                );

                return $options;
            }
        );

        $this->assertSame( 'menu_order', SortOptions::find( 'menu-order' )['orderby'] );
        $this->assertSame( 'ASC', SortOptions::find( 'menu-order' )['order'] );
    }

    /**
     * @dataProvider provide_invalid_options
     *
     * @param array $option Option a filter added.
     */
    public function test_invalid_options_are_dropped( array $option ): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) use ( $option ) {
                $options[] = $option;

                return $options;
            }
        );

        $this->assertCount( 4, SortOptions::all() );
    }

    /**
     * @return array<string, array{array}>
     */
    public static function provide_invalid_options(): array {
        return array(
            'no key'                  => array( array( 'label' => 'x', 'orderby' => 'date' ) ),
            'no label'                => array( array( 'key' => 'x', 'orderby' => 'date' ) ),
            'no orderby'              => array( array( 'key' => 'x', 'label' => 'x' ) ),
            'meta_value without meta_key' => array( array( 'key' => 'x', 'label' => 'x', 'orderby' => 'meta_value' ) ),
            'meta_value_num, no key'  => array( array( 'key' => 'x', 'label' => 'x', 'orderby' => 'meta_value_num' ) ),
        );
    }

    public function test_a_meta_option_with_a_meta_key_survives(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) {
                $options[] = array(
                    'key'      => 'price',
                    'label'    => 'Price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'asc',
                    'meta_key' => 'price',
                );

                return $options;
            }
        );

        $this->assertSame( 'price', SortOptions::find( 'price' )['meta_key'] );
    }

    public function test_unknown_keys_are_not_found(): void {
        $this->assertNull( SortOptions::find( 'rand' ) );
        $this->assertNull( SortOptions::find( '' ) );
    }

    public function test_match_finds_the_option_for_a_loops_own_order(): void {
        $this->assertSame( 'date-desc', SortOptions::match( 'date', 'desc' )['key'] );
        $this->assertSame( 'title-asc', SortOptions::match( 'title', 'ASC' )['key'] );
    }

    public function test_match_returns_null_when_nothing_matches(): void {
        $this->assertNull( SortOptions::match( 'relevance', 'DESC' ) );
        $this->assertNull( SortOptions::match( '', '' ) );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `vendor/bin/phpunit tests/php/SortOptionsTest.php`
Expected: class not found.

- [ ] **Step 3: Write the class**

Create `includes/Query/SortOptions.php` in namespace `Pikari\GutenbergQueryFilter\Query`.

- `all()`:
  - builds the four defaults from spec §4.3, with translated labels in the `pikari-gutenberg-query-filter` text domain;
  - applies `pikari_gutenberg_query_filter_sort_options` with a PHPDoc block above the call, saying the same list renders the Sort block and validates requests, and that no block attributes are passed on purpose;
  - normalizes: `key` through `sanitize_key()`, `order` uppercased with anything other than `ASC`/`DESC` becoming `DESC`, and `meta_key` kept when present;
  - drops options missing `key`, `label` or `orderby`, and options whose `orderby` is `meta_value` or `meta_value_num` without a `meta_key`;
  - re-indexes the list with `array_values()`.
- `find( $key )`: the first option whose `key` matches, else `null`. An empty key returns `null`.
- `match( $orderby, $order )`: the first option whose `orderby` matches case-insensitively and whose `order` matches case-insensitively, else `null`. An empty `$orderby` returns `null`.

Don't cache the result: a filter may legitimately depend on request state.

- [ ] **Step 4: Run the test**

Run: `vendor/bin/phpunit tests/php/SortOptionsTest.php --testdox`
Expected: 13 passing (the provider contributes 5).

- [ ] **Step 5: Commit**

```bash
git add includes/Query/SortOptions.php tests/php/SortOptionsTest.php
git commit -m "feat: Add the filterable sort option list that also validates requests"
```

---

### Task 3: `FilterState`

**Files:**

- Create: `includes/Url/FilterState.php`, `tests/php/FilterStateTest.php`

**Interfaces:**

- Consumes: `QueryParams`, `SortOptions`.
- Produces:

  - `FilterState::from_array( array $get, QueryParams $params ): self`
  - `FilterState::for_loop( QueryParams $params ): self` — reads `$_GET` and memoizes per prefix
  - `FilterState::reset_cache(): void` — for tests
  - `->post_types(): array`, `->taxonomies(): array` (taxonomy ⇒ slugs), `->author_ids(): ?array`, `->search(): string`, `->sort(): ?array`, `->has_filters(): bool`

- [ ] **Step 1: Write the failing test**

Create `tests/php/FilterStateTest.php`. Give it a `setUp()` that stubs, in the WordPress-function style B0's `QueryLoopHandlerTest` uses:

- `sanitize_text_field`, `wp_unslash` → `returnArg()`
- `absint` → `abs( (int) $value )`
- `sanitize_title_for_query` → `strtolower( (string) $value )`
- `is_post_type_viewable` → true for `post`, `page`, `resource`; false otherwise
- `is_taxonomy_viewable` → true for `category`, `post_tag`, `language`; false otherwise
- `get_taxonomies` → `array( 'category' => 'category', 'post_tag' => 'post_tag', 'language' => 'language' )`
- `get_option` → `false` for `wp_attachment_pages_enabled`
- `get_current_blog_id` → `1`
- `get_users` → a stub the tests override per case
- `sanitize_key`, `stubTranslationFunctions` for `SortOptions`

Write these tests (custom loop, `new QueryParams( 3 )` unless stated):

1. `test_no_parameters_means_no_filters` — `has_filters()` false, `post_types()` empty, `taxonomies()` empty, `author_ids()` null, `search()` `''`, `sort()` null.
2. `test_post_types_keep_only_viewable_ones` — `'page, resource,nope'` → `array( 'page', 'resource' )`.
3. `test_attachment_is_rejected_unless_attachment_pages_are_enabled` — `'attachment'` → empty, with `is_post_type_viewable('attachment')` stubbed true and `get_option('wp_attachment_pages_enabled')` false. Then with the option true → `array( 'attachment' )`.
4. `test_taxonomy_values_are_slugs` — `query-3-category=News, Events` → `array( 'category' => array( 'news', 'events' ) )` (the `sanitize_title_for_query` stub lowercases).
5. `test_non_viewable_taxonomies_are_ignored` — a `query-3-secret=x` key, with `get_taxonomies` including `secret` but `is_taxonomy_viewable('secret')` false → `taxonomies()` empty.
6. `test_array_values_are_read_like_comma_lists` — `array( 'query-3-category' => array( 'news', 'events' ) )` → the same result as the comma form. This is the no-JS `key[]` form from spec §3.2.
7. `test_values_are_deduplicated_and_capped_at_50` — 60 distinct slugs plus a repeat → `assertCount( 50, … )` and no duplicates.
8. `test_empty_values_are_ignored` — `query-3-category=` → `taxonomies()` empty; `query-3-s=` → `search()` `''`.
9. `test_authors_resolve_by_nicename` — `query-3-author=jane-doe,sam-lee`, with `get_users` expecting `nicename__in` and returning two objects with `ID` and `user_nicename` → `author_ids()` is those IDs in the parameter's order.
10. `test_numeric_author_values_resolve_by_id_when_no_nicename_matches` — `query-3-author=7`, first call returns `array()`, second call (`include`) returns `array( 7 )` → `array( 7 )`.
11. `test_a_numeric_nicename_wins_over_the_same_id` — `query-3-author=12` where the nicename lookup returns a user with `user_nicename` `12` and `ID` 99 → `array( 99 )`, and the `include` lookup is never called.
12. `test_authors_that_resolve_to_nobody_return_no_results` — `query-3-author=nobody` with both lookups empty → `array( 0 )`, and `has_filters()` is true. Docblock: spec §3.2, a change from 0.3.4, which ignored the parameter.
13. `test_author_lookups_are_limited_to_this_site` — assert the `get_users` arguments include `blog_id => 1`.
14. `test_search_is_sanitized` — `query-3-s=mango` → `'mango'`.
15. `test_sort_resolves_through_the_allowlist` — `query-3-sort=title-asc` → `sort()['orderby']` is `title`, `order` is `ASC`.
16. `test_unknown_sort_keys_are_ignored` — `query-3-sort=rand` → `sort()` null. Docblock: this is what stops `orderby=rand` reaching `WP_Query` (spec §3.2, §3.6).
17. `test_parameters_for_another_loop_are_ignored` — `query-30-category` with `new QueryParams( 3 )` → empty.
18. `test_inherited_loop_reads_unnumbered_keys_and_core_search` — `new QueryParams( null, true )`, `query-category=news` and `s=mango` → taxonomy and `search()` set.
19. `test_for_loop_reads_the_request_once_per_prefix` — set `$_GET`, call `for_loop()` twice, change `$_GET` between calls, and assert the second call returns the first result. Call `FilterState::reset_cache()` in `tearDown`.
20. `test_for_loop_caches_per_prefix` — two different `QueryParams` (3 and 4) get their own state.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/FilterStateTest.php`
Expected: class not found.

- [ ] **Step 3: Write the class**

Create `includes/Url/FilterState.php` in namespace `Pikari\GutenbergQueryFilter\Url`. Keep the constructor private and build through the two factories.

Rules, all from spec §3.2:

- **Reading a key:** accept a string or an array. A string splits on commas. Both then trim, drop empty values, `array_unique()`, and `array_slice( …, 0, 50 )`. Re-index with `array_values()`.
- **Post types:** keep values where `is_post_type_viewable()` is true; drop `attachment` unless `get_option( 'wp_attachment_pages_enabled' )`.
- **Taxonomies:** for each name in `get_taxonomies( array( 'public' => true ), 'names' )` that also passes `is_taxonomy_viewable()`, read `$params->key( $taxonomy )`; map values through `sanitize_title_for_query()`. Skip empty results.
- **Authors:** read the key, then resolve in at most two `get_users()` calls, both with `blog_id => get_current_blog_id()`:

  1. `array( 'nicename__in' => $values, 'fields' => array( 'ID', 'user_nicename' ), 'number' => 50 )`;
  2. for values that are all digits and matched no nicename: `array( 'include' => $ids, 'fields' => 'ID', 'number' => 50 )`.

  Return IDs in the order the values appear. If the parameter was present but nothing resolved, the state holds `array( 0 )`.

- **Search:** `sanitize_text_field( wp_unslash( … ) )`, empty means absent.
- **Sort:** `SortOptions::find()` on the raw value; unknown or empty means null.
- **`has_filters()`:** true when any of post types, taxonomies, authors, search or sort is set.
- **`for_loop()`:** a private static array keyed by `$params->prefix()`. `reset_cache()` empties it. The memo exists because core builds a custom loop's query vars up to 6 times per page (spec §4.1).

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit tests/php/FilterStateTest.php --testdox`
Expected: all passing, at least 20 tests.

- [ ] **Step 5: Commit**

```bash
git add includes/Url/FilterState.php tests/php/FilterStateTest.php
git commit -m "feat: Parse and validate filter parameters in FilterState"
```

---

### Task 4: `QueryArgs`

**Files:**

- Create: `includes/Query/QueryArgs.php`, `tests/php/QueryArgsTest.php`

**Interfaces:**

- Consumes: `FilterState`.
- Produces: `QueryArgs::apply( array $args, FilterState $state ): array`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/QueryArgsTest.php`. Build states through `FilterState::from_array()` with a `new QueryParams( 3 )`, reusing Task 3's stubs (extract them into a small private helper in this file rather than sharing across test classes).

Tests, each asserting one rule from spec §4.2:

1. `test_no_filters_leaves_the_arguments_untouched` — `assertSame( $args, QueryArgs::apply( $args, $state ) )`.
2. `test_post_types_replace_the_loops_post_type` — one value gives a string, two give an array.
3. `test_post_type_filter_keeps_the_loops_post__in` — docblock: changed from 0.3.4, which removed it and broke sticky "only" (spec §3.6).
4. `test_taxonomy_filter_adds_an_in_clause_by_slug`.
5. `test_two_taxonomies_are_joined_with_and`.
6. `test_an_existing_tax_query_is_nested_unchanged` — an existing `array( 'relation' => 'OR', … )` ends up as `array( 'relation' => 'AND', $existing, $filters )` with the existing relation still `OR`. Docblock: 0.3.4 forced it to AND, which broke core's post-format filter (spec §3.6, §4.2).
7. `test_authors_set_author__in`.
8. `test_authors_that_resolved_to_nobody_return_no_posts` — `author__in` is `array( 0 )`.
9. `test_search_sets_s`.
10. `test_sort_sets_orderby_and_order`.
11. `test_a_meta_sort_also_sets_meta_key`.
12. `test_any_filter_ignores_sticky_posts` — with only a taxonomy filter, `ignore_sticky_posts` is `true`. Docblock: 0.3.4 set it only for post types, so core added sticky posts that didn't match (spec §1, §3.6, §4.2).
13. `test_a_loops_own_ignore_sticky_posts_is_kept` — a loop that set `0` keeps `0`.
14. `test_apply_does_not_mutate_the_arguments_it_was_given` — call `apply()` and assert the original array is unchanged.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/QueryArgsTest.php`
Expected: class not found.

- [ ] **Step 3: Write the class**

Create `includes/Query/QueryArgs.php`. `apply()` copies `$args`, applies the rules above, and returns the copy. No WordPress calls belong here beyond what the state already resolved.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit tests/php/QueryArgsTest.php --testdox`
Expected: 14 passing.

- [ ] **Step 5: Commit**

```bash
git add includes/Query/QueryArgs.php tests/php/QueryArgsTest.php
git commit -m "feat: Merge filter state into query arguments with QueryArgs"
```

---

### Task 5: Rewrite `QueryLoopHandler` on the new classes

**Files:**

- Modify: `includes/Core/QueryLoopHandler.php`, `tests/php/QueryLoopHandlerTest.php`

**Interfaces:**

- Consumes: `QueryParams::from_block()`, `FilterState::for_loop()`, `QueryArgs::apply()`.
- Produces: the same public surface as today — a constructor that adds `query_loop_block_query_vars` at priority 19 with 3 args, and `modify_query( array $query_args, \WP_Block $block, int $page ): array`.

- [ ] **Step 1: Update the characterization tests to the 1.0 contract**

B0's `QueryLoopHandlerTest` pins 0.3.4. Change only what spec §3.6 and §3.2 allow, and give every changed test a docblock naming the change:

- **Keep unchanged:** the constructor test, "no parameters", "another query id", the post type tests except `post__in`, the taxonomy IN clause, empty slugs, two taxonomies, non-viewable taxonomy keys, and search.
- **Change:**
  - `test_post_type_filter_removes_post__in` → `test_post_type_filter_keeps_post__in`;
  - `test_taxonomy_filter_leaves_ignore_sticky_posts_unset` → `test_any_filter_ignores_sticky_posts`;
  - `test_existing_tax_query_is_nested_with_its_relation_forced_to_and` → `test_existing_tax_query_is_nested_with_its_own_relation`;
  - the author tests → nicename resolution, with `get_users` stubbed;
  - `test_orderby_passes_any_value_through`, `test_order_is_uppercased` and `test_order_other_than_asc_or_desc_is_ignored` → one test that `query-3-orderby`/`query-3-order` are now ignored, and one that `query-3-sort=title-asc` sets `orderby`/`order`.
- **Trim:** the detailed validation cases now live in `FilterStateTest` and `QueryArgsTest`. Leave `QueryLoopHandlerTest` with the adapter's behaviour: hook registration, the prefix cases, and one example of each filter type reaching the query arguments.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php`
Expected: the changed tests fail against the old implementation, for example `ignore_sticky_posts` missing and the relation forced to AND. Note which fail, and why, in your report.

- [ ] **Step 3: Rewrite the handler**

`modify_query()` becomes:

```php
    public function modify_query( array $query_args, \WP_Block $block, int $page ): array {
        unset( $page );

        $params = QueryParams::from_block( $block );

        if ( $params->is_inherit() ) {
            // Core never applies this filter to inherited loops; B2 handles them
            // through pre_get_posts (spec §4.4).
            return $query_args;
        }

        return QueryArgs::apply( $query_args, FilterState::for_loop( $params ) );
    }
```

Delete `parse_query_parameters()` and `parse_taxonomy_parameters()`. Keep the class's hook registration as it is.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php --testdox`, then `composer test`.
Expected: all passing. Report the new totals.

- [ ] **Step 5: Commit**

```bash
git add includes/Core/QueryLoopHandler.php tests/php/QueryLoopHandlerTest.php
git commit -m "refactor: Build Query Loop arguments from the new URL classes"
```

---

### Task 6: Move the render paths onto `QueryParams`, and delete the old helpers

**Files:**

- Modify: `src/blocks/query-filter/render.php`, `includes/Integrations/BlockFilters.php`, `includes/Helpers/FilterHelper.php`, `includes/Helpers/AuthorHelper.php`, `tests/php/BlockFiltersTest.php`
- Delete: `includes/Helpers/AbstractQueryHelper.php`, `includes/Helpers/SortHelper.php`

**Interfaces:**

- Consumes: `QueryParams`, `FilterState`.
- Produces: `render.php` and `render_block_search()` both take their parameter names from `QueryParams`. `FilterHelper` keeps only its option methods: `get_filter_post_types()`, `get_taxonomy_filter_terms()`, `get_filter_options()`, `get_all_option()`, `get_option_classes()`, `get_option_label_html()`.

- [ ] **Step 1: Write the failing test for the search naming**

In `tests/php/BlockFiltersTest.php`, add a test that `render_block_search()` on a loop with `queryId` 3 sets the input's `name` to `query-3-s`, and one that a loop whose context has no `queryId` gets `query-0-s` — 0.3.4 skipped those loops entirely because of an `empty()` check. Keep the existing search tests.

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php`
Expected: the no-`queryId` test fails, because the block returns unchanged content.

- [ ] **Step 2: Update the consumers**

- **`BlockFilters::render_block_search()`:** build `QueryParams::from_block( $instance )`, use `key( 's' )` and `page_key()`, and change the early return from `empty( $query_id )` to a check that the block has `query` context. Leave the rest of the method alone; B3 reworks it.
- **`src/blocks/query-filter/render.php`:**
  - replace the three `FilterHelper::get_*_config()` calls with one `QueryParams::from_block( $block )`;
  - `$query_var` becomes `$params->key( 'post_type' )`, `$params->key( $taxonomy )` or `$params->key( 'author' )`;
  - `$page_var` becomes `$params->page_key()`;
  - replace `FilterHelper::get_current_filter_value( $query_var )` with a read of `$_GET[ $query_var ]` through `sanitize_text_field( wp_unslash( … ) )`, keeping the existing phpcs ignore comment for nonce verification;
  - keep the `data-wp-context` keys as they are. B3 changes them.
- **Delete** `AbstractQueryHelper.php` and `SortHelper.php`, and the methods `FilterHelper::get_post_type_filter_config()`, `get_taxonomy_filter_config()`, `get_current_filter_value()` and `AuthorHelper::get_author_filter_config()`. Make `FilterHelper` and `AuthorHelper` plain classes again (no `extends`).

Run `composer dump-autoload`, then `grep -rn "AbstractQueryHelper\|SortHelper\|get_current_filter_value\|_filter_config" includes src tests` and confirm the only hits are in the sort block's `render.php`, which Task 7 rewrites.

- [ ] **Step 3: Run the tests**

Run: `composer test`
Expected: all passing, including the two new search tests.

- [ ] **Step 4: Check the rendered page**

Run `npm run build`, then `npm run test:e2e -- tests/e2e/specs/filters.spec.js`.
Expected: 7 passing. The filter parameter names don't change in B1, so these still pass unchanged.

- [ ] **Step 5: Commit**

```bash
git add -A includes src/blocks/query-filter/render.php tests/php/BlockFiltersTest.php
git commit -m "refactor: Name filter and search parameters from QueryParams"
```

---

### Task 7: One sort key, end to end

**Files:**

- Modify: `src/blocks/sort/render.php`, `src/blocks/query-filter/view.js`, `tests/unit/blocks/query-filter/view.test.js`, `tests/e2e/specs/sort.spec.js`

**Interfaces:**

- Consumes: `QueryParams`, `SortOptions`, `FilterState`.
- Produces: the Sort block renders `<select>` options whose values are sort keys, with `data-wp-context` carrying `sortVar` and `pageVar`; `actions.handleSort` writes one parameter.

- [ ] **Step 1: Write the failing Jest test**

In `tests/unit/blocks/query-filter/view.test.js`, add tests for `handleSort`:

- with context `{ sortVar: 'query-3-sort', pageVar: 'query-3-page' }` and a select value of `title-asc`, the navigated URL has `query-3-sort=title-asc` and no `query-3-page`;
- with an empty value, `query-3-sort` is removed;
- the old `query-3-orderby` / `query-3-order` parameters are never written.

Follow the file's existing pattern for invoking a generator action and reading the URL the router mock received.

Run: `npm test -- --testPathPattern=view`
Expected: failures showing `orderby`/`order` being written.

- [ ] **Step 2: Update `handleSort`**

In `src/blocks/query-filter/view.js`, replace the `orderbyVar`/`orderVar` logic with a single `sortVar`: set it when the value is non-empty, delete it otherwise, and keep the existing page-parameter removal and `navigate()` call. Don't touch the other actions.

Run: `npm test`
Expected: Jest green, with the new tests passing.

- [ ] **Step 3: Rewrite the Sort block's render**

`src/blocks/sort/render.php`:

- build `QueryParams::from_block( $block )` and `$sort_var = $params->key( 'sort' )`;
- read the current key from `$_GET[ $sort_var ]` and resolve it with `SortOptions::find()`;
- get the loop's own order from `$block->context['query']['orderBy'] ?? ''` and `['order'] ?? ''`, and find its option with `SortOptions::match()`;
- render one `<option>` per `SortOptions::all()` entry:
  - the option matching the loop's own order gets `value=""`, so choosing it removes the parameter (spec §4.3);
  - every other option gets `value="{key}"`;
  - the selected option is the one the request resolved to, or the loop-default option when there's no parameter;
- when `match()` finds nothing, render an empty first option labelled with `emptyLabel`, defaulting to `__( 'Default', … )` through `?:` so an empty attribute value falls back;
- `data-wp-context` carries `sortVar` and `pageVar` only;
- keep the existing label markup, classes and `data-wp-on--change`.

- [ ] **Step 4: Update the E2E sort spec**

In `tests/e2e/specs/sort.spec.js`, keep the results assertion and add:

- after choosing "Title (A-Z)", `param( page, 'query-3-sort' )` is `title-asc`;
- choosing the option that matches the loop's own order (Date, newest first) removes `query-3-sort` from the URL and restores date order.

Use `waitForParam` for both.

- [ ] **Step 5: Run everything for this task**

Run: `composer test`, `npm test`, `npm run build`, `npm run test:e2e -- tests/e2e/specs/sort.spec.js`
Expected: all green, with the sort spec now at 2 tests.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/sort/render.php src/blocks/query-filter/view.js tests/unit/blocks/query-filter/view.test.js tests/e2e/specs/sort.spec.js
git commit -m "feat: Sort with a single allowlisted key in the URL"
```

---

### Task 8: Author values become nicenames

**Files:**

- Modify: `includes/Helpers/FilterHelper.php`, `src/blocks/query-filter/edit.js`, `tests/php/FilterHelperTest.php`

**Interfaces:**

- Consumes: nothing new.
- Produces: an author option's `value` is the user's nicename in both the frontend option list and the editor preview. Its `slug` stays the nicename, so the `author_{nicename}` option class is unchanged.

- [ ] **Step 1: Write the failing test**

In `tests/php/FilterHelperTest.php`, add a test that `get_filter_options()` with `filterType` `author` and a user object (`ID` 7, `display_name` "Jane Doe", `user_nicename` `jane-doe`) returns `value` `jane-doe`, `label` "Jane Doe", `slug` `jane-doe`. If an existing test asserts the numeric ID, update it and note the change in its docblock (spec §3.6).

Run: `vendor/bin/phpunit tests/php/FilterHelperTest.php`
Expected: failure showing `7`.

- [ ] **Step 2: Change the option value**

In `FilterHelper::get_filter_options()`, the `author` branch sets `'value' => $item->user_nicename`.

Run: `composer test`
Expected: green.

- [ ] **Step 3: Match the editor preview**

In `src/blocks/query-filter/edit.js`, the author preview maps to `value: author.slug` instead of `author.id`. The REST users endpoint returns `slug`, which is the nicename.

Run: `npm test` and `npm run lint:js`
Expected: green.

- [ ] **Step 4: Commit**

```bash
git add includes/Helpers/FilterHelper.php src/blocks/query-filter/edit.js tests/php/FilterHelperTest.php
git commit -m "feat: Identify authors by nicename in filter URLs"
```

---

### Task 9: End-to-end coverage for the new contract

**Files:**

- Modify: `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js`, `tests/e2e/specs/filters.spec.js`, `tests/e2e/specs/harness.spec.js`
- Create: `tests/e2e/specs/query-results.spec.js`

**Interfaces:**

- Consumes: the harness helpers from B0.
- Produces: fixture exports gain `STICKY_TITLE` and an author whose REST `slug` differs from the username; `newestTitles()` accounts for the sticky post.

- [ ] **Step 1: Extend the fixtures**

In `tests/e2e/fixtures/content.js`:

- add a 13th post, `'Quince'`, dated before the others, in a new `uncategorized` category, by `jane-doe`, and mark it `sticky: true`. Export `STICKY_TITLE = 'Quince'`;
- give one author a REST `slug` that differs from its username, for example username `sam-lee` with `slug` `sam-the-editor`. Export the slug, so specs can assert the URL value;
- keep `newestTitles()` honest: sticky posts appear first on page 1 of an unfiltered loop, so either give it a parameter for "unfiltered page 1" that prepends the sticky title, or set `ignoreSticky` on the fixture loops. **Choose the first**, so the sticky behaviour is visible in the tests.

In `tests/e2e/setup/fixtures.js`, pass `sticky` and `slug` through to the REST calls, and create the extra category.

- [ ] **Step 2: Fix the affected existing specs**

`harness.spec.js` and `filters.spec.js` assert `newestTitles()`. Update those call sites for the sticky-first ordering, and use the new uncategorized post to make the "both categories" case differ from the unfiltered page (the B0 final review's M1).

Run: `npm run build && npm run test:e2e`
Expected: green, with the counts you now expect. Report them.

- [ ] **Step 3: Write the new spec**

Create `tests/e2e/specs/query-results.spec.js`, browsing as `visitor`:

1. **Sticky posts don't leak into filtered results.** On the filters page, the unfiltered first page starts with the sticky post. Check a category the sticky post isn't in, and assert its title is absent. This is the §3.6 sticky fix.
2. **The author parameter uses the nicename.** Select the author whose slug differs from the username, then assert `param( page, 'query-1-author' )` equals that slug, and that the results are that author's posts.
3. **Old numeric author links still work.** Go to the filters page with `?query-1-author=<id>`, where the id comes from a `requestUtils` lookup in the spec's `beforeAll`, and assert the same results. Spec §3.2 keeps numeric IDs working.
4. **An unknown author returns nothing.** `?query-1-author=nobody` shows no results, which is the §3.6 change from 0.3.4's "ignore it".

- [ ] **Step 4: Run the whole suite**

Run: `npm run build && npm run test:e2e`
Expected: green. Report the total.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e
git commit -m "test: Cover sticky posts, author nicenames and old author links end to end"
```

---

### Task 10: Documentation, changelog and the full check

**Files:**

- Modify: `docs/hooks.md`, `CHANGELOG.md`, `CLAUDE.md`

**Interfaces:**

- Consumes: everything above.
- Produces: the public contract as documented.

- [ ] **Step 1: Document the URL contract and the new filter in `docs/hooks.md`**

Add a "URL parameters" section before "Frontend Markup", holding spec §3.1's table for custom loops, §3.2's value rules (comma lists, the 50-value cap, term slugs, viewable post types, authors by nicename with numeric IDs still accepted, sort keys from the allowlist), and the note that multiple values are written in the order the options appear on the page.

Add a `pikari_gutenberg_query_filter_sort_options` section in the Filters list, in the same shape as the three existing hooks: what it filters, the option array, a worked example that adds a `menu_order` option, and the limitations (the same list renders blocks and validates requests; `meta_value` options need a `meta_key`; a meta sort hides posts without that key; the editor's Sort preview ignores the filter).

Update the option-array table: an author option's `value` is now the nicename.

Mark inherited loops as "search only, until B2" wherever the doc implies otherwise.

- [ ] **Step 2: Write the changelog entries**

In `CHANGELOG.md` under `[Unreleased]`, add a `### Breaking changes` section covering, in plain language: sort links change to a single `query-{id}-sort` key and old sort links fall back to the loop's order; author links use nicenames, with numeric IDs still accepted; an author value matching nobody now returns no results; and the internal helper classes that were removed.

Add `### Fixed` entries for the three query-result bugs: sticky posts appearing in filtered results, the loop's own taxonomy relation being forced to AND, and a post-type filter dropping the loop's `post__in`.

- [ ] **Step 3: Update `CLAUDE.md`'s render flow**

Rewrite the "Query Filter render flow" steps that describe `FilterHelper::get_*_config()` and `SortHelper`, so they describe `QueryParams`, `FilterState`, `QueryArgs` and `SortOptions`. Update the extension rules that name those helpers. Leave the parts about option classes and `view.js` alone; B3 rewrites them.

- [ ] **Step 4: Run every check**

Run:

```bash
composer test
npm test
npm run lint:js && npm run lint:css && npm run lint:php
npm run build
npm run test:e2e
```

Expected: all green. Record every count.

- [ ] **Step 5: Commit**

```bash
git add docs/hooks.md CHANGELOG.md CLAUDE.md
git commit -m "docs: Document the 1.0 URL contract and the sort options filter"
```

- [ ] **Step 6: Stop here**

Don't push and don't open the PR. The controller does that after the whole-branch review, with the `breaking` label applied by hand.

---

## Self-Review Notes

- **Spec coverage:**
  - §3.1 and §3.2 → Tasks 1, 3 and 10.
  - §3.3 ownership → **B3**, since it only matters once controls live in a form.
  - §3.6 → Tasks 4, 5, 7, 8 and 10.
  - §4.1 → Tasks 1–5.
  - §4.2 → Task 4.
  - §4.3 → Tasks 2 and 7.
  - §4.5 author values → Task 8.
  - §10.1 B1 row → all tasks.
- **Deliberately not in B1:** `MainQueryFilter` and everything inherited (B2); the loop form, control names, `<noscript>`, the store rewrite and the injected-styles changes (B3); inherited post-type options and inherited sort defaults (B2).
- **Names used across tasks:** `QueryParams::from_block`, `->key`, `->page_key`, `->prefix`, `->is_inherit`; `FilterState::from_array`, `::for_loop`, `::reset_cache`, `->post_types`, `->taxonomies`, `->author_ids`, `->search`, `->sort`, `->has_filters`; `QueryArgs::apply`; `SortOptions::all`, `::find`, `::match`.
- **Known risk for the executor:** Task 9's fixture change ripples into two existing specs. Run the whole E2E suite after it, not just the new spec.
