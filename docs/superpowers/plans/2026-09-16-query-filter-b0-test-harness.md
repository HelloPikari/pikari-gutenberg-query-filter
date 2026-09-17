# Query Filter 1.0 — B0: Test Harness and Characterization Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pin down how the plugin behaves today (v0.3.4) with PHP characterization tests and a Playwright end-to-end suite, so B1–B3 can change the query engine and markup without silently breaking what visitors rely on.

**Architecture:**

- **PHP characterization:** `QueryLoopHandlerTest` drives `QueryLoopHandler::modify_query()` with Brain\Monkey stubs and a Mockery `WP_Block`, recording current behaviour (including known bugs, marked as such).
- **Playwright:**
  - The suite extends the `@wordpress/scripts` config.
  - It runs against the wp-env tests instance (port 5885) with pretty permalinks.
  - A global setup rebuilds deterministic content on every run: 12 posts, 2 categories, 2 authors, 3 pages.
  - Specs assert only what visitors see and the URL parameters the 1.0 contract keeps. Sort and author parameter names change in B1, so those specs assert results, not URLs.
- **No production code changes.**

**Tech Stack:** PHPUnit 9.6 + Brain\Monkey + Mockery; Playwright 1.63 via `wp-scripts test-playwright`; `@wordpress/e2e-test-utils-playwright` 1.54 `RequestUtils`; `@wordpress/env` 10.39.

**Spec:** `docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md` (revision 2): §8.1, §8.2 item 1, §8.4, §10.1 row B0.

## Global Constraints

- **Code style:**
  - PHP uses WordPress Coding Standards with **4 spaces, not tabs** (`phpcs.xml`).
  - JavaScript follows the WordPress ESLint config. `tests/e2e/**` and `playwright.config.js` are ESLint-ignored, but keep the same style.
- **Git:**
  - Commit format is `type: Brief description`.
  - **No `Co-Authored-By` lines** (plugin `CLAUDE.md`).
  - Work on branch `chore/query-filter-test-harness`, which already holds the spec commits and is rebased on `main` at `37decb0` (v0.3.4).
- **Scope:**
  - No production code changes in B0: nothing under `includes/`, `src/`, or `pikari-gutenberg-query-filter.php`.
  - Mutation checks edit production files **temporarily** and must be reverted before committing.
- **Environment:**
  - The wp-env tests instance is disposable. The fixture setup deletes all its posts, pages, non-admin users and non-default categories.
  - Don't point E2E at port 5884 (development) or at any ddev site.
- **PR:** label `skip-changelog` by hand. It changes no user-facing behaviour, so the release draft stays empty and no version bump runs.

## File Structure

| File                                                        | Responsibility                                                                                                      |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `tests/unit/__mocks__/@wordpress/interactivity.js` (modify) | Match the updated monorepo template (`readStore`, `getConfig`).                                                     |
| `tests/php/QueryLoopHandlerTest.php` (create)               | Characterization tests for `QueryLoopHandler`.                                                                      |
| `package.json` / `package-lock.json` (modify)               | `test:e2e` script; `@playwright/test` and `@wordpress/e2e-test-utils-playwright` devDependencies.                   |
| `.wp-env.json` (modify)                                     | `lifecycleScripts.afterStart` sets pretty permalinks on the tests instance.                                         |
| `.gitignore` (modify)                                       | Ignore `artifacts/`.                                                                                                |
| `playwright.config.js` (create)                             | Extends the wp-scripts config: `testDir`, global setup chain, `webServer` command.                                  |
| `tests/e2e/fixtures/content.js` (create)                    | Fixture data (posts, categories, authors, page block markup) and expected-result helpers shared by setup and specs. |
| `tests/e2e/setup/fixtures.js` (create)                      | Global setup that rebuilds the fixture content through REST.                                                        |
| `tests/e2e/utils.js` (create)                               | Spec helpers: result titles, same-document marker, URL parameters.                                                  |
| `tests/e2e/specs/harness.spec.js` (create)                  | Smoke test that each fixture page renders its blocks.                                                               |
| `tests/e2e/specs/filters.spec.js` (create)                  | Checkbox, author, search, pagination reset, Back, accessible names.                                                 |
| `tests/e2e/specs/sort.spec.js` (create)                     | A loop containing only a Sort block.                                                                                |
| `tests/e2e/specs/injected-styles.spec.js` (create)          | Script-injected styles survive filtering and enhanced pagination.                                                   |
| `CLAUDE.md` (modify)                                        | How to run the E2E suite.                                                                                           |

---

### Task 1: Bring the Interactivity mock back in line with its template

The plugin's `tests/unit/__mocks__/@wordpress/interactivity.js` added `readStore`, so `store( 'core/router' )` works in `view.test.js`. On 2026-09-16 the monorepo session copied that into the template (monorepo `a92292d`), and also added a generic `getConfig()` for modals.

The plugin copy now differs from the template only in `getConfig` and the header comment. Copy the template so the mock is template-managed again.

Don't add the file to `skip-sync`. Test is a required check, so a sync that breaks the mock would fail its own PR. A `skip-sync` entry would also cut this plugin off from future mock fixes.

**Files:**

- Modify: `tests/unit/__mocks__/@wordpress/interactivity.js`

**Interfaces:**

- Consumes: `../.github/config-templates/tests/unit/__mocks__/@wordpress/interactivity.js` (monorepo template).
- Produces: the mock exports `store`, `getContext`, `getElement`, `getConfig`, `withScope`, `withSyncEvent`. `withScope` still returns its callback unchanged; B3 changes it in the plugin copy first, then hands it to the monorepo session. Non-generator callbacks must still pass through, because modals relies on that.

- [ ] **Step 1: Check the remaining difference**

