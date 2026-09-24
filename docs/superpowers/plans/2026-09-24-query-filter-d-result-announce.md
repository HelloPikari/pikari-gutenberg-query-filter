# Query Filter 1.0 — D: Result Announcements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a filter, sort or search changes a loop's results, a screen reader hears the new total ("12 results found", "No results found") instead of core's generic "Page loaded.".

**Architecture:**

- **PHP, capturing the count.** `QueryLoopHandler` tags every custom loop's query args with a private query var carrying the loop's form id. A `found_posts` filter records each tagged query's total against that id, in the new `Query\ResultCount`. For inherited loops, the count is the main query's `found_posts`.
- **PHP, publishing it.** `BlockFilters::inject_loop_form()` runs after the loop has rendered, so the count is known by then. It passes the count to `LoopForm::render()`, which stamps `data-query-found-posts` and a translated `data-query-results-message` on the form.
- **JavaScript.** `actions.navigate` turns off the router's own announcement and re-creates its 400 ms "loading" cue. Once the new page renders, it reads the message from the loop's **new** form and speaks it with `@wordpress/a11y`.

**Tech Stack:** PHP 8.4, PHPUnit 9.6 with Brain\Monkey and Mockery; Jest with `@wordpress/scripts`; Playwright; WordPress 7.1 Interactivity API, `interactivity-router` and the `@wordpress/a11y` script module.

**Spec:** `docs/superpowers/specs/2026-09-23-query-filter-1.0-c-d-design.md`, approved 2026-09-23. This plan implements §3, the D parts of §5, and the D row of §7.

## Global Constraints