Run: `diff ../.github/config-templates/tests/unit/__mocks__/@wordpress/interactivity.js tests/unit/__mocks__/@wordpress/interactivity.js`

Expected: only the header comment line mentioning `getConfig()`, the `const getConfig = jest.fn( () => ( {} ) );` line, and `getConfig,` in `module.exports`. If `readStore` also shows up, the template hasn't been updated: stop and ask the monorepo session rather than editing the template.

- [ ] **Step 2: Copy the template**

Run: `cp ../.github/config-templates/tests/unit/__mocks__/@wordpress/interactivity.js tests/unit/__mocks__/@wordpress/interactivity.js`

Then run the diff from Step 1 again. Expected: no output.

- [ ] **Step 3: Run the JS suite**

Run: `npm test`

Expected: `Tests: 30 passed, 30 total`.

- [ ] **Step 4: Commit**

```bash
git add tests/unit/__mocks__/@wordpress/interactivity.js
git commit -m "test: Match the Interactivity mock to its template"
```

---

### Task 2: Characterization tests for `QueryLoopHandler`

These record how 0.3.4 turns URL parameters into query arguments for custom Query Loops. B1 ports them to the new classes and may change only the behaviours spec §3.6 lists. Tests that record a known bug say so in their docblock.

Characterization tests pass on the first run. To show each one can fail, Step 3 temporarily breaks the handler.

**Files:**

- Create: `tests/php/QueryLoopHandlerTest.php`

**Interfaces:**

- Consumes: `Pikari\GutenbergQueryFilter\Core\QueryLoopHandler::modify_query( array $query_args, \WP_Block $block, int $page ): array`, and the constructor, which adds the `query_loop_block_query_vars` filter.
- Produces: nothing B0 uses later. In B1, these assertions move to `QueryArgsTest` / `FilterStateTest`.

- [ ] **Step 1: Write the test file**

Create `tests/php/QueryLoopHandlerTest.php`:

```php
<?php
/**
 * Characterization tests for QueryLoopHandler (0.3.4 behaviour).
 *
 * These pin how URL parameters become Query Loop query arguments before the
 * 1.0 rewrite. Tests describing a known bug say so; B1 changes only the
 * behaviours listed in the 1.0 spec, section 3.6.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Core\QueryLoopHandler;
use Pikari\Tests\TestCase;

class QueryLoopHandlerTest extends TestCase {

    /**
     * Post types the stubs treat as registered and viewable.
     */
    private const VIEWABLE_POST_TYPES = array( 'post', 'page', 'resource' );

    protected function setUp(): void {
        parent::setUp();

        $_GET = array();

        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'absint' )->alias(
            function ( $value ) {
                return abs( (int) $value );
            }
        );
        Functions\when( 'post_type_exists' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, array_merge( self::VIEWABLE_POST_TYPES, array( 'wp_block' ) ), true );
            }
        );
        Functions\when( 'is_post_type_viewable' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, self::VIEWABLE_POST_TYPES, true );
            }
        );
        Functions\when( 'get_taxonomies' )->justReturn(
            array(
                'category' => 'category',
                'post_tag' => 'post_tag',
            )
        );
        Functions\when( 'taxonomy_exists' )->justReturn( true );
    }

    protected function tearDown(): void {
        $_GET = array();
        parent::tearDown();
    }

    /**
     * Build a Query Loop inner block with the given query context.
     *
     * @param array    $query    The `query` block context.
     * @param int|null $query_id The `queryId` block context, or null to leave it unset.
     * @return \WP_Block Block mock.
     */
    private function block( array $query = array( 'inherit' => false ), ?int $query_id = 3 ): \WP_Block {
        $context = array( 'query' => $query );

        if ( null !== $query_id ) {
            $context['queryId'] = $query_id;
        }

        // Assign once: Mockery creates the property on first write.
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = $context;

        return $block;
    }

    /**
     * Run the handler for a custom loop with the given URL parameters.
     *
     * @param array $get        URL parameters.
     * @param array $query_args Query arguments core built for the loop.
     * @return array Filtered query arguments.
     */
    private function filter( array $get, array $query_args = array() ): array {
        $_GET = $get;

        return ( new QueryLoopHandler() )->modify_query( $query_args, $this->block(), 1 );
    }

    /*
     * Hook registration
     */

    public function test_constructor_filters_query_loop_query_vars_at_priority_19(): void {
        $handler = new QueryLoopHandler();

        $this->assertSame( 19, has_filter( 'query_loop_block_query_vars', array( $handler, 'modify_query' ) ) );
    }

    /*
     * No parameters
     */

    public function test_query_args_are_unchanged_without_parameters(): void {
        $query_args = array(
            'post_type'      => 'post',
            'posts_per_page' => 5,
        );

        $this->assertSame( $query_args, $this->filter( array(), $query_args ) );
    }

    public function test_parameters_for_another_query_id_are_ignored(): void {
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame(
            $query_args,
            $this->filter( array( 'query-30-category' => 'news' ), $query_args )
        );
    }

    /*
     * Post types
     */

    public function test_single_post_type_replaces_post_type_as_a_string(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'post_type' => 'post' ) );

        $this->assertSame( 'page', $result['post_type'] );
    }

    public function test_multiple_post_types_keep_only_viewable_ones_as_an_array(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page, wp_block,resource,nope' ) );

        $this->assertSame( array( 'page', 'resource' ), $result['post_type'] );
    }

    public function test_post_type_filter_is_ignored_when_no_type_is_valid(): void {
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame( $query_args, $this->filter( array( 'query-3-post_type' => 'wp_block,nope' ), $query_args ) );
    }

    public function test_post_type_filter_ignores_sticky_posts_when_the_loop_has_not_decided(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ) );

        $this->assertTrue( $result['ignore_sticky_posts'] );
    }

    public function test_post_type_filter_keeps_the_loops_ignore_sticky_posts(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'ignore_sticky_posts' => 0 ) );

        $this->assertSame( 0, $result['ignore_sticky_posts'] );
    }

    /**
     * Known behaviour B1 changes: a post type filter should keep the loop's
     * post__in, for example sticky "only" (spec §3.6).
     */
    public function test_post_type_filter_removes_post__in(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'post__in' => array( 7, 9 ) ) );

        $this->assertArrayNotHasKey( 'post__in', $result );
    }

    /*
     * Taxonomies
     */

    public function test_taxonomy_parameter_adds_an_in_clause_by_slug(): void {
        $result = $this->filter( array( 'query-3-category' => 'news,events' ) );

        $this->assertSame(
            array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => array( 'news', 'events' ),
                    'operator' => 'IN',
                ),
            ),
            $result['tax_query']
        );
    }

    public function test_empty_term_slugs_are_dropped(): void {
        $result = $this->filter( array( 'query-3-category' => 'news,, events' ) );

        $this->assertSame( array( 'news', 'events' ), array_values( $result['tax_query'][0]['terms'] ) );
    }

    public function test_two_taxonomies_are_joined_with_and(): void {
        $result = $this->filter(
            array(
                'query-3-category' => 'news',
                'query-3-post_tag' => 'featured',
            )
        );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
        $this->assertSame( 'post_tag', $result['tax_query'][1]['taxonomy'] );
    }

    public function test_parameters_for_non_public_taxonomies_are_ignored(): void {
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame( $query_args, $this->filter( array( 'query-3-language' => 'fr' ), $query_args ) );
    }

    /**
     * Known bug B1 fixes: the loop's own relation is overwritten with AND,
     * so an OR tax_query (such as core's post format filter) stops matching
     * (spec §1, §3.6, §4.2).
     */
    public function test_existing_tax_query_is_nested_with_its_relation_forced_to_and(): void {
        $existing = array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'post_format',
                'field'    => 'slug',
                'terms'    => array( 'post-format-gallery' ),
                'operator' => 'IN',
            ),
        );

        $result = $this->filter( array( 'query-3-category' => 'news' ), array( 'tax_query' => $existing ) );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( 'AND', $result['tax_query'][0]['relation'] );
        $this->assertSame( 'post_format', $result['tax_query'][0][0]['taxonomy'] );
        $this->assertSame( 'category', $result['tax_query'][1][0]['taxonomy'] );
    }

    /*
     * Authors
     */

    public function test_author_parameter_sets_author__in_to_positive_integer_ids(): void {
        $result = $this->filter( array( 'query-3-author' => '7,abc,0,12' ) );

        $this->assertSame( array( 7, 12 ), array_values( $result['author__in'] ) );
    }

    /**
     * Known behaviour B1 changes: unresolvable authors return no results
     * instead of being ignored (spec §3.2, §3.6).
     */
    public function test_author_parameter_without_valid_ids_is_ignored(): void {
        $this->assertArrayNotHasKey( 'author__in', $this->filter( array( 'query-3-author' => 'jane-doe' ) ) );
    }

    /*
     * Search
     */

    public function test_search_parameter_sets_s(): void {
        $this->assertSame( 'mango', $this->filter( array( 'query-3-s' => 'mango' ) )['s'] );
    }

    public function test_empty_search_parameter_is_ignored(): void {
        $this->assertArrayNotHasKey( 's', $this->filter( array( 'query-3-s' => '' ) ) );
    }

    /*
     * Sorting
     */

    /**
     * Known behaviour B1 changes: any orderby value reaches WP_Query. 1.0 reads a
     * single sort key from an allowlist instead (spec §3.2, §3.6).
     */
    public function test_orderby_passes_any_value_through(): void {
        $this->assertSame( 'rand', $this->filter( array( 'query-3-orderby' => 'rand' ) )['orderby'] );
    }

    public function test_order_is_uppercased(): void {
        $this->assertSame( 'ASC', $this->filter( array( 'query-3-order' => 'asc' ) )['order'] );
    }

    public function test_order_other_than_asc_or_desc_is_ignored(): void {
        $this->assertArrayNotHasKey( 'order', $this->filter( array( 'query-3-order' => 'sideways' ) ) );
    }

    /*
     * Parameter prefixes
     */

    public function test_loop_without_query_id_reads_query_0_parameters(): void {
        $_GET = array( 'query-0-category' => 'news' );

        $result = ( new QueryLoopHandler() )->modify_query( array(), $this->block( array( 'inherit' => false ), null ), 1 );

        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
    }

    /**
     * Core never calls this filter for inherited loops (spec §1), so this
     * branch only runs when another plugin applies the filter itself.
     */
    public function test_inherited_loop_reads_unnumbered_parameters_and_core_search(): void {
        $_GET = array(
            'query-category' => 'news',
            's'              => 'mango',
        );

        $result = ( new QueryLoopHandler() )->modify_query( array(), $this->block( array( 'inherit' => true ) ), 1 );

        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
        $this->assertSame( 'mango', $result['s'] );
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php --testdox`

Expected: `OK (24 tests, …)`.

If a test fails, don't change the test to match your expectation. First re-read `includes/Core/QueryLoopHandler.php`:

- If the code really does something different, record what it does.
- If the stubs are wrong (e.g. Mockery can't set `context`), fix the stub.

- [ ] **Step 3: Prove the tests can fail (temporary mutations, then revert)**

Apply each mutation on its own, run `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php`, confirm the named test fails, then revert with `git checkout includes/Core/QueryLoopHandler.php`:

1. In `parse_taxonomy_parameters()`, change `'operator' => 'IN',` to `'operator' => 'AND',`. Expected failure: `test_taxonomy_parameter_adds_an_in_clause_by_slug`.
2. In `modify_query()`, delete the `unset( $query_args['post__in'] );` line. Expected failure: `test_post_type_filter_removes_post__in`.
3. In `parse_query_parameters()`, change `sprintf( 'query-%d-', $query_id )` to `sprintf( 'query-%d_', $query_id )`. Expected failures: the post type, taxonomy, author and order tests for custom loops. The search tests still pass, because the search key is built by a separate `sprintf`.

Then run: `git status --short includes/`

Expected: no output (all mutations reverted).

- [ ] **Step 4: Run the whole PHP suite and lint**

Run: `composer test && composer lint`

Expected: `OK (55 tests, …)` (31 existing + 24 new) and no phpcs errors.

- [ ] **Step 5: Commit**

```bash
git add tests/php/QueryLoopHandlerTest.php
git commit -m "test: Characterize how QueryLoopHandler maps URL parameters to query arguments"
```

---

### Task 3: Playwright harness and fixture content

**Files:**

- Modify: `package.json`, `package-lock.json`, `.wp-env.json`, `.gitignore`
- Create: `playwright.config.js`, `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js`, `tests/e2e/utils.js`, `tests/e2e/specs/harness.spec.js`

**Interfaces:**

- Consumes: the wp-env tests instance at `http://localhost:5885`; `build/` from `npm run build`.
- Produces, used by Tasks 4–6:

  - `tests/e2e/fixtures/content.js` exports `PAGES` (`{ filters, enhanced, sortOnly }`, each `{ slug, path, queryId, title, content }`), `POSTS` (array of `{ title, date, category, author }`), `CATEGORIES`, `AUTHORS`, `newestTitles( predicate, count = 5 )`, `titlesByTitle( count = 5 )` and `INJECTED_COLOR` (`'rgb(1, 2, 3)'`).
  - `tests/e2e/utils.js` exports:
    - `resultTitles( page ): Promise<string[]>`
    - `markDocument( page ): Promise<void>`
    - `isSameDocument( page ): Promise<boolean>`
    - `param( page, name ): string|null`
    - `waitForParam( page, name, value ): Promise<void>`, where `value` null means "absent"
    - `visitor`, a Playwright `storageState` value for a logged-out browser.

- [ ] **Step 1: Install the Playwright packages and the browser**

Run:

```bash
npm install --save-dev @playwright/test@^1.59.1 @wordpress/e2e-test-utils-playwright@^1.43.0
npx playwright install chromium
```

Expected: both appear under `devDependencies` in `package.json`, and `package-lock.json` changes. Chromium downloads, or reports it's already installed.

- [ ] **Step 2: Point `test:e2e` at the Playwright runner**

In `package.json`, change `"test:e2e": "wp-scripts test-e2e",` to:

```json
		"test:e2e": "wp-scripts test-playwright",
```

- [ ] **Step 3: Give the tests instance pretty permalinks**

Replace `.wp-env.json` with:

```json
{
	"phpVersion": "8.4",
	"plugins": ["."],
	"port": 5884,
	"env": {
		"tests": {
			"port": 5885
		}
	},
	"lifecycleScripts": {
		"afterStart": "npx wp-env run tests-cli wp rewrite structure /%postname%/ --hard"
	}
}
```

`--hard` writes `.htaccess`. Without it the tests instance has no `.htaccess`, and every pretty permalink 404s (checked 2026-09-16: `/sample-page/` 404 → 200 after `wp rewrite flush --hard`).

Run: `npx wp-env start && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:5885/sample-page/`

Expected: the start output ends with the `afterStart` command succeeding, then `200`. The Sample Page is deleted in Step 7, which is fine; this only proves the rewrite works.

- [ ] **Step 4: Ignore Playwright artifacts**

Append to `.gitignore`:

```gitignore

# Playwright artifacts (test results, storage state)
artifacts/
```

- [ ] **Step 5: Write the Playwright config**

Create `playwright.config.js`:

```js
/**
 * Playwright configuration for end-to-end tests.
 *
 * Extends the @wordpress/scripts defaults. `npm run test:e2e` sets WP_BASE_URL
 * to the wp-env tests instance from .wp-env.json (port 5885).
 */
const path = require('path');
const baseConfig = require('@wordpress/scripts/config/playwright.config.js');

// Playwright rejects `port` and `url` together, so drop the inherited port.
const { port, ...webServer } = baseConfig.webServer;

module.exports = {
	...baseConfig,
	testDir: './tests/e2e/specs',
	// Authenticate as admin first, then rebuild the fixture content.
	globalSetup: [
		require.resolve('@wordpress/scripts/config/playwright/global-setup.js'),
		path.resolve(__dirname, 'tests/e2e/setup/fixtures.js'),
	],
	webServer: {
		...webServer,
		// The package's own `wp-env` script adds --xdebug and can't take `start`.
		// Keep the process alive after wp-env start exits; Playwright fails if
		// the webServer process exits before the URL is ready, and kills it at teardown.
		command: 'npx wp-env start && tail -f /dev/null',
		// /wp-json/ 404s until pretty permalinks and .htaccess exist, so readiness waits for afterStart.
		url: new URL('wp-json/', baseConfig.use.baseURL).href,
		timeout: 300_000,
	},
};
```

- [ ] **Step 6: Write the fixture data**

Create `tests/e2e/fixtures/content.js`:

```js
/**
 * Deterministic content for the end-to-end suite.
 *
 * Titles are chosen so date order and title order differ, and each category
 * and author owns a different set of posts.
 */

const INJECTED_COLOR = 'rgb(1, 2, 3)';

const CATEGORIES = [
	{ name: 'News', slug: 'news' },
	{ name: 'Events', slug: 'events' },
];

const AUTHORS = [
	{ username: 'jane-doe', name: 'Jane Doe' },
	{ username: 'sam-lee', name: 'Sam Lee' },
];

const TITLES = [
	'Kiwi',
	'Apple',
	'Lemon',
	'Banana',
	'Mango',
	'Cherry',
	'Nectarine',
	'Date',
	'Orange',
	'Elderberry',
	'Papaya',
	'Fig',
];

// Index 0 is the oldest post. Even indexes are News, odd are Events.
// The first six are Jane's, the last six Sam's.
const POSTS = TITLES.map((title, index) => ({
	title,
	date: `2026-01-${String(index + 1).padStart(2, '0')}T09:00:00`,
	category: index % 2 === 0 ? 'news' : 'events',
	author: index < 6 ? 'jane-doe' : 'sam-lee',
}));

/**
 * Titles of the newest posts matching a predicate, newest first.
 *
 * @param {Function} predicate Receives a POSTS entry.
 * @param {number}   count     Posts per page.
 * @return {string[]} Titles.
 */
const newestTitles = (predicate = () => true, count = 5) =>
	POSTS.filter(predicate)
		.reverse()
		.slice(0, count)
		.map((post) => post.title);

/**
 * The first titles in alphabetical order.
 *
 * @param {number} count Posts per page.
 * @return {string[]} Titles.
 */
const titlesByTitle = (count = 5) => [...TITLES].sort().slice(0, count);

// A script that injects an id-less <style> while the page parses, the way
// WPForms adds its honeypot CSS. The router disables such styles on navigation.
const injectedStyleBlock = `<!-- wp:html -->
<script>document.head.appendChild(Object.assign(document.createElement('style'),{textContent:'.e2e-injected{color:${INJECTED_COLOR}}'}));</script>
<p class="e2e-injected">Injected style check</p>
<!-- /wp:html -->`;

const queryBlock = (queryId, innerBlocks, attributes = {}) =>
	`<!-- wp:query ${JSON.stringify({
		queryId,
		query: {
			perPage: 5,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
		},
		...attributes,
	})} -->
<div class="wp-block-query">
${innerBlocks}
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->`;

const PAGES = {
	filters: {
		slug: 'e2e-filters',
		path: '/e2e-filters/',
		queryId: 1,
		title: 'E2E filters',
		content: `${queryBlock(
			1,
			`<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Category","displayType":"checkbox"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"author","label":"Author"} /-->
<!-- wp:search {"label":"Search","buttonText":"Search"} /-->`
		)}
${injectedStyleBlock}`,
	},
	enhanced: {
		slug: 'e2e-enhanced',
		path: '/e2e-enhanced/',
		queryId: 2,
		title: 'E2E enhanced pagination',
		content: `${queryBlock(
			2,
			'<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Topic","displayType":"radio"} /-->',
			{ enhancedPagination: true }
		)}
${injectedStyleBlock}`,
	},
	sortOnly: {
		slug: 'e2e-sort-only',
		path: '/e2e-sort-only/',
		queryId: 3,
		title: 'E2E sort only',
		content: queryBlock(
			3,
			'<!-- wp:pikari-gutenberg-query-filter/sort {"label":"Sort by"} /-->'
		),
	},
};

module.exports = {
	AUTHORS,
	CATEGORIES,
	INJECTED_COLOR,
	PAGES,
	POSTS,
	newestTitles,
	titlesByTitle,
};
```

- [ ] **Step 7: Write the fixture global setup**

Create `tests/e2e/setup/fixtures.js`:

```js
/**
 * Global setup: rebuild the fixture content on the wp-env tests instance.
 *
 * Runs after the @wordpress/scripts global setup has saved the admin storage
 * state. Everything it creates is deleted and recreated on every run.
 */
const fs = require('fs');
const path = require('path');
const { request } = require('@playwright/test');
const { RequestUtils } = require('@wordpress/e2e-test-utils-playwright');
const { AUTHORS, CATEGORIES, PAGES, POSTS } = require('../fixtures/content');

const deleteCategories = async (requestUtils) => {
	const categories = await requestUtils.rest({
		path: '/wp/v2/categories',
		params: { per_page: 100 },
	});

	// Category 1 is the default category, which WordPress won't delete.
	await Promise.all(
		categories
			.filter((category) => category.id !== 1)
			.map((category) =>
				requestUtils.rest({
					method: 'DELETE',
					path: `/wp/v2/categories/${category.id}`,
					params: { force: true },
				})
			)
	);
};

module.exports = async function fixtures(config) {
	const buildDir = path.resolve(__dirname, '../../../build/blocks');
	if (!fs.existsSync(buildDir)) {
		throw new Error(
			'build/blocks is missing. Run `npm run build` before `npm run test:e2e`.'
		);
	}

	const { storageState, baseURL } = config.projects[0].use;
	const requestContext = await request.newContext({ baseURL });
	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath: storageState,
	});
	await requestUtils.setupRest();

	await requestUtils.deleteAllPosts();
	await requestUtils.deleteAllPages();
	await requestUtils.deleteAllUsers();
	await deleteCategories(requestUtils);

	const categoryIds = {};
	for (const { name, slug } of CATEGORIES) {
		const term = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name, slug },
		});
		categoryIds[slug] = term.id;
	}

	const authorIds = {};
	for (const { username, name } of AUTHORS) {
		const user = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username,
				name,
				email: `${username}@example.com`,
				password: 'password',
				roles: ['author'],
			},
		});
		authorIds[username] = user.id;
	}

	for (const post of POSTS) {
		await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				title: post.title,
				status: 'publish',
				date: post.date,
				categories: [categoryIds[post.category]],
				author: authorIds[post.author],
			},
		});
	}

	for (const page of Object.values(PAGES)) {
		await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: page.title,
				slug: page.slug,
				status: 'publish',
				content: page.content,
			},
		});
	}

	await requestContext.dispose();
};
```

- [ ] **Step 8: Write the spec helpers**

Create `tests/e2e/utils.js`:

```js
/**
 * Helpers shared by the end-to-end specs.
 */

// Browse as a logged-out visitor rather than the admin storage state.
const visitor = { cookies: [], origins: [] };

/**
 * Post titles rendered by the page's Query Loop, in display order.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string[]>} Titles.
 */
const resultTitles = async (page) =>
	(
		await page.locator('.wp-block-query .wp-block-post-title').allTextContents()
	).map((title) => title.trim());

/**
 * Mark the current document, so a later check can tell whether a full page load happened.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const markDocument = (page) =>
	page.evaluate(() => {
		window.e2eSameDocument = true;
	});

/**
 * Whether the document marked by markDocument() is still the current one.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<boolean>} True when no full page load happened.
 */
const isSameDocument = (page) =>
	page.evaluate(() => window.e2eSameDocument === true);

/**
 * A URL parameter of the current page, decoded.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          name Parameter name.
 * @return {string|null} Value, or null when absent.
 */
const param = (page, name) => new URL(page.url()).searchParams.get(name);

/**
 * Wait until a URL parameter has a value, or is absent when value is null.
 *
 * Times out after 10s rather than the full test timeout, so a missed
 * navigation fails fast instead of waiting out the whole test.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {string}                          name  Parameter name.
 * @param {string|null}                     value Expected value.
 */
const waitForParam = (page, name, value) =>
	page.waitForURL((url) => new URL(url).searchParams.get(name) === value, {
		timeout: 10_000,
	});

module.exports = {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
};
```

- [ ] **Step 9: Write the harness smoke spec**

Create `tests/e2e/specs/harness.spec.js`:

```js
/**
 * Smoke test: each fixture page renders its Query Loop and plugin blocks.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles } = require('../fixtures/content');
const { resultTitles, visitor } = require('../utils');

test.use({ storageState: visitor });

for (const [key, fixture] of Object.entries(PAGES)) {
	test(`renders the ${key} fixture page`, async ({ page }) => {
		const response = await page.goto(fixture.path);

		expect(response.status()).toBe(200);
		await expect(
			page.locator('.wp-block-pikari-gutenberg-query-filter').first()
		).toBeVisible();
		expect(await resultTitles(page)).toEqual(newestTitles());
	});
}
```

- [ ] **Step 10: Build and run the harness**

Run:

```bash
npm run build
npm run test:e2e -- tests/e2e/specs/harness.spec.js
```

Expected: `3 passed`.

If it fails:

- **404s:** re-check Step 3.
- **"build/blocks is missing":** run the build.
- **REST errors while creating users:** read the error body. The admin user must be `admin` / `password`, which is wp-env's default.

- [ ] **Step 11: Prove the smoke test can fail, then revert**

Temporarily change `perPage: 5` to `perPage: 4` in `tests/e2e/fixtures/content.js`, run `npm run test:e2e -- tests/e2e/specs/harness.spec.js`, and confirm 3 failures (4 titles instead of 5).

The file isn't committed yet, so undo the edit by hand. Confirm with `grep -n "perPage" tests/e2e/fixtures/content.js`, which should show `perPage: 5`.

- [ ] **Step 12: Check JS lint and unit tests still pass, then commit**

Run: `npm run lint:js && npm test`

Expected: no ESLint errors (`tests/e2e/**` and `playwright.config.js` are ignored), `Tests: 30 passed`.

```bash
git add package.json package-lock.json .wp-env.json .gitignore playwright.config.js tests/e2e
git commit -m "test: Add a Playwright harness with deterministic fixture content"
```

---

### Task 4: Filter, author and search behaviour specs

These pin what the 1.0 contract keeps. The category parameter stays `query-{id}-{taxonomy}=a,b` (spec §3.1), so the specs may assert it. The author parameter changes to nicenames in B1, so its spec asserts results only.

**Files:**

- Create: `tests/e2e/specs/filters.spec.js`

**Interfaces:**

- Consumes: `PAGES`, `POSTS`, `newestTitles` from `tests/e2e/fixtures/content.js`; `resultTitles`, `markDocument`, `isSameDocument`, `param`, `waitForParam`, `visitor` from `tests/e2e/utils.js`.
- Produces: nothing.

- [ ] **Step 1: Write the spec**

Create `tests/e2e/specs/filters.spec.js`:

```js
/**
 * Filtering a custom Query Loop: checkboxes, author select, search.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
} = require('../utils');

const { path, queryId } = PAGES.filters;
const categoryParam = `query-${queryId}-category`;
const isNews = (post) => post.category === 'news';

// FilterHelper::get_taxonomy_filter_terms() calls get_terms() with no
// orderby, so terms render name-ASC: Events before News. updateFilters()
// joins the checked checkboxes in DOM order, not click order, so both
// checked always serializes as "events,news".

test.use({ storageState: visitor });

test.beforeEach(async ({ page }) => {
	await page.goto(path);
});

test('names the category group and labels the author select', async ({
	page,
}) => {
	await expect(page.getByRole('group', { name: 'Category' })).toBeVisible();
	await expect(page.getByRole('combobox', { name: 'Author' })).toBeVisible();
});

test('filters by a category without a full page load', async ({ page }) => {
	await markDocument(page);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	expect(await isSameDocument(page)).toBe(true);
});

test('writes checked categories as one comma-separated parameter', async ({
	page,
}) => {
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));

	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles());
});

test('filters by author', async ({ page }) => {
	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });

	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles((post) => post.author === 'jane-doe'));
});

test('filters by search text', async ({ page }) => {
	await page.getByRole('searchbox', { name: 'Search' }).fill('Mango');
	await waitForParam(page, `query-${queryId}-s`, 'Mango');

	await expect.poll(() => resultTitles(page)).toEqual(['Mango']);
});

test('returns to page 1 when a filter changes', async ({ page }) => {
	await page.goto(`${path}?query-${queryId}-page=2`);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	expect(param(page, `query-${queryId}-page`)).toBeNull();
});

test('restores the previous filter on Back', async ({ page }) => {
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');
	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');

	await page.goBack();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	await expect(
		page.getByRole('checkbox', { name: 'Events' })
	).not.toBeChecked();
});
```

- [ ] **Step 2: Run the spec**

Run: `npm run test:e2e -- tests/e2e/specs/filters.spec.js`

Expected: `7 passed`.

If a test fails because the behaviour differs from what's written here, don't weaken the assertion. Stop and report the difference: it's either a fixture mistake or a real 0.3.4 bug worth knowing about before B1.

- [ ] **Step 3: Prove the spec catches the 0.3.0 regression, then revert**

In `includes/Integrations/BlockFilters.php`, `render_block_query()`, temporarily comment out the `set_attribute( 'data-wp-interactive', 'pikari/gutenberg-query-filter' )` call. PHP needs no rebuild.

Run: `npm run test:e2e -- tests/e2e/specs/filters.spec.js`

Expected: the result assertions fail. The URL changes, but the results don't, which is the bug #12 fixed in 0.3.0.

Revert: `git checkout includes/Integrations/BlockFilters.php`, then `git status --short includes/`, which should print nothing.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/specs/filters.spec.js
git commit -m "test: Cover category, author and search filtering end to end"
```

---

### Task 5: Sort-only loop spec

A Sort block is the only filter in this loop, which is the case 0.3.4 fixed (PR #96). The sort URL format changes in B1, so this spec asserts results only.

**Files:**

- Create: `tests/e2e/specs/sort.spec.js`

**Interfaces:**

- Consumes: `PAGES`, `titlesByTitle` from `content.js`; `resultTitles`, `markDocument`, `isSameDocument`, `visitor` from `utils.js`.
- Produces: nothing.

- [ ] **Step 1: Write the spec**

Create `tests/e2e/specs/sort.spec.js`:

```js
/**
 * Sorting a Query Loop that contains only a Sort block.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, titlesByTitle } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	resultTitles,
	visitor,
} = require('../utils');

test.use({ storageState: visitor });

test('sorts a loop that contains only a Sort block', async ({ page }) => {
	await page.goto(PAGES.sortOnly.path);
	await markDocument(page);

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Title (A-Z)' });

	await expect.poll(() => resultTitles(page)).toEqual(titlesByTitle());
	expect(await isSameDocument(page)).toBe(true);
});
```

- [ ] **Step 2: Run the spec**

Run: `npm run test:e2e -- tests/e2e/specs/sort.spec.js`

Expected: `1 passed`.

- [ ] **Step 3: Prove it catches the pre-0.3.4 bug, then revert**

In `src/blocks/sort/block.json`, temporarily set `"viewScriptModule": "pikari-gutenberg-query-filter-taxonomy-view-script-module"`, run `npm run build`, then run the spec.

Expected: it fails. The titles stay in date order, because no store loads.

Revert:

```bash
git checkout src/blocks/sort/block.json
npm run build
```

Run the spec again. Expected: `1 passed`.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/specs/sort.spec.js
git commit -m "test: Cover a Query Loop with only a Sort block end to end"
```

---

### Task 6: Injected styles survive filtering and enhanced pagination

This pins the 0.3.1 and 0.3.3 fixes (roadmap #18, #24). A script injects an id-less `<style>`, and the router must not leave it disabled after navigating.

**Files:**

- Create: `tests/e2e/specs/injected-styles.spec.js`

**Interfaces:**

- Consumes: `PAGES`, `INJECTED_COLOR` from `content.js`; `markDocument`, `isSameDocument`, `param`, `waitForParam`, `visitor` from `utils.js`.
- Produces: nothing.

- [ ] **Step 1: Write the spec**

Create `tests/e2e/specs/injected-styles.spec.js`:

```js
/**
 * Styles injected by other scripts stay enabled after router navigation.
 */
const { test, expect } = require('@playwright/test');
const { INJECTED_COLOR, PAGES } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	visitor,
	waitForParam,
} = require('../utils');