- **Branch:** `feature/result-announce`, cut from `main` at `e57a04a`, where C is merged and the plugin version is 1.0.0, unpublished. This plan is committed on it.
- **Label:** `feature`, from the autolabeler. D only adds to the contract; it breaks nothing.
- **Nothing is published** (todo #529).
- **Code style:**
  - PHP follows WordPress Coding Standards with **4 spaces, not tabs**. `phpcs.xml` skips `tests/`, so check each new or edited PHP test with `grep -c $'\t' <file>`, which must print 0.
  - JavaScript is formatted by ESLint, with tabs. `npm run lint:js` only covers `src/`, so run `npx wp-scripts lint-js <file>` on each new or edited file under `tests/unit/`.
  - Reformatting plan code to satisfy lint is expected; changing behaviour isn't.
- **Text domain** is `pikari-gutenberg-query-filter`. Every user-facing string is translated.
- **Commits:** `type: Brief description`. No `Co-Authored-By` or "Generated with" trailers in commit messages. Never `--no-verify`.
- **TDD, with a mutation step:**
  - Watch every new test fail for the right reason.
  - Once it passes, mutate the production line it covers and confirm the test fails, then restore.
  - Each task names its mutations.
- **Environment:** this plugin's wp-env only, on ports 5884 (development) and 5885 (tests). Never touch other Docker containers, and never run `wp-env destroy` or `wp-env clean`.
- **Suites at the start:** PHP **237**, Jest **102**, Playwright **55**, all green. They must be green at the end of every task.

## Push back on this plan

It's argued from the spec and from reading WordPress 7.1's source, not from a run. If a task's code doesn't do what its prose says, say so and rule on it; don't transcribe it. In sub-project C, the final review found a gap every task review had missed, at the seam between the editor preview and the PHP it mirrors. D's seams are the PHP count and the JS reader, and the old form against the new form after a navigation.

## Facts established while writing this plan

Verified against WP 7.1.2 in `~/.wp-env/*/WordPress/` and a live wp-env. Do not re-derive them.

- **The router's announcement can be turned off.** `interactivity-router` `actions.navigate( href, options )` takes `screenReaderAnnouncement` (default `true`). When it's true, the router speaks "loading" after 400 ms if still in flight, and "loaded" after rendering. It reads both texts from the JSON in `<script id="wp-script-module-data-@wordpress/interactivity-router">` (`i18n.loading`, `i18n.loaded`).
- **The router discards a superseded response.** If a newer `navigate()` started, the older call returns without rendering and its promise still resolves. So after `yield actions.navigate( url )`, the page may not be `url`'s. `inFlightUrl === url` is the check.
- **`@wordpress/a11y` is a script module in WP 7.1.** It lives at `wp-includes/js/dist/script-modules/a11y/`, and exports `speak( message, ariaLive )` and `setup()`. `speak()` clears the regions, then writes to `#a11y-speak-polite` unless `ariaLive === 'assertive'`. A repeated identical message gets a trailing space appended, so it's re-announced.
- **The live regions are printed in `wp_footer`** by `WP_Script_Modules::print_a11y_script_module_html()` whenever `@wordpress/a11y` is in the queue or the import map. The router already puts it in the import map as a dynamic dependency.
- **The build supports it.** `@wordpress/dependency-extraction-webpack-plugin` treats `@wordpress/a11y` as a dynamic module external (`import @wordpress/a11y`), exactly like `@wordpress/interactivity-router`.
- **The `found_posts` filter sees private query vars.** Probed in the live wp-env: `new WP_Query( array( …, 'pikari_probe' => 'form-3' ) )` with a `found_posts` filter reading `$query->get( 'pikari_probe' )` printed `form-3`. Core's post template builds its loop query from the args `query_loop_block_query_vars` returns, so the var arrives intact.
- **Core queries a custom loop more than once per page** (post template, pagination, query-no-results, query-total), each through `query_loop_block_query_vars`. Every run reports the same total, so the last write wins.
- **`@wordpress/a11y` is not in `node_modules`.** In Jest, mock it virtually inside the test file: `jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ), { virtual: true } )`. Don't edit `jest.config.js`, which is template-synced.
- **Existing tests assert exact router options.** In `tests/unit/blocks/query-filter/view.test.js`, many tests expect `navigate` to be called with `{}` or `{ replace: true }`. Task 3 changes every call to include `screenReaderAnnouncement: false`, so those expectations change with it. That's deliberate, not collateral damage.
- **Existing tests assert exact query args.** In `tests/php/QueryLoopHandlerTest.php`, some tests `assertSame` the full args array, for example `test_query_args_are_unchanged_without_parameters` and `test_parameters_for_another_query_id_are_ignored`. Task 1 adds a key to every custom loop's args, so those expectations gain it.

## File Structure

| File                                                   | Responsibility                                                                                            |
| ------------------------------------------------------ | --------------------------------------------------------------------------------------------------------- |
| `includes/Query/ResultCount.php` (create)              | Records each loop's total by form id, reads it back, builds the announcement text. Pure except `__`/`_n`. |
| `includes/Core/QueryLoopHandler.php` (modify)          | Tags custom loops' args with the form id; registers the `found_posts` recorder.                           |
| `includes/Url/LoopForm.php` (modify)                   | `render()` takes an optional count and stamps the two attributes.                                         |
| `includes/Integrations/BlockFilters.php` (modify)      | Looks up the count (custom: `ResultCount`; inherited: main query) and hands it to `render()`.             |
| `src/blocks/query-filter/view.js` (modify)             | `navigate` gets the form id, silences the router, speaks loading and the new message.                     |
| `tests/php/ResultCountTest.php` (create)               | Recording, lookup, reset, message text.                                                                   |
| `tests/php/QueryLoopHandlerTest.php` (modify)          | The var on custom loops, not inherited ones; the filter registration.                                     |
| `tests/php/LoopFormTest.php` (modify)                  | The attributes with and without a count.                                                                  |
| `tests/php/BlockFiltersTest.php` (modify)              | The count reaches the injected form, for custom and inherited loops, and is absent when unknown.          |
| `tests/unit/blocks/query-filter/view.test.js` (modify) | Announcements; updated router-option expectations.                                                        |
| `tests/e2e/specs/announcements.spec.js` (create)       | Real browser: the polite region carries the count after filtering, including zero and an inherited loop.  |

---

### Task 1: Record each loop's total

**Files:**

- Create: `includes/Query/ResultCount.php`
- Modify: `includes/Core/QueryLoopHandler.php`
- Create: `tests/php/ResultCountTest.php`
- Modify: `tests/php/QueryLoopHandlerTest.php`

**Interfaces:**

- Produces:
  - `Query\ResultCount::QUERY_VAR`, which is `'pikari_gutenberg_query_filter_loop'`;
  - `ResultCount::record( $found_posts, $query )`, which returns `$found_posts` unchanged;
  - `ResultCount::for_form( string $form_id ): ?int`;
  - `ResultCount::message( int $count ): string`;
  - `ResultCount::reset(): void`.
- Produces: `QueryLoopHandler::modify_query()` sets `$query_args[ ResultCount::QUERY_VAR ]` to the loop's form id on custom loops.

- [ ] **Step 1: Write the failing `ResultCount` tests**

Create `tests/php/ResultCountTest.php`:

```php
<?php
/**
 * Tests for ResultCount: each Query Loop's total, recorded by form id.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Query\ResultCount;
use Pikari\Tests\TestCase;

class ResultCountTest extends TestCase {

    protected function tearDown(): void {
        ResultCount::reset();
        parent::tearDown();
    }

    /**
     * A WP_Query stand-in whose get() answers for the loop var only.
     *
     * @param mixed $form_id Value of the loop var, or null when absent.
     * @return \WP_Query
     */
    private function query( $form_id ): \WP_Query {
        $query = Mockery::mock( 'WP_Query' );
        $query->shouldReceive( 'get' )
            ->with( ResultCount::QUERY_VAR )
            ->andReturn( $form_id ?? '' );

        return $query;
    }

    public function test_record_stores_the_total_against_the_form_id(): void {
        ResultCount::record( 12, $this->query( 'pikari-gutenberg-query-filter-form-3' ) );

        $this->assertSame( 12, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_record_returns_the_total_unchanged(): void {
        $this->assertSame( 12, ResultCount::record( 12, $this->query( 'pikari-gutenberg-query-filter-form-3' ) ) );
    }

    public function test_record_ignores_a_query_without_the_loop_var(): void {
        ResultCount::record( 99, $this->query( null ) );

        $this->assertNull( ResultCount::for_form( '' ) );
    }

    public function test_record_ignores_something_that_is_not_a_query(): void {
        $this->assertSame( 5, ResultCount::record( 5, null ) );
    }

    public function test_the_last_run_of_a_loop_wins(): void {
        ResultCount::record( 12, $this->query( 'pikari-gutenberg-query-filter-form-3' ) );
        ResultCount::record( 7, $this->query( 'pikari-gutenberg-query-filter-form-3' ) );

        $this->assertSame( 7, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_two_loops_keep_separate_totals(): void {
        ResultCount::record( 12, $this->query( 'pikari-gutenberg-query-filter-form-3' ) );
        ResultCount::record( 4, $this->query( 'pikari-gutenberg-query-filter-form-4' ) );

        $this->assertSame( 12, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
        $this->assertSame( 4, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-4' ) );
    }

    public function test_for_form_is_null_for_an_unrecorded_loop(): void {
        $this->assertNull( ResultCount::for_form( 'pikari-gutenberg-query-filter-form-9' ) );
    }

    public function test_reset_forgets_every_total(): void {
        ResultCount::record( 12, $this->query( 'pikari-gutenberg-query-filter-form-3' ) );
        ResultCount::reset();

        $this->assertNull( ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_message_for_no_results(): void {
        Functions\stubTranslationFunctions();

        $this->assertSame( 'No results found', ResultCount::message( 0 ) );
    }

    public function test_message_for_one_result_is_singular(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->alias( 'strval' );

        $this->assertSame( '1 result found', ResultCount::message( 1 ) );
    }

    public function test_message_for_several_results_is_plural_and_localized(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->justReturn( '1,234' );

        $this->assertSame( '1,234 results found', ResultCount::message( 1234 ) );
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit tests/php/ResultCountTest.php`
Expected: errors, "Class … ResultCount not found".

- [ ] **Step 3: Implement `ResultCount`**

Create `includes/Query/ResultCount.php`:

```php
<?php
/**
 * Each Query Loop's total result count, for the result announcement.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Query;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Records custom loops' totals as core runs their queries.
 *
 * QueryLoopHandler tags each custom loop's query args with QUERY_VAR, set to
 * the loop's form id. Core builds the loop's WP_Query from those args, so the
 * found_posts filter can tell which loop a total belongs to. The loop form is
 * injected after the loop renders, by which time the total is known (spec C/D §3.2).
 */
class ResultCount {

    /**
     * Private query var carrying a custom loop's form id. Internal: not part of
     * the public contract.
     */
    public const QUERY_VAR = 'pikari_gutenberg_query_filter_loop';

    /**
     * Totals by form id, for this request.
     *
     * @var array<string, int>
     */
    private static array $counts = array();

    /**
     * Record a tagged query's total. Hooked to found_posts.
     *
     * Core runs a loop's query several times per page, every run reporting the
     * same total, so the last write wins.
     *
     * @param int|string $found_posts Total, as core computed it.
     * @param mixed      $query       The WP_Query.
     * @return int|string The total, unchanged.
     */
    public static function record( $found_posts, $query ) {
        if ( $query instanceof \WP_Query ) {
            $form_id = $query->get( self::QUERY_VAR );

            if ( is_string( $form_id ) && '' !== $form_id ) {
                self::$counts[ $form_id ] = (int) $found_posts;
            }
        }

        return $found_posts;
    }

    /**
     * A custom loop's total, if one was recorded this request.
     *
     * @param string $form_id The loop's form id.
     * @return int|null Total, or null when unknown.
     */
    public static function for_form( string $form_id ): ?int {
        return self::$counts[ $form_id ] ?? null;
    }

    /**
     * The sentence a screen reader hears.
     *
     * @param int $count Total results.
     * @return string Translated message.
     */
    public static function message( int $count ): string {
        if ( 0 === $count ) {
            return __( 'No results found', 'pikari-gutenberg-query-filter' );
        }

        return sprintf(
            /* translators: %s: number of results. */
            _n( '%s result found', '%s results found', $count, 'pikari-gutenberg-query-filter' ),
            number_format_i18n( $count )
        );
    }

    /**
     * Forget every total. For tests.
     */
    public static function reset(): void {
        self::$counts = array();
    }
}
```

Run `composer dump-autoload`, then: `vendor/bin/phpunit tests/php/ResultCountTest.php`, which should pass.

Mutations, restoring after each:

- Remove the `is_string( $form_id ) && '' !== $form_id` guard. `test_record_ignores_a_query_without_the_loop_var` must fail.
- Change `0 === $count` to `1 === $count`. Both message tests must fail.

- [ ] **Step 4: Write the failing `QueryLoopHandler` tests**

In `tests/php/QueryLoopHandlerTest.php`:

- add `use Pikari\GutenbergQueryFilter\Query\ResultCount;`;
- add, next to the existing registration test:

```php
    public function test_constructor_records_loop_totals_on_found_posts(): void {
        \Brain\Monkey\Filters\expectAdded( 'found_posts' )
            ->once()
            ->with( array( ResultCount::class, 'record' ), 10, 2 );

        new QueryLoopHandler();
        $this->addToAssertionCount( 1 );
    }

    public function test_custom_loop_args_carry_the_loop_form_id(): void {
        $result = $this->filter( array(), array( 'post_type' => 'post' ) );

        $this->assertSame( 'pikari-gutenberg-query-filter-form-3', $result[ ResultCount::QUERY_VAR ] );
    }
```

- extend `test_inherited_loop_returns_the_query_args_unchanged`, which already asserts the args are unchanged, with `$this->assertArrayNotHasKey( ResultCount::QUERY_VAR, $result );`.

Then update every existing test that `assertSame`s a full args array for a **custom** loop so that its expected array includes `ResultCount::QUERY_VAR => 'pikari-gutenberg-query-filter-form-3'`. For the `queryId`-less test, the expected value is `…-form-0`. Run the file first to see which tests fail on the new key, and change only those expectations. Don't weaken any assertion to `assertArraySubset` or anything like it.

- [ ] **Step 5: Run to verify they fail**

Run: `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php`
Expected: the two new tests fail. The updated expectations fail too, because the key isn't there yet.

- [ ] **Step 6: Implement in `QueryLoopHandler`**

In `includes/Core/QueryLoopHandler.php`:

- add `use Pikari\GutenbergQueryFilter\Query\ResultCount;`;
- in `register_hooks()`, add after the existing filter:

```php
        // Record each tagged loop's total for the result announcement.
        add_filter( 'found_posts', array( ResultCount::class, 'record' ), 10, 2 );
```

- in `modify_query()`, replace the final `return` with:

```php
        $query_args = QueryArgs::apply( $query_args, FilterState::for_loop( $params ) );

        // Tag the query, so ResultCount can tell which loop a total is for.
        $query_args[ ResultCount::QUERY_VAR ] = $params->form_id();

        return $query_args;
```

- [ ] **Step 7: Run, mutate, run everything**

Run: `vendor/bin/phpunit tests/php/QueryLoopHandlerTest.php`, which should pass.

Mutation: delete the tagging line. `test_custom_loop_args_carry_the_loop_form_id` must fail. Restore.

Run `composer test`, which should be all green. The expected count is 237 + 11 (ResultCountTest) + 2 = **250**. If your count differs, explain it in the report.

- [ ] **Step 8: Lint and commit**

```bash
npm run lint:php && grep -c $'\t' tests/php/ResultCountTest.php tests/php/QueryLoopHandlerTest.php   # each must print 0
git add includes/Query/ResultCount.php includes/Core/QueryLoopHandler.php tests/php/ResultCountTest.php tests/php/QueryLoopHandlerTest.php
git commit -m "feat: Record each Query Loop's total result count"
```

---

### Task 2: Stamp the count on the loop form

**Files:**

- Modify: `includes/Url/LoopForm.php` (`render()`)
- Modify: `includes/Integrations/BlockFilters.php` (`inject_loop_form()`)
- Modify: `tests/php/LoopFormTest.php`, `tests/php/BlockFiltersTest.php`

**Interfaces:**

- Consumes: `ResultCount::for_form()`, `ResultCount::message()` (Task 1).
- Produces:

  - `LoopForm::render( QueryParams $params, string $action, array $hidden_inputs, string $pagination_base, ?int $found_posts = null ): string`.
  - The markup contract: `data-query-found-posts="{int}"` and `data-query-results-message="{text}"` on `form.wp-block-pikari-gutenberg-query-filter__form`, both present or both absent.

- [ ] **Step 1: Write the failing `LoopForm` tests**

Add to `tests/php/LoopFormTest.php`:

```php
    public function test_render_stamps_the_count_and_its_message(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->alias( 'strval' );

        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page', 12 );

        $this->assertStringContainsString( 'data-query-found-posts="12"', $html );
        $this->assertStringContainsString( 'data-query-results-message="12 results found"', $html );
    }

    public function test_render_stamps_a_zero_count(): void {
        Functions\stubTranslationFunctions();

        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page', 0 );

        $this->assertStringContainsString( 'data-query-found-posts="0"', $html );
        $this->assertStringContainsString( 'data-query-results-message="No results found"', $html );
    }

    public function test_render_omits_both_attributes_without_a_count(): void {
        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' );

        $this->assertStringNotContainsString( 'data-query-found-posts', $html );
        $this->assertStringNotContainsString( 'data-query-results-message', $html );
    }
```

Add `use Brain\Monkey\Functions;` if the file doesn't import it. Check how the file's setUp stubs the escape functions, and match it.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit tests/php/LoopFormTest.php`
Expected: the first two fail, because the attributes are missing. The third passes already; it guards Step 3 against always stamping.

- [ ] **Step 3: Implement in `LoopForm::render()`**

In `includes/Url/LoopForm.php`:

- add `use Pikari\GutenbergQueryFilter\Query\ResultCount;`;
- add `@param int|null $found_posts Total results, when known; stamped for the result announcement.` to the docblock;
- change the signature to `public static function render( QueryParams $params, string $action, array $hidden_inputs, string $pagination_base, ?int $found_posts = null ): string {`;
- before the `return sprintf(`, add:

```php
        // Read by view.js after a navigation, from the new page's form (spec C/D §3.3).
        $count = '';
        if ( null !== $found_posts ) {
            $count = sprintf(
                ' data-query-found-posts="%1$d" data-query-results-message="%2$s"',
                $found_posts,
                esc_attr( ResultCount::message( $found_posts ) )
            );
        }
```

- in the format string, change `data-query-pagination-base="%5$s">%6$s</form>` to `data-query-pagination-base="%5$s"%7$s>%6$s</form>`, and append `$count` as the seventh argument.

Run: `vendor/bin/phpunit tests/php/LoopFormTest.php`, which should pass.

Mutation: change `null !== $found_posts` to `! empty( $found_posts )`. `test_render_stamps_a_zero_count` must fail. Restore.

- [ ] **Step 4: Write the failing `BlockFilters` tests**

In `tests/php/BlockFiltersTest.php`:

- add `use Pikari\GutenbergQueryFilter\Query\ResultCount;`;
- add `ResultCount::reset();` and `unset( $GLOBALS['wp_query'] );` to `tearDown()`;
- add `Functions\when( 'number_format_i18n' )->alias( 'strval' );` to `setUp()`. Translation functions are already stubbed there.
- add:

```php
    public function test_render_block_query_stamps_a_custom_loops_recorded_count(): void {
        $query = Mockery::mock( 'WP_Query' );
        $query->shouldReceive( 'get' )->with( ResultCount::QUERY_VAR )->andReturn( 'pikari-gutenberg-query-filter-form-3' );
        ResultCount::record( 12, $query );

        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $this->assertStringContainsString( 'data-query-found-posts="12"', $html );
        $this->assertStringContainsString( 'data-query-results-message="12 results found"', $html );
    }

    public function test_render_block_query_stamps_nothing_when_no_count_was_recorded(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $this->assertStringContainsString( 'wp-block-pikari-gutenberg-query-filter__form', $html );
        $this->assertStringNotContainsString( 'data-query-found-posts', $html );
    }

    public function test_render_block_query_does_not_use_another_loops_count(): void {
        $query = Mockery::mock( 'WP_Query' );
        $query->shouldReceive( 'get' )->with( ResultCount::QUERY_VAR )->andReturn( 'pikari-gutenberg-query-filter-form-4' );
        ResultCount::record( 12, $query );

        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $this->assertStringNotContainsString( 'data-query-found-posts', $html );
    }

    public function test_render_block_query_stamps_an_inherited_loops_main_query_count(): void {
        $main              = Mockery::mock( 'WP_Query' );
        $main->found_posts = 5;
        $GLOBALS['wp_query'] = $main;

        $html = $this->render_query(
            '<select name="query-category" form="pikari-gutenberg-query-filter-form-inherit"></select>',
            array( 'query' => array( 'inherit' => true ) )
        );

        $this->assertStringContainsString( 'data-query-found-posts="5"', $html );
    }
```

Check `render_query()`'s signature and the constant `CUSTOM_LOOP_ATTRS`, and make the calls fit them. The first argument is the loop's inner HTML; the second is the Query block attributes. Look at how `test_render_block_query_uses_the_inherit_form_for_an_inherited_loop` builds an inherited loop, and do the same.

- [ ] **Step 5: Run to verify they fail**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php`
Expected: the custom-count and inherited-count tests fail. The two "nothing stamped" tests pass already, which is fine: they guard Step 6.

- [ ] **Step 6: Implement in `BlockFilters::inject_loop_form()`**

In `includes/Integrations/BlockFilters.php`:

- add `use Pikari\GutenbergQueryFilter\Query\ResultCount;`;
- in `inject_loop_form()`, pass a fifth argument to `LoopForm::render(`: `self::found_posts( $params, $form_id )`;
- add a private static method after `form_targets()`:

```php
    /**
     * The loop's total result count, if known.
     *
     * A custom loop's total is recorded by ResultCount as core runs its query.
     * An inherited loop shows the main query, already filtered by
     * MainQueryFilter by the time the template renders.
     *
     * @param QueryParams $params  The loop's parameters.
     * @param string      $form_id The loop's form id.
     * @return int|null Total, or null when unknown.
     */
    private static function found_posts( QueryParams $params, string $form_id ): ?int {
        if ( $params->is_inherit() ) {
            global $wp_query;

            return ( $wp_query instanceof \WP_Query ) ? (int) $wp_query->found_posts : null;
        }

        return ResultCount::for_form( $form_id );
    }
```

Add a `use` for `QueryParams` if the file doesn't import it. It does already use `QueryParams::from_query_block()`, so check.

- [ ] **Step 7: Run, mutate, run everything**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php`, which should pass. Then, restoring after each:

- Mutation 1: return `ResultCount::for_form( $form_id )` unconditionally. The inherited test must fail.
- Mutation 2: return `null` for custom loops. The custom-count test must fail.

Run `composer test`, which should be all green, at **257** (250 + 3 + 4). Explain any difference.

- [ ] **Step 8: Lint and commit**

```bash
npm run lint:php && grep -c $'\t' tests/php/LoopFormTest.php tests/php/BlockFiltersTest.php   # each must print 0
git add includes/Url/LoopForm.php includes/Integrations/BlockFilters.php tests/php/LoopFormTest.php tests/php/BlockFiltersTest.php
git commit -m "feat: Stamp each loop form with its result count"
```

---

### Task 3: Announce the result count

**Files:**

- Modify: `src/blocks/query-filter/view.js`
- Modify: `tests/unit/blocks/query-filter/view.test.js`

**Interfaces:**

- Consumes: the `data-query-results-message` form attribute (Task 2).
- Produces: `actions.navigate( url, replace, formId )`, where the third argument is new. Everything that calls `run()` passes the form's id.

- [ ] **Step 1: Write the failing tests**

In `tests/unit/blocks/query-filter/view.test.js`:

1. At the top of the file, below the header comment, add a virtual mock. `@wordpress/a11y` isn't installed, and `jest.config.js` is template-synced, so it isn't touched:

   ```js
   jest.mock('@wordpress/a11y', () => ({ speak: jest.fn() }), {
   	virtual: true,
   });
   ```

2. Add `let speak;` to the module-level `let`s. In the top-level `beforeEach`, after the router `require`, add `( { speak } = require( '@wordpress/a11y' ) );`.

3. Update every existing expectation of `navigate` options. `{}` becomes `{ screenReaderAnnouncement: false }`, and `{ replace: true }` becomes `{ replace: true, screenReaderAnnouncement: false }`. Where a test uses `expect.objectContaining`, leave it. Don't change any URL.

4. Add a new `describe` inside `form-driven navigation`, so it shares `addForm()` and the fake timers:

```js
describe('result announcements', () => {
	/**
	 * Make the router "render" a new page: replace the loop form, as the
	 * router's region swap does, carrying the new page's message.
	 *
	 * @param {string|null} message New form's message, or null for none.
	 */
	const rendersPageWith = (message) => {
		navigate.mockImplementationOnce(() => {
			const next = form.cloneNode(true);
			if (null === message) {
				delete next.dataset.queryResultsMessage;
			} else {
				next.dataset.queryResultsMessage = message;
			}
			form.replaceWith(next);
			return Promise.resolve();
		});
	};

	beforeEach(() => {
		form.dataset.queryResultsMessage = '9 results found';
	});

	it('turns off the router announcement', async () => {
		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);

		expect(navigate).toHaveBeenCalledWith(
			expect.any(String),
			expect.objectContaining({
				screenReaderAnnouncement: false,
			})
		);
	});

	it('speaks the new page form message, not the old one', async () => {
		rendersPageWith('3 results found');
		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);
		await flush();

		expect(speak).toHaveBeenCalledWith('3 results found');
		expect(speak).not.toHaveBeenCalledWith('9 results found');
	});

	it('announces after a submit too', async () => {
		rendersPageWith('No results found');
		actions.submit({
			type: 'submit',
			target: form,
			preventDefault: () => {},
		});
		await flush();

		expect(speak).toHaveBeenCalledWith('No results found');
	});

	it('stays silent when the new form has no message', async () => {
		rendersPageWith(null);
		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);
		await flush();

		expect(speak).not.toHaveBeenCalled();
	});

	it('stays silent when a newer navigation took over', async () => {
		let finishFirst;
		navigate.mockImplementationOnce(
			() =>
				new Promise((resolve) => {
					finishFirst = resolve;
				})
		);
		rendersPageWith('1 result found');

		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);

		// A second change while the first is in flight navigates at once.
		select.value = 'events';
		fire(select, 'change');
		await flush();
		expect(speak).toHaveBeenCalledWith('1 result found');

		speak.mockClear();
		finishFirst();
		await flush();

		expect(speak).not.toHaveBeenCalled();
	});

	it('speaks the router loading text only once 400ms have passed in flight', async () => {
		document.body.insertAdjacentHTML(
			'beforeend',
			'<script type="application/json" id="wp-script-module-data-@wordpress/interactivity-router">{"i18n":{"loading":"Loading page…","loaded":"Page Loaded."}}</script>'
		);
		let finish;
		navigate.mockImplementationOnce(
			() =>
				new Promise((resolve) => {
					finish = resolve;
				})
		);

		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);

		await jest.advanceTimersByTimeAsync(399);
		expect(speak).not.toHaveBeenCalledWith('Loading page…');

		await jest.advanceTimersByTimeAsync(1);
		expect(speak).toHaveBeenCalledWith('Loading page…');

		finish();
		await flush();
	});

	it('does not speak the loading text for a fast navigation', async () => {
		document.body.insertAdjacentHTML(
			'beforeend',
			'<script type="application/json" id="wp-script-module-data-@wordpress/interactivity-router">{"i18n":{"loading":"Loading page…"}}</script>'
		);
		rendersPageWith('3 results found');

		select.value = 'news';
		fire(select, 'change');
		await jest.advanceTimersByTimeAsync(250);
		await flush();
		await jest.advanceTimersByTimeAsync(1000);

		expect(speak).not.toHaveBeenCalledWith('Loading page…');
	});
});
```

`announce()` loads `@wordpress/a11y` with a dynamic `import()`, so `speak` is called a microtask or two after the navigation resolves. Where an assertion on `speak` runs straight after advancing timers, `await flush()` first. If one `flush()` isn't enough, loop it, and say so in the report rather than adding real delays. `flush()` already exists in the file. If `addForm()` rebuilds `document.body.innerHTML` in each `beforeEach`, as it does today, the injected `<script>` doesn't leak between tests. `view.js` caches the loading text per module load, and the top-level `beforeEach` calls `jest.resetModules()`, so each test reads it fresh. Verify both.

- [ ] **Step 2: Run to verify they fail**

Run: `npm test -- --testPathPattern=view`
Expected: the updated option expectations and the new tests fail. The two "silent" tests may pass trivially, because nothing speaks yet. That's fine; the mutations in Step 4 prove them.

- [ ] **Step 3: Implement in `view.js`**

In `src/blocks/query-filter/view.js`:

1. Below `SEARCH_DELAY`, add:

   ```js
   // The router's own "loading" cue fires after 400ms; this matches it, since
   // the router's announcements are turned off for this plugin's navigations.
   const LOADING_DELAY = 400;

   // The router's localized loading text, read once per page.
   let loadingText;

   /**
    * The router's localized "loading" text, as the router itself reads it.
    *
    * @return {string|null} Text, or null when the page doesn't carry it.
    */
   const routerLoadingText = () => {
   	if (undefined === loadingText) {
   		try {
   			loadingText =
   				JSON.parse(
   					document.getElementById(
   						'wp-script-module-data-@wordpress/interactivity-router'
   					)?.textContent ?? ''
   				)?.i18n?.loading ?? null;
   		} catch {
   			loadingText = null;
   		}
   	}

   	return loadingText;
   };

   /**
    * Speak a message politely, through core's shared live region.
    *
    * @param {string|null|undefined} message Message; nothing is spoken when empty.
    */
   const announce = (message) => {
   	if (!message) {
   		return;
   	}

   	import('@wordpress/a11y').then(
   		({ speak }) => speak(message),
   		// As the router does, ignore a module that fails to load.
   		() => {}
   	);
   };
   ```

2. `run( url, isSearch )` becomes `run( url, isSearch, formId )`, and it calls `.actions.navigate( url, replace, formId )`. Update the JSDoc.

3. In `actions.change`, pass `form.id`, to both `run( url, isSearch )` calls and inside the timer callback. In `actions.submit`, pass `form.id`.

4. Replace `*navigate( url, replace )` with:

```js
		/**
		 * Navigate, keeping script-injected styles alive across the swap, and
		 * announce the new result count (spec C/D §3.3).
		 *
		 * @param {string}  url     URL to navigate to.
		 * @param {boolean} replace Whether to replace the history entry.
		 * @param {string}  formId  Id of the loop form that triggered it.
		 */
		*navigate( url, replace, formId ) {
			captureInjectedStyles();
			inFlightUrl = url;

			const loading = setTimeout( () => {
				if ( inFlightUrl === url ) {
					announce( routerLoadingText() );
				}
			}, LOADING_DELAY );

			try {
				const { actions } = yield import(
					'@wordpress/interactivity-router'
				);
				yield actions.navigate( url, {
					...( replace ? { replace: true } : {} ),
					screenReaderAnnouncement: false,
				} );
				enableInjectedStyles();

				// The router returns without rendering when a newer navigation
				// has taken over, so only the current one may announce. The
				// form is looked up again: the swap replaced it.
				if ( inFlightUrl === url ) {
					announce(
						document.getElementById( formId )?.dataset
							.queryResultsMessage
					);
				}
			} finally {
				clearTimeout( loading );

				// Only if no newer navigation has taken over.
				if ( inFlightUrl === url ) {
					inFlightUrl = null;
				}
			}
		},