test.use({ storageState: visitor });

test('keeps injected styles after filtering', async ({ page }) => {
	const { path, queryId } = PAGES.filters;
	await page.goto(path);
	const injected = page.locator('.e2e-injected');
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
	await markDocument(page);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, `query-${queryId}-category`, 'news');

	expect(await isSameDocument(page)).toBe(true);
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
});

test('keeps injected styles after enhanced pagination and a filter change', async ({
	page,
}) => {
	const { path, queryId } = PAGES.enhanced;
	await page.goto(path);
	await markDocument(page);
	const injected = page.locator('.e2e-injected');
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);

	await page.locator('.wp-block-query-pagination-next').click();
	await waitForParam(page, `query-${queryId}-page`, '2');
	expect(await isSameDocument(page)).toBe(true);
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);

	await page.getByRole('radio', { name: 'News' }).check();
	await waitForParam(page, `query-${queryId}-category`, 'news');
	expect(param(page, `query-${queryId}-page`)).toBeNull();
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
});
```

- [ ] **Step 2: Run the spec**

Run: `npm run test:e2e -- tests/e2e/specs/injected-styles.spec.js`

Expected: `2 passed`.

- [ ] **Step 3: Prove it catches a regression, then revert**

In `src/blocks/query-filter/view.js`, temporarily make `enableInjectedStyles` do nothing: replace its body with `{}`. Then run `npm run build` and the spec.

Expected: both tests fail on the color after navigation.

Revert:

```bash
git checkout src/blocks/query-filter/view.js
npm run build
```

Run the spec again. Expected: `2 passed`.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/specs/injected-styles.spec.js
git commit -m "test: Cover injected styles after filtering and enhanced pagination end to end"
```

---

### Task 7: Document the suite, run everything, open the PR

**Files:**

- Modify: `CLAUDE.md` (plugin), under `## Development Commands` → `### Testing`.

**Interfaces:**

- Consumes: everything above.
- Produces: PR B0.

- [ ] **Step 1: Document how to run the E2E suite**

In the plugin `CLAUDE.md`, replace the `### Testing` code block under `## Development Commands` with:

````markdown
### Testing

```bash
# Run JavaScript tests
npm test

# Run PHP tests
composer test

# Run end-to-end tests (Playwright, wp-env tests instance on port 5885)
npm run build          # E2E tests use build/, so build first
npx wp-env start       # afterStart sets pretty permalinks on the tests instance
npm run test:e2e       # all specs
npm run test:e2e -- tests/e2e/specs/filters.spec.js   # one spec
```

The E2E global setup (`tests/e2e/setup/fixtures.js`) deletes and recreates all posts, pages, non-admin users and categories on the tests instance each run. Fixture data and expected results live in `tests/e2e/fixtures/content.js`. CI doesn't run E2E yet (roadmap #28).
````

- [ ] **Step 2: Run every check**

Run:

```bash
npm run build
composer test
npm test
npm run lint:js && npm run lint:css && npm run lint:php
npm run test:e2e
```