```

- [ ] **Step 4: Run, mutate, run everything**

Run: `npm test -- --testPathPattern=view`, which should pass. Then, restoring after each:

- Mutation 1: delete the `announce( document.getElementById… )` call. "speaks the new page form message" and "announces after a submit" must fail.
- Mutation 2: remove the `if ( inFlightUrl === url )` guard around it. "stays silent when a newer navigation took over" must fail.
- Mutation 3: capture the form **before** `yield actions.navigate`. Move `const next = document.getElementById( formId );` above the router call and announce `next?.dataset…` afterwards. "speaks the new page form message, not the old one" must fail.
- Mutation 4: remove `clearTimeout( loading )`. "does not speak the loading text for a fast navigation" must fail.

Run: `npm run lint:js && npx wp-scripts lint-js tests/unit/blocks/query-filter/view.test.js && npm test && npm run build`

Check `build/blocks/query-filter/view.asset.php`: its module dependencies must list `@wordpress/a11y` as `dynamic`, next to `@wordpress/interactivity-router`. Quote the line in your report.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/query-filter/view.js tests/unit/blocks/query-filter/view.test.js
git commit -m "feat: Announce the result count after filtering"
```

---

### Task 4: Prove it in a real browser

**Files:**

- Create: `tests/e2e/specs/announcements.spec.js`
- Modify: `tests/e2e/fixtures/content.js`, only if a needed page or count isn't already there.