Expected:

- `OK (55 tests, …)`;
- `Tests: 30 passed`;
- no lint errors;
- `13 passed` (3 harness + 7 filters + 1 sort + 2 injected styles).

- [ ] **Step 3: Confirm there are no production changes and nothing stray**

Run: `git diff --stat origin/main -- includes src pikari-gutenberg-query-filter.php`

Expected: no output.

Run: `git status --short`

Expected: only the `CLAUDE.md` change. `artifacts/` and `.playwright-mcp/` are ignored.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: Explain how to run the end-to-end suite"
```

- [ ] **Step 5: Push and open the PR**

```bash
git push -u origin chore/query-filter-test-harness
gh pr create --base main --label skip-changelog \
  --title "chore: Add the Query Filter 1.0 spec, a Playwright harness and characterization tests" \
  --body-file <(cat <<'EOF'
B0 of the Query Filter 1.0 effort (spec §10.1). No production code changes.

## What's here
- **The 1.0 spec**, revision 2, approved: `docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md`.
- **This plan:** `docs/superpowers/plans/2026-09-16-query-filter-b0-test-harness.md`.
- **`QueryLoopHandlerTest`:** 24 characterization tests of how 0.3.4 maps URL parameters to query arguments. The known bugs B1 fixes (the forced AND relation, `post__in` removal, any `orderby`, ignored unresolved authors, sticky posts leaking into filters other than post type) are pinned and marked in docblocks.
- **Playwright harness** (`wp-scripts test-playwright`, wp-env tests instance on 5885, pretty permalinks via `lifecycleScripts`):
  - fixture content rebuilt every run;
  - 13 specs covering the harness, category/author/search filtering, pagination reset, Back, accessible names, a Sort-only loop, and injected styles after filtering and enhanced pagination.
- The Interactivity test mock matches its monorepo template again. The template gained this plugin's `readStore` in monorepo `a92292d`.

## Verification
- **Tests:** PHP 55 of 55, Jest 30 of 30, Playwright 13 of 13 locally. Lint clean.
- **Each new test proven able to fail:**
  - PHP, by 3 temporary mutations;
  - filters spec, by removing `data-wp-interactive` from the Query wrapper (the 0.3.0 bug);
  - sort spec, by restoring the pre-0.3.4 module ID;
  - injected-styles spec, by stubbing out the restore.

  All mutations were reverted.

## Not verified by CI
Playwright doesn't run on CI yet (roadmap #28). CI runs the PHP and Jest suites only.
EOF
)
```

Expected: a PR URL. Watch CI with `gh pr checks <number> --watch`. `Build`, `Code Quality` and `Test` must pass.

---

## Self-Review Notes

- **Spec coverage:**
  - §8.1: the mock (the template was updated rather than using `skip-sync`, the monorepo session's call) and CI facts → Task 1, plus the Task 7 PR body.
  - §8.2 item 1: characterization tests → Task 2.
  - §8.4: configuration, environment and fixtures → Task 3. The spec's archive, search-template, button-only Search and sticky fixtures are **deferred to B1/B2**, where the behaviour they exercise exists. B0 only pins today's behaviour.
  - §10.1: B0 row, `skip-changelog` → Task 7.
- **Deliberately not in B0:**
  - visual screenshot baselines (B3, when the markup changes);
  - no-JS specs (B3);
  - race and delayed-response specs (B3);
  - inherited-loop specs (B2).
- **Names used across tasks:**
  - `PAGES.filters` / `enhanced` / `sortOnly` with `path` and `queryId`;
  - `newestTitles`, `titlesByTitle`, `INJECTED_COLOR`;
  - `resultTitles`, `markDocument`, `isSameDocument`, `param`, `waitForParam`, `visitor`.