**Interfaces:**

- Consumes: everything above, through the built plugin.

- [ ] **Step 1: Read the harness first**

Read these:

- `tests/e2e/specs/filters.spec.js` and `tests/e2e/specs/inherited-loops.spec.js`, for the page paths (`PAGES`, `TEMPLATES`), the `visitor` storage state, and the helpers `waitForParam` and `resultTitles`;
- `tests/e2e/fixtures/content.js`, for `POSTS`, `TITLES` and how posts map to categories, authors and titles.

Every expected count in this spec **must be derived from the fixture data** with a one-line comment, for example `POSTS.filter( isNews ).length`. Never read it back from `data-query-found-posts`, which would be circular. Before relying on a derived count, confirm it by reading what the loop's query includes: its post type, its sticky handling, and posts per page. `found_posts` is the total, not the page size.

- [ ] **Step 2: Write the spec**

Create `tests/e2e/specs/announcements.spec.js` with these tests:

1. **The custom loop announces a count.** On `PAGES.filters`, do a search that has exactly one match (`'Mango'`, per `filters.spec.js`). Poll `#a11y-speak-polite` until it reads `1 result found`. Use `expect.poll( () => page.locator( '#a11y-speak-polite' ).textContent() )`, and trim the result: `speak()` may append a space.
2. **Zero results.** Search for a string no fixture contains. The region reads `No results found`.
3. **A plural count.** Check one category, and expect `${ n } results found`, where `n` is derived from `POSTS`.
4. **The router's own text is not spoken.** After test 1's change, the region doesn't contain `Page Loaded` or `Page loaded`.
5. **An inherited loop announces.** On the category archive from `TEMPLATES.category`, change one of its filters. Pick one whose result count you can derive, and expect that count.
6. **No-JS pages carry the attributes.** With `javaScriptEnabled: false` (see `no-js.spec.js`), load `PAGES.filters` with the one-match search in the URL. Assert the loop form has `data-query-found-posts="1"` and `data-query-results-message="1 result found"`. This proves the server count on its own, with no announcement involved.

Use the existing helpers wherever they fit. Don't use `waitForTimeout`; poll instead.

- [ ] **Step 3: Run it**

```bash
npm run build
PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm run test:e2e -- tests/e2e/specs/announcements.spec.js
```

Expected: all pass.

Mutation: in `build/` only, no source change, or by temporarily editing `view.js` and rebuilding: remove the `announce(` call after the router navigation. Tests 1, 2, 3 and 5 must fail. Restore and rebuild.

Then run the whole suite: `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm run test:e2e`, which should pass at 55 + your new tests.

- [ ] **Step 4: Lint and commit**

`tests/e2e/**` isn't linted by any script (roadmap #28). Match `filters.spec.js`'s formatting by eye.

```bash
git add tests/e2e/specs/announcements.spec.js tests/e2e/fixtures/content.js
git commit -m "test: Cover the result announcement in the browser"
```

(Drop `content.js` from the `git add` if you didn't change it.)

---

### Task 5: Docs, translations, verification

**Files:**

- Modify: `docs/hooks.md`, `CHANGELOG.md`, `README.md`, `CLAUDE.md`
- Modify: `languages/` (`.pot`, `fr_CA.po`, `.mo`, `.json`)

- [ ] **Step 1: `docs/hooks.md`**

In the section describing the injected loop form (around line 123, "Every filter and sort control lives inside a real `<form>`"), add a paragraph:

```markdown
**Result count.** Once the loop has rendered, the injected form also carries `data-query-found-posts` (the loop's total result count, an integer) and `data-query-results-message` (the translated sentence announced to screen readers, for example "12 results found" or "No results found"). Both are absent when the count isn't known. After a filter, sort or search change, the plugin announces that message through WordPress's shared live region (`#a11y-speak-polite`) in place of the router's "Page loaded.". Core's own pagination keeps its "Page loaded." announcement. The total is WordPress's `found_posts`, so a loop with an `offset` counts the posts it skips, as core's Query Total block does.
```

If the file has a table of the form's attributes, add the two attributes to it as well.

- [ ] **Step 2: `CHANGELOG.md`**, under `## [Unreleased]`

Add to `### Added`:

```markdown
- **Screen readers hear the result count after filtering,** for example "12 results found" or "No results found", in place of the router's generic "Page loaded.". The count is also on the loop's form as `data-query-found-posts`. See [docs/hooks.md](docs/hooks.md).
```

Add to `### Changed`:

```markdown
- **Filter, sort and search changes no longer announce "Page loaded."** They announce the result count instead. Core's own pagination still announces "Page loaded.".
```

- [ ] **Step 3: `README.md`**

In `## Features`, add the bullet `- Announces the number of results to screen readers after each filter change`.

- [ ] **Step 4: Plugin `CLAUDE.md`**

In "Query Filter render flow", add after step 6:

```markdown
7. `Core\QueryLoopHandler` tags each custom loop's query args with `Query\ResultCount::QUERY_VAR` (the form id), and a `found_posts` filter records the loop's total in `ResultCount`. `BlockFilters::inject_loop_form()` stamps it on the form as `data-query-found-posts` / `data-query-results-message` (inherited loops use the main query's `found_posts`). After a navigation, `view.js` reads the message from the **new** form and speaks it with `@wordpress/a11y`, having passed `screenReaderAnnouncement: false` to the router.
```

In "Tests for this area", add:

```markdown
- `tests/php/ResultCountTest.php` — recording loop totals by form id, and the announced message.
- `tests/e2e/specs/announcements.spec.js` — the polite live region after filtering, for custom and inherited loops, and the no-JS form attributes.
```

- [ ] **Step 5: Translations**

wp-env is already running. Run `npm run build` **first**: `make-pot` scans `build/` as well as `src/`, and a stale build puts old strings back. Then run `npm run i18n:pot` and `msgmerge --update languages/pikari-gutenberg-query-filter-fr_CA.po languages/pikari-gutenberg-query-filter.pot`.

Translate the new strings into Canadian French:

- `No results found` → `Aucun résultat trouvé`;
- `%s result found` / `%s results found` → `%s résultat trouvé` / `%s résultats trouvés`.

The plural entry needs both `msgstr[0]` and `msgstr[1]`. Clear any `#, fuzzy` entries, then run `npm run i18n:mo && npm run i18n:json`.

- [ ] **Step 6: Full verification**

```bash
npm run lint:all && composer test && npm test && npm run build
PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm run test:e2e
```

All must pass. Report the exact counts.

- [ ] **Step 7: Commit**

```bash
git add docs/hooks.md CHANGELOG.md README.md CLAUDE.md languages/
git commit -m "docs: Document the result announcement and update translations"
```

Don't push; the controller opens the PR after the final review.
