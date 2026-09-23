# Query Filter 1.0 — B3: Form-Based Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Put every filter control inside a real `<form>`, so the plugin works without JavaScript, and replace the four ad-hoc URL builders in `view.js` with one pure `buildUrl()` driven by that form.

**Architecture:**

- Each Query Loop gets one hidden `<form>`, injected as the last child of the Query wrapper. Controls stay where they are and join it through the HTML `form` attribute.
- The form carries the current request's other query parameters as hidden inputs, so a no-JS GET submit preserves them.
- The browser's own `FormData` becomes the single source of truth for a filter change. `buildUrl()` reads it, rewrites only the names the form owns, and returns a URL.
- The store shrinks to `change` / `submit` / `navigate`, with one debounce and one in-flight guard, instead of four near-identical generators.

**Tech Stack:** PHP 8.4, PHPUnit 9.6 with Brain\Monkey and Mockery; Jest with `@wordpress/scripts`; Playwright 1.63 with `@wordpress/e2e-test-utils-playwright`; wp-env with Twenty Twenty-Five; WordPress Interactivity API (WP 7.1).

**Spec:** `docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md` (revision 2, approved). B3 implements §5.1, §5.2, §5.3, §6.1, §6.2, the §8.3 and remaining §8.4 test lists, and §9. It is the last row of §10.1.

## Global Constraints

- **Branch:** `feature/form-based-filters`, cut from `main` at `1f17f3c` (plugin version 1.0.0, unpublished). This plan is already committed on it.
- **Label:** `breaking`, **applied by hand** on the PR. The autolabeler reads the branch name and would label this `feature`; `main` is already at 1.0.0 from B1, so a wrong label does no damage to the version, but the changelog section matters.
- **Nothing is published.** 1.0.0 ships in sub-project E, not here (todo #529).
- **Code style:** PHP follows WordPress Coding Standards with **4 spaces, not tabs**. `phpcs.xml` skips `tests/`, so check each new or edited test file with `grep -c $'\t' <file>`, which must print 0. JavaScript is formatted by ESLint (tabs); Prettier ignores JS.
- **Store namespace** is `pikari/gutenberg-query-filter` everywhere. **Text domain** is `pikari-gutenberg-query-filter`. Every user-facing string is translated.
- **Commits:** `type: Brief description`. No `Co-Authored-By` or "Generated with" trailers. Never `--no-verify`.
- **TDD:** every behaviour change starts with a failing test, and you must watch it fail for the right reason. After writing a test that passes, **mutate the production code it covers** (delete the line, invert the condition) and confirm the test fails; restore. A test that survives its own mutation is not a test. Three of B1/B2's tests passed with the fix deleted — see "What went wrong before" below.
- **Environment:** this plugin's wp-env only (5884 development, 5885 tests). Never touch other Docker containers or ddev sites, and never run `wp-env destroy` or `wp-env clean`. Run `npm run build` before any E2E run.
- **Suites at the start:** PHP **170**, Jest **44**, Playwright **28**, all green. They must be green at the end of every task except where this plan says otherwise.
- **Expected red window:** Tasks 4 and 5 point the control markup at `actions.change`, which does not exist until Task 9. **The JavaScript Playwright specs are expected to fail between Task 4 and Task 9.** That is deliberate: the no-JS path is proven on its own first (Task 6). Task reviewers must not reject Tasks 4–8 for those failures; they must reject them for PHP or Jest failures. Task 9 restores a fully green suite.

## What went wrong before, and what it costs you

B1 and B2 were built from this same spec. Three plan errors and three vacuous tests got through. Read this before Task 1.

- **Push back on this plan.** Three of my instructions were wrong and each was caught by the implementer, not by me: a child-term lookup that mixed term IDs into a term-taxonomy-ID list; a cast test whose fixture passed either way; pagination handling assigned to the wrong sub-project. This plan is argued from the spec, not from a run. If a task's code does not do what its prose claims, say so and rule on it — do not implement the code as written.
- **The router only re-renders inside `data-wp-router-region`.** Everything outside the Query wrapper — the archive heading, the page title — is untouched by a client navigation. A Playwright test asking "did the page change?" that clicks a filter therefore proves nothing about anything outside the loop. Only a full `page.goto()` discriminates. One B2 test passed for exactly this reason.
- **Brain\Monkey's `expect( … )->never()` does not override an earlier `when( … )`.** If a test stubs a function and later asserts it is never called, the assertion is inert. Assert on a function that is genuinely never stubbed.
- **`tests/unit/__mocks__/@wordpress/interactivity.js` is template-managed** (`.github/config-templates/tests/…`) and is currently byte-identical to the template. Task 8 changes it, which creates drift; Task 8 also adds it to `skip-sync` so `sync-all.sh` cannot clobber it.
- **`sanitize_text_field()` destroys percent-encoded characters**, which is how WordPress stores the slug of any term or author whose name is not Latin. B1 shipped that bug and fixed it with `sanitize_title_for_query()` per comma-separated value. Task 6 adds the fixture that would have caught it.

## The classes and helpers B3 builds on

Shipped in B1 and B2, used as they are:

- `Url\QueryParams`: `__construct( ?int $query_id, bool $inherit = false )`, `::from_block( \WP_Block $block )`, `->prefix()`, `->key( string $name )`, `->page_key()`, `->is_inherit()`. Inherited loops use keys `query-{name}`, search `s`, page key `paged`. A custom loop with no `queryId` uses prefix `query-0-` and page key `query-page`. **Task 1 adds `->form_id()`.**
- `Url\FilterState`: `::from_array( array $get, QueryParams $params )`, `::for_loop( QueryParams $params )`, `::reset_cache()`, `->post_types()`, `->taxonomies()`, `->author_ids()`, `->search()`, `->sort()`, `->has_filters()`. It already accepts both `?key=a,b` and `?key[]=a&key[]=b`.
- `Query\QueryArgs::apply( array $args, FilterState $state )`, `Query\SortOptions::all()/find()/match()`, `Query\TaxonomySubquery::where( array $taxonomies )`, `Integrations\MainQueryFilter`.
- `Helpers\FilterHelper`: `get_filter_options()`, `get_all_option()`, `get_option_classes()`, `get_option_label_html()`. **B3 does not change any of these, and does not change the three public PHP filters.**
- `src/blocks/query-filter/view.js` currently exports `stripInheritedPagination( pathname, paginationBase )`, added in B2. Task 7 **moves** it into `src/utils/build-url.js` together with its Jest tests — it is not duplicated.

## Facts established by reading WordPress 7.1's runtime

Verified in `~/.wp-env/*/WordPress/wp-includes/js/dist/script-modules/interactivity/index.js`. Do not re-derive them.

- **`withSyncEvent( fn )` sets `fn.sync = true` and returns the same function.** It preserves generator-ness. Only four event members are deprecated outside it: `currentTarget`, `preventDefault()`, `stopPropagation()`, `stopImmediatePropagation()`. `event.target`, `event.type` and `event.isComposing` are safe without it. **So only `actions.submit` needs `withSyncEvent`, and `actions.change` must not call `preventDefault()`** — a `change` or `input` event has no default to prevent, and calling it would re-raise the deprecation this closes (roadmap #21).
- **The store proxy wraps every function it returns in `withScope()`, and `withScope()` of a generator function returns an async function that runs the generator to completion.** So `store( 'pikari/gutenberg-query-filter' ).actions.navigate( url )` from a `setTimeout` callback returns a promise and runs. This is exactly what Task 8 teaches the Jest mock, and it is what monorepo roadmap #30 is waiting for.
- **The router already discards stale responses.** `interactivity-router` sets a module-level `navigatingTo = href` before fetching and returns early if it changed by the time the response lands. That is why §6.2 step 5 is safe.
- **`actions.navigate( href, options )` accepts `{ replace: true }`** and calls `history.replaceState` instead of `pushState`.
- **Directives are hydrated for the whole subtree of any `data-wp-interactive` element**, with namespaced directive values (`ns::actions.x`) resolving against that namespace. `BlockFilters::render_block_query()` always puts `data-wp-interactive` on the Query wrapper, so a core Search block **inside** the loop is hydrated even though core's own search form carries no `data-wp-interactive` in the default variant. A Search block **outside** the loop has no `query` context, is never touched, and keeps working as core's.

## File Structure

| File                                                                    | Responsibility                                                                                                 |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `includes/Url/QueryParams.php` (modify)                                 | Adds `form_id()`: the one place a form ID string is built.                                                     |
| `includes/Url/LoopForm.php` (create)                                    | Pure form logic: the action path, the hidden inputs from a raw query string, the `<form>` markup.              |
| `includes/Integrations/BlockFilters.php` (modify)                       | Scans the rendered Query for controls that claim the form, injects the form, and reworks the Search input.     |
| `src/blocks/query-filter/render.php` (modify)                           | Control `name` / `form`, namespaced directives, no `data-wp-context`, `<noscript>` Apply button.               |
| `src/blocks/sort/render.php` (modify)                                   | The same for the Sort select.                                                                                  |
| `src/utils/build-url.js` (create)                                       | `buildUrl()`, `sameQuery()`, and `stripInheritedPagination()` moved from `view.js`.                            |
| `src/blocks/query-filter/view.js` (rewrite)                             | `actions.change`, `actions.submit`, `actions.endBurst`, `actions.navigate`, and the unchanged style callbacks. |
| `tests/unit/__mocks__/@wordpress/interactivity.js` (modify)             | `withScope` runs generators; one-argument `store()` returns the registered definition through that wrapper.    |
| `.github/plugin.json` (modify)                                          | `skip-sync` for that mock until monorepo #30 lands it upstream.                                                |
| `tests/php/QueryParamsTest.php` (modify)                                | `form_id()` for custom, no-`queryId` and inherited loops.                                                      |
| `tests/php/LoopFormTest.php` (create)                                   | Action paths, hidden-input parsing, dropped names, markup attributes. Runs without WordPress.                  |
| `tests/php/BlockFiltersTest.php` (modify)                               | Injection position and trigger, exact `form` matching, nested loops, the Search input and button.              |
| `tests/unit/utils/build-url.test.js` (create)                           | Every §8.3 `buildUrl` and `sameQuery` case.                                                                    |
| `tests/unit/blocks/query-filter/view.test.js` (rewrite)                 | Debounce, in-flight, same-query skip, IME, submit, search context, `replace`.                                  |
| `tests/e2e/fixtures/content.js`, `tests/e2e/setup/fixtures.js` (modify) | Button-only Search page, Search inside the inherited loop, a non-Latin category and author with one old post.  |
| `tests/e2e/specs/no-js.spec.js` (create)                                | The whole §8.4 "Without JavaScript" list.                                                                      |
| `tests/e2e/specs/filters.spec.js`, `search.spec.js` (modify/create)     | The §8.4 "With JavaScript" cases not already covered, plus accessibility.                                      |
| `tests/e2e/specs/visual.spec.js` (create)                               | `toHaveScreenshot` baselines per display type, and the bounding-box check.                                     |
| `languages/*` (modify)                                                  | Regenerated `.pot`, merged and translated `fr_CA.po`, compiled `.mo` and JSON.                                 |
| `docs/hooks.md`, `CLAUDE.md`, `CHANGELOG.md`, `README.md` (modify)      | The public markup contract, the render-flow description, and the breaking-changes list.                        |

---

## Rulings made while writing this plan

Each is a deliberate departure from, or refinement of, the spec's literal text. Implementers and reviewers should treat these as binding; challenge them if the code disagrees.

1. **`page` and `cst` are reset on both sides.** §5.2 drops `cst` and the loop's owned names from the hidden inputs; §6.1 deletes only `pageKey` and owned names client-side. Left as written, a no-JS submit and a JS filter change would disagree about `page` (0.3.4 deleted it alongside the page key) and about `cst`. **Both sides reset the same three names: the loop's `pageKey`, `page`, and `cst`.** `LoopForm::reset_names()` is the single definition; `buildUrl()` mirrors it.
2. **Values are trimmed before being written.** §6.1 says "skip empty values". A search of three spaces is not empty but is meaningless, and 0.3.4 trimmed it. Trim each value; skip it if the trimmed value is empty; write the trimmed value.
3. **The Search input gets `data-wp-on--blur`.** §6.2 says a `replace` burst "ends when focus leaves the input", but §5.3's attribute list has no way to observe that. Adding `data-wp-on--blur="pikari/gutenberg-query-filter::actions.endBurst"` implements the stated behaviour declaratively, rather than attaching a document-level listener.
4. **The non-Latin E2E assertion reads the slug from the DOM**, asserts it matches `/%[0-9a-f]{2}/`, and asserts the URL round-trips it — rather than hard-coding WordPress's percent-encoding of a particular string. Hard-coding would make the test brittle without making it stronger.
5. **Roadmap #33's stale-object-cache item cannot be closed here.** wp-env has no persistent object cache, so `cache_results=false` stays hand-verified. Task 13 amends #33 to say so rather than letting it silently disappear. **That amendment is a separate commit in the monorepo**, not this one: `_log/roadmap.md` belongs to `wordpress-plugins`, and this plugin is its own git repository.
6. **A no-JS submit writes `key[]=a&key[]=b`; a JavaScript change writes `key=a,b`.** Both are valid (§3.2) and `FilterState` accepts both, so the plan does not force them to agree. Two consequences the tasks below handle explicitly: Playwright assertions read a helper that normalizes the two, and `render.php` must read an array `$_GET` value or every checkbox renders unchecked after a no-JS submit.

---

### Task 1: `QueryParams::form_id()`

**Files:**

- Modify: `includes/Url/QueryParams.php`
- Test: `tests/php/QueryParamsTest.php`

**Interfaces:**

- Consumes: nothing new.
- Produces: `QueryParams::form_id(): string` — `pikari-gutenberg-query-filter-form-{queryId}`, `…-form-0` when the loop has no `queryId`, `…-form-inherit` when it inherits.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/QueryParamsTest.php` (4 spaces, no tabs):

```php
    public function test_form_id_uses_the_query_id(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-3',
            ( new QueryParams( 3 ) )->form_id()
        );
    }

    public function test_form_id_falls_back_to_zero_without_a_query_id(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-0',
            ( new QueryParams( null ) )->form_id()
        );
    }

    public function test_form_id_is_shared_by_every_inherited_loop(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-inherit',
            ( new QueryParams( null, true ) )->form_id()
        );
    }

    public function test_form_id_ignores_the_query_id_when_inheriting(): void {
        // An inherited loop can still carry a queryId in block context; its
        // parameters and its form are the main query's either way (spec §3.1).
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-inherit',
            ( new QueryParams( 7, true ) )->form_id()
        );
    }

    public function test_form_id_does_not_collide_between_loop_3_and_loop_30(): void {
        // BlockFilters matches the form attribute exactly for this reason
        // (spec §5.2); the IDs themselves must differ as plain strings too.
        $this->assertNotSame(
            ( new QueryParams( 3 ) )->form_id(),
            ( new QueryParams( 30 ) )->form_id()
        );
    }
```

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit --filter form_id`
Expected: FAIL — `Call to undefined method … ::form_id()`.

- [ ] **Step 3: Implement**

Add to `includes/Url/QueryParams.php`, after `page_key()`:

```php
    /**
     * Get the id of the loop's hidden filter form.
     *
     * Every control that filters this loop carries it as a `form` attribute,
     * and BlockFilters injects a form with this id when it finds one
     * (spec §5.2). Inherited loops share a single id, as they share a single
     * set of parameters.
     *
     * @return string `pikari-gutenberg-query-filter-form-3`, `…-form-0`, or
     *                `…-form-inherit`.
     */
    public function form_id(): string {
        if ( $this->inherit ) {
            return 'pikari-gutenberg-query-filter-form-inherit';
        }

        return sprintf( 'pikari-gutenberg-query-filter-form-%d', $this->query_id ?? 0 );
    }
```

- [ ] **Step 4: Run the tests and the suite**

Run: `vendor/bin/phpunit --filter form_id && composer test`
Expected: the five new tests pass; total **175 tests**, no failures.

- [ ] **Step 5: Mutate**

Change `'…-form-inherit'` to `'…-form-0'` and confirm two tests fail. Restore.

- [ ] **Step 6: Commit**

```bash
git add includes/Url/QueryParams.php tests/php/QueryParamsTest.php
git commit -m "feat: Add the loop form id to QueryParams"
```

---

### Task 2: `Url\LoopForm`

The form is built from the raw request, not from `$_GET`. PHP rewrites `$_GET` keys (`a.b` becomes `a_b`) and keeps only the last of a repeated key, either of which would silently corrupt a parameter the plugin is supposed to preserve untouched.

**Files:**

- Create: `includes/Url/LoopForm.php`
- Test: `tests/php/LoopFormTest.php`

**Interfaces:**

- Consumes: `QueryParams::form_id()`, `->page_key()`, `->is_inherit()` from Task 1.
- Produces, all `public static`:

  - `LoopForm::reset_names( QueryParams $params ): array` — the names a filter change clears: the loop's page key, `page`, `cst`. `paged` is already the page key when inherited.
  - `LoopForm::query_string(): string` — `$_SERVER['QUERY_STRING']` when set, otherwise `http_build_query( $_GET )`.
  - `LoopForm::hidden_inputs( string $query_string, array $dropped_names ): array` — list of `array( 'name' => string, 'value' => string )`, decoded, in request order.
  - `LoopForm::action( string $request_uri, bool $inherit, string $pagination_base ): string` — the path alone.
  - `LoopForm::render( QueryParams $params, string $action, array $hidden_inputs, string $pagination_base ): string` — the `<form>` element.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/LoopFormTest.php` (4 spaces, no tabs):

```php
<?php
/**
 * Tests for the hidden per-loop filter form.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;
use Pikari\GutenbergQueryFilter\Url\LoopForm;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Brain\Monkey\Functions;

class LoopFormTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\stubEscapeFunctions();
    }

    protected function tearDown(): void {
        unset( $_SERVER['QUERY_STRING'] );
        $_GET = array();
        parent::tearDown();
    }

    /*
     * reset_names()
     */

    public function test_reset_names_covers_the_page_key_page_and_cst(): void {
        $this->assertSame(
            array( 'query-3-page', 'page', 'cst' ),
            LoopForm::reset_names( new QueryParams( 3 ) )
        );
    }

    public function test_reset_names_uses_paged_for_an_inherited_loop(): void {
        $this->assertSame(
            array( 'paged', 'page', 'cst' ),
            LoopForm::reset_names( new QueryParams( null, true ) )
        );
    }

    /*
     * hidden_inputs()
     */

    public function test_hidden_inputs_decodes_names_and_values(): void {
        $this->assertSame(
            array( array( 'name' => 'utm source', 'value' => 'a b' ) ),
            LoopForm::hidden_inputs( 'utm+source=a%20b', array() )
        );
    }

    public function test_hidden_inputs_keeps_repeated_keys_php_would_collapse(): void {
        // $_GET would keep only the last `tag`; the raw query string keeps both.
        $this->assertSame(
            array(
                array( 'name' => 'tag', 'value' => 'a' ),
                array( 'name' => 'tag', 'value' => 'b' ),
            ),
            LoopForm::hidden_inputs( 'tag=a&tag=b', array() )
        );
    }

    public function test_hidden_inputs_keeps_names_php_would_rewrite(): void {
        // PHP turns `a.b` into `a_b` in $_GET.
        $this->assertSame(
            array( array( 'name' => 'a.b', 'value' => '1' ) ),
            LoopForm::hidden_inputs( 'a.b=1', array() )
        );
    }

    public function test_hidden_inputs_drops_an_owned_name(): void {
        $this->assertSame(
            array( array( 'name' => 'lang', 'value' => 'fr' ) ),
            LoopForm::hidden_inputs( 'query-3-category=news&lang=fr', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_drops_an_owned_name_in_array_form(): void {
        $this->assertSame(
            array(),
            LoopForm::hidden_inputs( 'query-3-category%5B%5D=news', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_drops_cst(): void {
        // core/query-pagination-numbers adds it; it describes a page this
        // filter change is resetting (spec §5.2).
        $this->assertSame(
            array(),
            LoopForm::hidden_inputs( 'cst=1', array( 'cst' ) )
        );
    }

    public function test_hidden_inputs_keeps_a_name_that_merely_starts_with_an_owned_one(): void {
        // `query-3-category` must not swallow `query-30-category`.
        $this->assertSame(
            array( array( 'name' => 'query-30-category', 'value' => 'news' ) ),
            LoopForm::hidden_inputs( 'query-30-category=news', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_keeps_a_valueless_pair(): void {
        $this->assertSame(
            array( array( 'name' => 'debug', 'value' => '' ) ),
            LoopForm::hidden_inputs( 'debug', array() )
        );
    }

    public function test_hidden_inputs_ignores_empty_segments(): void {
        $this->assertSame( array(), LoopForm::hidden_inputs( '&&', array() ) );
    }

    public function test_hidden_inputs_preserves_a_percent_encoded_slug(): void {
        // A non-Latin term slug is stored percent-encoded; decoding must give
        // the octets back, not mangle them (the B1 sanitize_text_field bug).
        $this->assertSame(
            array( array( 'name' => 'query-9-category', 'value' => '%e6%96%b0%e9%97%bb' ) ),
            LoopForm::hidden_inputs( 'query-9-category=%25e6%2596%25b0%25e9%2597%25bb', array() )
        );
    }

    /*
     * query_string()
     */

    public function test_query_string_prefers_the_raw_server_value(): void {
        $_SERVER['QUERY_STRING'] = 'tag=a&tag=b';
        $_GET                    = array( 'tag' => 'b' );

        $this->assertSame( 'tag=a&tag=b', LoopForm::query_string() );
    }

    public function test_query_string_falls_back_to_get(): void {
        $_GET = array( 'tag' => 'a' );

        $this->assertSame( 'tag=a', LoopForm::query_string() );
    }

    /*
     * action()
     */

    public function test_action_drops_the_query_string_and_fragment(): void {
        $this->assertSame(
            '/resources/library/',
            LoopForm::action( '/resources/library/?query-3-category=news#results', false, 'page' )
        );
    }

    public function test_action_keeps_a_custom_loops_pagination_segment(): void {
        // Only an inherited loop paginates through the path (spec §5.2).
        $this->assertSame(
            '/library/page/2/',
            LoopForm::action( '/library/page/2/', false, 'page' )
        );
    }

    public function test_action_strips_an_inherited_loops_pagination_segment(): void {
        $this->assertSame(
            '/category/news/',
            LoopForm::action( '/category/news/page/2/', true, 'page' )
        );
    }

    public function test_action_keeps_the_trailing_slash_style(): void {
        $this->assertSame(
            '/category/news',
            LoopForm::action( '/category/news/page/2', true, 'page' )
        );
    }

    public function test_action_honours_a_translated_pagination_base(): void {
        $this->assertSame(
            '/categorie/actualites/',
            LoopForm::action( '/categorie/actualites/pagina/3/', true, 'pagina' )
        );
    }

    public function test_action_leaves_a_path_that_merely_contains_the_base(): void {
        $this->assertSame(
            '/page-two/',
            LoopForm::action( '/page-two/', true, 'page' )
        );
    }

    public function test_action_falls_back_to_the_site_root(): void {
        $this->assertSame( '/', LoopForm::action( '?s=cat', false, 'page' ) );
    }

    /*
     * render()
     */

    public function test_render_describes_the_loop_for_the_browser_and_the_store(): void {
        $html = LoopForm::render(
            new QueryParams( 3 ),
            '/library/',
            array( array( 'name' => 'lang', 'value' => 'fr' ) ),
            'page'
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-3"', $html );
        $this->assertStringContainsString( 'class="wp-block-pikari-gutenberg-query-filter__form"', $html );
        $this->assertStringContainsString( 'method="get"', $html );
        $this->assertStringContainsString( 'action="/library/"', $html );
        $this->assertStringContainsString( 'data-wp-interactive="pikari/gutenberg-query-filter"', $html );
        $this->assertStringContainsString( 'data-wp-on--submit="actions.submit"', $html );
        $this->assertStringContainsString( 'data-query-page-key="query-3-page"', $html );
        $this->assertStringContainsString( 'data-query-inherit="false"', $html );
        $this->assertStringContainsString( 'data-query-pagination-base="page"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="lang" value="fr"', $html );
    }

    public function test_render_hides_the_form_twice_over(): void {
        // A theme that overrides [hidden] must not put the form back in flow,
        // and controls outside a hidden form still submit with it (spec §5.2).
        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' );

        $this->assertStringContainsString( 'hidden', $html );
        $this->assertStringContainsString( 'style="display:none"', $html );
    }

    public function test_render_is_novalidate(): void {
        // core/search marks its input required; without novalidate the browser
        // would block every submit while the box is empty (spec §5.2).
        $this->assertStringContainsString(
            'novalidate',
            LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' )
        );
    }

    public function test_render_marks_an_inherited_loop(): void {
        $html = LoopForm::render( new QueryParams( null, true ), '/category/news/', array(), 'page' );

        $this->assertStringContainsString( 'data-query-inherit="true"', $html );
        $this->assertStringContainsString( 'data-query-page-key="paged"', $html );
    }

    public function test_render_escapes_a_hidden_input(): void {
        $html = LoopForm::render(
            new QueryParams( 3 ),
            '/',
            array( array( 'name' => 'x', 'value' => '"><script>alert(1)</script>' ) ),
            'page'
        );

        $this->assertStringNotContainsString( '<script>', $html );
    }
}
```

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/LoopFormTest.php`
Expected: FAIL — `Class "…\Url\LoopForm" not found`. If instead you get "no tests executed", run `composer dump-autoload` first.

- [ ] **Step 3: Implement**

Create `includes/Url/LoopForm.php`:

```php
<?php
/**
 * The hidden filter form injected into each Query Loop.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Url;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the per-loop `<form>` that every filter control joins.
 *
 * The form is a GET form, so submitting it replaces the whole query string.
 * Anything the loop does not own is therefore re-sent as a hidden input, built
 * from the raw request rather than from $_GET (spec §5.2).
 */
class LoopForm {

    /**
     * Query parameter core's pagination-numbers block adds to describe a page.
     */
    private const PAGINATION_STATE = 'cst';

    /**
     * Names a filter change clears, whichever way the form is submitted.
     *
     * Kept in one place because both sides must agree: PHP drops them from
     * the hidden inputs, and buildUrl() deletes them from the URL. If they
     * disagreed, a no-JS submit would keep a page number a filter change
     * had just reset.
     *
     * @param QueryParams $params The loop's parameters.
     * @return string[] Names to drop.
     */
    public static function reset_names( QueryParams $params ): array {
        return array( $params->page_key(), 'page', self::PAGINATION_STATE );
    }

    /**
     * The current request's query string, exactly as it arrived.
     *
     * PHP rewrites `.` and space in $_GET keys and keeps only the last of a
     * repeated key, so $_GET is only a fallback for CLI and test contexts
     * where QUERY_STRING is not set.
     *
     * @return string Raw query string, without the leading `?`.
     */
    public static function query_string(): string {
        if ( isset( $_SERVER['QUERY_STRING'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Split and decoded below, then escaped at render.
            return (string) wp_unslash( $_SERVER['QUERY_STRING'] );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtering parameters don't require nonces.
        return http_build_query( wp_unslash( $_GET ) );
    }

    /**
     * Turn a raw query string into the form's hidden inputs.
     *
     * @param string   $query_string  Raw query string, without the leading `?`.
     * @param string[] $dropped_names Base names to leave out.
     * @return array<int, array{name: string, value: string}> Decoded pairs, in request order.
     */
    public static function hidden_inputs( string $query_string, array $dropped_names ): array {
        $inputs = array();

        foreach ( explode( '&', $query_string ) as $pair ) {
            if ( '' === $pair ) {
                continue;
            }

            $parts = explode( '=', $pair, 2 );
            $name  = urldecode( $parts[0] );
            $value = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';

            if ( '' === $name ) {
                continue;
            }

            // `key[]=a` and `key=a` are the same parameter to this plugin.
            $bracket = strpos( $name, '[' );
            $base    = false === $bracket ? $name : substr( $name, 0, $bracket );

            if ( in_array( $base, $dropped_names, true ) ) {
                continue;
            }

            $inputs[] = array(
                'name'  => $name,
                'value' => $value,
            );
        }

        return $inputs;
    }

    /**
     * The form's action: the request path, and nothing else.
     *
     * A GET submit replaces the query string and drops the fragment, so
     * carrying either here would be misleading. An inherited loop's page
     * number can live in the path, and a filter change resets it.
     *
     * @param string $request_uri     The request URI, path first.
     * @param bool   $inherit         Whether the loop inherits the main query.
     * @param string $pagination_base The rewrite's pagination base, e.g. `page`.
     * @return string Path, beginning with a slash.
     */
    public static function action( string $request_uri, bool $inherit, string $pagination_base ): string {
        $path = explode( '#', explode( '?', $request_uri, 2 )[0], 2 )[0];

        if ( '' === $path ) {
            $path = '/';
        }

        if ( $inherit ) {
            $base = preg_quote( $pagination_base, '#' );
            $path = (string) preg_replace( '#/' . $base . '/\d+(/?)$#', '$1', $path );
        }

        return $path;
    }

    /**
     * Render the form element.
     *
     * @param QueryParams $params          The loop's parameters.
     * @param string      $action          Action path, from action().
     * @param array       $hidden_inputs   Pairs, from hidden_inputs().
     * @param string      $pagination_base The rewrite's pagination base.
     * @return string The `<form>` element.
     */
    public static function render( QueryParams $params, string $action, array $hidden_inputs, string $pagination_base ): string {
        $inputs = '';
        foreach ( $hidden_inputs as $input ) {
            $inputs .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr( $input['name'] ),
                esc_attr( $input['value'] )
            );
        }

        return sprintf(
            '<form hidden novalidate style="display:none" id="%1$s" class="wp-block-pikari-gutenberg-query-filter__form" method="get" action="%2$s" data-wp-interactive="pikari/gutenberg-query-filter" data-wp-on--submit="actions.submit" data-query-page-key="%3$s" data-query-inherit="%4$s" data-query-pagination-base="%5$s">%6$s</form>',
            esc_attr( $params->form_id() ),
            esc_url( $action ),
            esc_attr( $params->page_key() ),
            $params->is_inherit() ? 'true' : 'false',
            esc_attr( $pagination_base ),
            $inputs
        );
    }
}
```

`Functions\stubEscapeFunctions()` makes `esc_attr` and `esc_url` return their argument, so the escaping test above asserts only that the raw `<script>` does not survive — add `Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );` inside that one test if `stubEscapeFunctions()` is too permissive to fail on the unmutated code. **Watch that test fail before you trust it.**

- [ ] **Step 4: Run them and watch them pass**

Run: `composer dump-autoload && vendor/bin/phpunit tests/php/LoopFormTest.php --testdox`
Expected: all pass.

- [ ] **Step 5: Mutate**

Delete the `in_array( $base, $dropped_names, true )` guard and confirm at least three tests fail. Change `explode( '=', $pair, 2 )` to `explode( '=', $pair )` and confirm a value containing `=` breaks — if no test covers that, add one (`redirect=/a?b=c`). Restore.

- [ ] **Step 6: Run the whole suite and lint, then commit**

```bash
composer test && npm run lint:php
grep -c $'\t' tests/php/LoopFormTest.php   # must print 0
git add includes/Url/LoopForm.php tests/php/LoopFormTest.php
git commit -m "feat: Add LoopForm, the per-loop filter form builder"
```

---

### Task 3: Inject the form into the rendered Query

`render_block_query()` already runs at priority 20 on `render_block_core/query`, after the loop and all its inner blocks have rendered. That is what makes the "does any control claim this form?" check reliable: by then every filter, Sort and Search block has emitted its `form` attribute.

**Files:**

- Modify: `includes/Integrations/BlockFilters.php`
- Test: `tests/php/BlockFiltersTest.php`

**Interfaces:**

- Consumes: `LoopForm::*` and `QueryParams::form_id()`.
- Produces: no new public API. `render_block_query()` keeps its `( string $block_content, array $block )` signature.

**Two traps, both of which have bitten this codebase:**

- **Do not call `QueryParams::from_block()` here.** This filter receives the parsed block array, not a `WP_Block`, and a Query block's own context is what it _receives_, not what it provides. Build the params from `$block['attrs']`: `new QueryParams( isset( $block['attrs']['queryId'] ) ? (int) $block['attrs']['queryId'] : null, ! empty( $block['attrs']['query']['inherit'] ) )`. A loop with no `queryId` must come out as `…-form-0` from the attrs path and `…-form-0` from the context path the controls use — there is a test for exactly that below.
- **Match the `form` attribute by value, never by substring.** `…-form-3` must not match `…-form-30`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/BlockFiltersTest.php`. `setUp()` already stubs the escape and translation functions and skips when `WP_HTML_Tag_Processor` is unavailable.

```php
    /*
     * render_block_query(): form injection
     */

    /**
     * Render a Query block whose inner HTML is given, with the request set.
     *
     * @param string $inner        Inner HTML of the Query wrapper.
     * @param array  $attrs        Query block attributes.
     * @param string $query_string Raw query string for the request.
     * @param string $tag          Wrapper tag name.
     * @return string Rendered HTML.
     */
    private function render_query( string $inner, array $attrs = array( 'queryId' => 3 ), string $query_string = '', string $tag = 'div' ): string {
        $_SERVER['QUERY_STRING'] = $query_string;
        $_SERVER['REQUEST_URI']  = '/library/?' . $query_string;

        return ( new BlockFilters() )->render_block_query(
            sprintf( '<%1$s class="wp-block-query">%2$s</%1$s>', $tag, $inner ),
            array( 'attrs' => $attrs )
        );
    }

    public function test_render_block_query_injects_the_form_when_a_control_claims_it(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-3"', $html );
    }

    public function test_render_block_query_injects_the_form_as_the_last_child(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select><p>After</p>'
        );

        $this->assertMatchesRegularExpression( '#<p>After</p><form [^>]*id="pikari-gutenberg-query-filter-form-3"#', $html );
        $this->assertStringEndsWith( '</form></div>', $html );
    }

    public function test_render_block_query_injects_before_a_non_div_wrapper_close(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            '',
            'main'
        );

        $this->assertStringEndsWith( '</form></main>', $html );
    }

    public function test_render_block_query_injects_no_form_without_a_control(): void {
        $html = $this->render_query( '<p>Just posts</p>' );

        $this->assertStringNotContainsString( '<form', $html );
    }

    public function test_render_block_query_ignores_another_loops_control(): void {
        // Loop 3 must not claim loop 30's select (spec §5.2).
        $html = $this->render_query(
            '<select name="query-30-category" form="pikari-gutenberg-query-filter-form-30"></select>'
        );

        $this->assertStringNotContainsString( '<form', $html );
    }

    public function test_render_block_query_owns_its_controls_names(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            'query-3-category=news&lang=fr'
        );

        $this->assertStringNotContainsString( '<input type="hidden" name="query-3-category"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="lang" value="fr"', $html );
    }

    public function test_render_block_query_owns_a_checkbox_name_in_array_form(): void {
        $html = $this->render_query(
            '<input type="checkbox" name="query-3-category[]" form="pikari-gutenberg-query-filter-form-3">',
            array( 'queryId' => 3 ),
            'query-3-category=news&lang=fr'
        );

        $this->assertStringNotContainsString( '<input type="hidden" name="query-3-category"', $html );
        $this->assertStringContainsString( 'name="lang"', $html );
    }

    public function test_render_block_query_resets_the_page_key_and_cst(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            'query-3-page=2&page=4&cst=1&lang=fr'
        );

        $this->assertStringNotContainsString( 'name="query-3-page"', $html );
        $this->assertStringNotContainsString( 'name="page"', $html );
        $this->assertStringNotContainsString( 'name="cst"', $html );
        $this->assertStringContainsString( 'name="lang"', $html );
    }

    public function test_render_block_query_uses_the_inherit_form_for_an_inherited_loop(): void {
        $html = $this->render_query(
            '<select name="query-category" form="pikari-gutenberg-query-filter-form-inherit"></select>',
            array( 'query' => array( 'inherit' => true ) )
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-inherit"', $html );
        $this->assertStringContainsString( 'data-query-inherit="true"', $html );
    }

    public function test_render_block_query_gives_a_loop_without_a_query_id_the_same_form_id_its_controls_use(): void {
        // The controls read queryId from block context, this filter from
        // block attributes. Both paths must land on `…-form-0`, or the
        // controls would point at a form that is never injected.
        $params = new \Pikari\GutenbergQueryFilter\Url\QueryParams( null );
        $html   = $this->render_query(
            sprintf( '<select name="query-0-category" form="%s"></select>', $params->form_id() ),
            array()
        );

        $this->assertStringContainsString( sprintf( 'id="%s"', $params->form_id() ), $html );
        $this->assertStringContainsString( 'data-query-page-key="query-page"', $html );
    }

    public function test_render_block_query_gives_a_nested_loop_its_own_form(): void {
        // The inner loop rendered first, so its form is already in the HTML.
        // The outer loop must add its own and leave the inner one alone.
        $inner = '<div class="wp-block-query">'
            . '<select name="query-9-category" form="pikari-gutenberg-query-filter-form-9"></select>'
            . '<form id="pikari-gutenberg-query-filter-form-9"><input type="hidden" name="lang" value="fr" /></form>'
            . '</div>';

        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>' . $inner
        );

        $this->assertSame( 1, substr_count( $html, 'id="pikari-gutenberg-query-filter-form-9"' ) );
        $this->assertSame( 1, substr_count( $html, 'id="pikari-gutenberg-query-filter-form-3"' ) );
        $this->assertStringEndsWith( '</form></div>', $html );
    }

    public function test_render_block_query_does_not_own_a_hidden_input_of_a_nested_form(): void {
        // The nested form's own hidden inputs carry a name but no `form`
        // attribute, so the outer scan must not treat them as controls.
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
            . '<form id="pikari-gutenberg-query-filter-form-9"><input type="hidden" name="lang" value="fr" /></form>',
            array( 'queryId' => 3 ),
            'lang=fr'
        );

        $this->assertStringContainsString( '<input type="hidden" name="lang" value="fr"', $html );
    }

    public function test_render_block_query_still_marks_the_router_region_when_it_injects(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag();

        $this->assertSame( 'query-3', $processor->get_attribute( 'data-wp-router-region' ) );
    }
```

Add `unset( $_SERVER['QUERY_STRING'], $_SERVER['REQUEST_URI'] );` to the existing `tearDown()`.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php --filter render_block_query`
Expected: the new tests fail; the existing `render_block_query` tests still pass.

- [ ] **Step 3: Implement**

In `includes/Integrations/BlockFilters.php`, add `use Pikari\GutenbergQueryFilter\Url\LoopForm;` beside the existing `QueryParams` import, then replace the end of `render_block_query()`:

```php
        if ( isset( $block[ self::UNIQUE_ID_START_KEY ] ) ) {
            $start = $block[ self::UNIQUE_ID_START_KEY ];
            self::advance_unique_id( 'wp_unique_id', '', $start['id'] + self::UNIQUE_ID_RESERVE );
            self::advance_unique_id( 'wp_unique_prefixed_id', 'wp-elements-', $start['elements'] + self::UNIQUE_ID_RESERVE );
        }

        return self::inject_loop_form( $processor->get_updated_html(), $block );
    }

    /**
     * Add the loop's hidden filter form as the last child of its wrapper.
     *
     * Runs after the whole loop has rendered, so every control has emitted
     * its `form` attribute and blocks hidden after rendering, fragment caches
     * and render order are all irrelevant (spec §5.2).
     *
     * @param string $html  Rendered Query block.
     * @param array  $block Parsed block.
     * @return string HTML, with the form appended when a control claims it.
     */
    private static function inject_loop_form( string $html, array $block ): string {
        // The Query block's own context is what it receives, not what it
        // provides, so the queryId comes from its attributes.
        $params = new QueryParams(
            isset( $block['attrs']['queryId'] ) ? (int) $block['attrs']['queryId'] : null,
            ! empty( $block['attrs']['query']['inherit'] )
        );

        $targets = self::form_targets( $html, $params->form_id() );
        if ( ! $targets['found'] ) {
            return $html;
        }

        global $wp_rewrite;
        $pagination_base = ( $wp_rewrite instanceof \WP_Rewrite ) ? $wp_rewrite->pagination_base : 'page';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path only; escaped with esc_url() in LoopForm::render().
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

        $form = LoopForm::render(
            $params,
            LoopForm::action( $request_uri, $params->is_inherit(), $pagination_base ),
            LoopForm::hidden_inputs(
                LoopForm::query_string(),
                array_merge( $targets['names'], LoopForm::reset_names( $params ) )
            ),
            $pagination_base
        );

        // render_block_core/query receives only this block's HTML, so the
        // wrapper is its first tag and its close is the last matching one.
        $tag = strtolower( self::wrapper_tag( $html ) );
        if ( '' === $tag ) {
            return $html;
        }

        $close    = '</' . $tag . '>';
        $position = strripos( $html, $close );
        if ( false === $position ) {
            return $html;
        }

        return substr( $html, 0, $position ) . $form . substr( $html, $position );
    }

    /**
     * The wrapper's tag name, which core lets a theme set to div, main, section or aside.
     *
     * @param string $html Rendered Query block.
     * @return string Lowercase tag name, or '' when there is no tag.
     */
    private static function wrapper_tag( string $html ): string {
        $processor = new \WP_HTML_Tag_Processor( $html );

        return $processor->next_tag() ? (string) $processor->get_tag() : '';
    }

    /**
     * Find the controls that claim a form id, and the base names they own.
     *
     * The comparison is by attribute value, never by substring, so
     * `…-form-3` cannot claim `…-form-30`'s controls.
     *
     * @param string $html    Rendered Query block.
     * @param string $form_id The loop's form id.
     * @return array{found: bool, names: string[]} Whether any control claimed it, and their base names.
     */
    private static function form_targets( string $html, string $form_id ): array {
        $found     = false;
        $names     = array();
        $processor = new \WP_HTML_Tag_Processor( $html );

        while ( $processor->next_tag() ) {
            if ( $form_id !== $processor->get_attribute( 'form' ) ) {
                continue;
            }

            $found = true;

            $name = $processor->get_attribute( 'name' );
            if ( ! is_string( $name ) || '' === $name ) {
                continue;
            }

            $bracket = strpos( $name, '[' );
            $names[] = false === $bracket ? $name : substr( $name, 0, $bracket );
        }

        return array(
            'found' => $found,
            'names' => array_values( array_unique( $names ) ),
        );
    }
```

- [ ] **Step 4: Run them and watch them pass**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php --testdox && composer test`
Expected: all green.

- [ ] **Step 5: Mutate**

Change the `form` comparison from `!==` to `! str_contains( (string) $processor->get_attribute( 'form' ), $form_id )` and confirm `test_render_block_query_ignores_another_loops_control` fails. Change `strripos` to `stripos` and confirm the nested-loop test fails. Restore both.

- [ ] **Step 6: Lint and commit**

```bash
npm run lint:php && composer test
git add includes/Integrations/BlockFilters.php tests/php/BlockFiltersTest.php
git commit -m "feat: Inject a hidden filter form into each Query Loop"
```

---

### Task 4: Control markup and the no-JS Apply button

From here the rendered controls point at `actions.change`, which does not exist until Task 9. **The JavaScript Playwright specs are expected to fail until then** (see Global Constraints). PHP and Jest must stay green.

**Files:**

- Modify: `includes/Helpers/FilterHelper.php`
- Modify: `src/blocks/query-filter/render.php`
- Modify: `src/blocks/sort/render.php`
- Test: `tests/php/FilterHelperTest.php`
- Modify: `docs/hooks.md`

**Interfaces:**

- Consumes: `QueryParams::form_id()`.
- Produces: `FilterHelper::current_value( string $query_var, string $filter_type ): string`, and the public markup contract — `name`, `form`, `wp-block-pikari-gutenberg-query-filter__form`, `wp-block-pikari-gutenberg-query-filter__submit`.

**Why `current_value()` exists.** `render.php` line 81 reads `$_GET[ $query_var ]` behind an `is_scalar()` guard and falls back to `''`. Checkboxes are now named `query-3-category[]`, so a no-JS submit comes back as an **array**, the guard empties it, and every checkbox renders **unchecked** on a page whose results are correctly filtered. B3 introduces that bug unless this task fixes it. The plugin's CLAUDE.md also says option logic lives in `FilterHelper`, not `render.php`.

**What changes, and what must not:**

- Every control gets `name` and `form`. Select and radio use `$query_var`; checkbox uses `$query_var . '[]'`; Sort uses `$sort_var`.
- **The radio group's `name` stops being the block's UUID and becomes `$query_var`.** That is what makes a radio filter submit. Two blocks filtering the same taxonomy in one loop therefore merge into one radio group; spec §7 documents this as unsupported.
- Every plugin directive value is **namespaced**: `data-wp-on--change="pikari/gutenberg-query-filter::actions.change"`. Inside a loop the nearest `data-wp-interactive` may be `core/query`.
- Both wrappers **drop `data-wp-context`** and keep `data-wp-interactive`. `$page_var` and the `$wp_rewrite` / `$pagination_base` block go with it — the form now carries all three values as `data-query-*`.
- Unchanged: every class, the `<fieldset>`/`<legend>`/`<label>` structure, the per-option `{key}_{slug}` classes, the radio "All" option with `value=""`, the `<label for>` on the select, and the three PHP filters.

- [ ] **Step 1: Write the failing tests for `FilterHelper::current_value()`**

Append to `tests/php/FilterHelperTest.php` (4 spaces, no tabs; add `$_GET = array();` to `tearDown()` if it has none):

```php
    /*
     * current_value()
     */

    public function test_current_value_reads_a_scalar(): void {
        $_GET = array( 'query-3-category' => 'news' );

        $this->assertSame( 'news', FilterHelper::current_value( 'query-3-category', 'taxonomy' ) );
    }

    public function test_current_value_joins_an_array_from_a_no_js_submit(): void {
        // Checkboxes are named `query-3-category[]`, so a form GET returns an
        // array. Without this, every checkbox renders unchecked on a page
        // whose results are correctly filtered.
        $_GET = array( 'query-3-category' => array( 'news', 'events' ) );

        $this->assertSame( 'news,events', FilterHelper::current_value( 'query-3-category', 'taxonomy' ) );
    }

    public function test_current_value_is_empty_when_absent(): void {
        $_GET = array();

        $this->assertSame( '', FilterHelper::current_value( 'query-3-category', 'taxonomy' ) );
    }

    public function test_current_value_keeps_a_percent_encoded_slug(): void {
        // sanitize_text_field() strips %xx octets, which is how WordPress
        // stores a non-Latin slug; taxonomy and author values are sanitized
        // one at a time with sanitize_title_for_query() instead.
        $_GET = array( 'query-3-category' => '%e6%96%b0%e9%97%bb' );

        $this->assertSame(
            '%e6%96%b0%e9%97%bb',
            FilterHelper::current_value( 'query-3-category', 'taxonomy' )
        );
    }

    public function test_current_value_sanitizes_each_value_of_an_array_separately(): void {
        $_GET = array( 'query-3-author' => array( 'jane-doe', '%e5%b1%b1%e7%94%b0' ) );

        $this->assertSame(
            'jane-doe,%e5%b1%b1%e7%94%b0',
            FilterHelper::current_value( 'query-3-author', 'author' )
        );
    }

    public function test_current_value_drops_a_nested_array(): void {
        $_GET = array( 'query-3-category' => array( array( 'news' ) ) );

        $this->assertSame( '', FilterHelper::current_value( 'query-3-category', 'taxonomy' ) );
    }

    public function test_current_value_uses_text_sanitization_for_post_types(): void {
        $_GET = array( 'query-3-post_type' => 'post' );

        $this->assertSame( 'post', FilterHelper::current_value( 'query-3-post_type', 'post-type' ) );
    }
```

Stub `sanitize_title_for_query`, `sanitize_text_field` and `wp_unslash` the way the file's existing tests do — `sanitize_title_for_query` must **not** be stubbed with `returnArg()` for the percent-encoding tests, or they prove nothing. Use `Functions\when( 'sanitize_title_for_query' )->alias( fn( $v ) => strtolower( (string) $v ) );` so a stripped `%` would show up.

- [ ] **Step 2: Run them and watch them fail, then implement**

Move the whole `$raw_value` / `$current_value` block from `render.php` lines 79–97 into `FilterHelper::current_value()`, widened to accept an array: cast a scalar to a one-element list, drop any non-scalar member, sanitize each member (`sanitize_title_for_query` for `author` and `taxonomy`, `sanitize_text_field` otherwise), and `implode( ',', … )`. Keep the existing phpcs ignore comment and its explanation.

- [ ] **Step 3: Rework `src/blocks/query-filter/render.php`**

Replace lines 79–97 with `$current_value = FilterHelper::current_value( $query_var, $filter_type );`. Delete the `global $wp_rewrite;` block and `$page_var`. Add `$form_id = $params->form_id();` beside `$params`. Replace the wrapper opening tag:

```php
<div <?php echo wp_kses_post( $wrapper_attributes ); ?> data-wp-interactive="pikari/gutenberg-query-filter">
```

Replace the three control tags:

```php
        <select class="wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $query_var ); ?>" form="<?php echo esc_attr( $form_id ); ?>" data-wp-on--change="pikari/gutenberg-query-filter::actions.change">
```

```php
                <input type="radio" name="<?php echo esc_attr( $query_var ); ?>" form="<?php echo esc_attr( $form_id ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $current_value, $option['value'] ); ?> data-wp-on--change="pikari/gutenberg-query-filter::actions.change">
```

```php
                <input type="checkbox" name="<?php echo esc_attr( $query_var ); ?>[]" form="<?php echo esc_attr( $form_id ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $is_checked ); ?> data-wp-on--change="pikari/gutenberg-query-filter::actions.change">
```

Add, immediately before the closing `</div>` of the wrapper (after `<?php endif; ?>`):

```php
    <noscript>
        <button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="wp-block-pikari-gutenberg-query-filter__submit">
            <?php esc_html_e( 'Apply filters', 'pikari-gutenberg-query-filter' ); ?>
        </button>
    </noscript>
```

- [ ] **Step 4: Rework `src/blocks/sort/render.php`**

Same three edits: drop `global $wp_rewrite;`, `$pagination_base`, `$page_var` and the wrapper's `data-wp-context`; add `$form_id = $params->form_id();`; then:

```php
    <select class="wp-block-pikari-gutenberg-query-filter-sort__select wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $sort_var ); ?>" form="<?php echo esc_attr( $form_id ); ?>" data-wp-on--change="pikari/gutenberg-query-filter::actions.change">
```

and the same `<noscript>` block before the wrapper's `</div>`.

- [ ] **Step 5: Check it renders**

`render.php` is deliberately not unit-tested (plugin CLAUDE.md), so prove it in a browser:

```bash
npm run build && npm run wp-env
```

Visit a page with a Query Loop and filters on `http://localhost:5884`. In DevTools confirm: each control has `name` and `form`; there is exactly one `<form id="pikari-gutenberg-query-filter-form-…">` as the last child of `.wp-block-query`; the form is not visible and takes no space; and no Apply button is in the DOM (JavaScript is on). Then load the same page with JavaScript disabled and confirm one Apply button per filter block, each of which submits every filter.

- [ ] **Step 6: Update `docs/hooks.md`**

`docs/hooks.md` is the public contract for theme authors and the plugin's CLAUDE.md requires it to change in the same commit. Add a "Form and controls" section covering: the injected `<form>` and its `__form` class; the `name` and `form` attributes on every control, with the §5.1 table; the `__submit` class and the `<noscript>` wrapper; the note that the radio group's `name` is now the query parameter, so two blocks on one taxonomy share a group. Say plainly that the `<input>` remains outside all filterable markup and that `view.js` now depends on `name`, `form` and `value` rather than on `queryVar` context.

- [ ] **Step 7: Lint and commit**

```bash
npm run lint:php && npm run lint:md && composer test && npm test
grep -c $'\t' tests/php/FilterHelperTest.php   # must print 0
git add includes/Helpers/FilterHelper.php tests/php/FilterHelperTest.php src/blocks/query-filter/render.php src/blocks/sort/render.php docs/hooks.md
git commit -m "feat: Put every filter control in the loop form"
```

---

### Task 5: Rework the core Search block

**Files:**

- Modify: `includes/Integrations/BlockFilters.php` (`render_block_search()`)
- Test: `tests/php/BlockFiltersTest.php`

**This task removes more than it adds.** Core's `<form>` must come out untouched: delete the `set_attribute` calls for `action`, `data-wp-interactive`, `data-wp-on--submit` and `data-wp-context` on it, delete the `$action` computation and the `wp_interactivity_state()` call. The search value now lives in the input's own context, which is what survives a region re-render.

The existing `isset( $context['query'] )` guard at line 203 is already what §5.3 asks for — a Search block with `query` context but no `queryId` is a loop without an ID, and must still be handled. Do not change it; pin it with a test.

**Interfaces:**

- Consumes: `QueryParams::key( 's' )`, `->form_id()`.
- Produces: the Search input's attributes, which Task 9's store reads.

- [ ] **Step 1: Write the failing tests**

The file already has a `render_block_search()` section with four tests and a `stub_search_render_functions()` helper. **Two of those four tests assert on `data-wp-context` on core's `<form>`** (`…sends_pagination_base_for_an_inherited_loop`, `…_for_a_custom_loop`); B3 deletes that attribute, so delete both tests and name them in the commit body. The two naming tests survive unchanged and are your regression net for `QueryParams::key( 's' )`.

Trim `stub_search_render_functions()` to what the new code actually calls — `wp_enqueue_script_module`, `sanitize_text_field`, `wp_unslash`, `wp_json_encode` — and drop `get_query_var`, `add_query_arg` and `wp_interactivity_state`. If a removed stub leaves a test passing, the production code still calls a function it should not.

Then append, following the file's existing `Mockery::mock( 'WP_Block' )` convention:

```php
    /**
     * Render a core/search block inside a loop.
     *
     * @param array $context Block context.
     * @return string Rendered HTML.
     */
    private function render_search( array $context ): string {
        $this->stub_search_render_functions();

        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = $context;

        return ( new BlockFilters() )->render_block_search(
            '<form role="search" method="get" action="/" class="wp-block-search">'
            . '<input class="wp-block-search__input" type="search" name="s" required value="" />'
            . '<button type="submit" class="wp-block-search__button">Search</button>'
            . '</form>',
            array(),
            $instance
        );
    }

    public function test_render_block_search_leaves_cores_form_alone(): void {
        $html = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'form' ) );

        $this->assertSame( '/', $processor->get_attribute( 'action' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-interactive' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-context' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-on--submit' ) );
    }

    public function test_render_block_search_joins_the_input_to_the_loop_form(): void {
        $_GET = array( 'query-3-s' => 'cats' );

        $html      = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 'query-3-s', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'cats', $processor->get_attribute( 'value' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-3', $processor->get_attribute( 'form' ) );
        $this->assertSame(
            'pikari/gutenberg-query-filter::{"searchValue":"cats"}',
            $processor->get_attribute( 'data-wp-context' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::context.searchValue',
            $processor->get_attribute( 'data-wp-bind--value' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.change',
            $processor->get_attribute( 'data-wp-on--input' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.change',
            $processor->get_attribute( 'data-wp-on--compositionend' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.endBurst',
            $processor->get_attribute( 'data-wp-on--blur' )
        );
    }

    public function test_render_block_search_joins_the_submit_button_to_the_loop_form(): void {
        $html      = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'button' ) );

        $this->assertSame( 'pikari-gutenberg-query-filter-form-3', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_uses_the_bare_s_in_an_inherited_loop(): void {
        $html      = $this->render_search( array( 'query' => array( 'inherit' => true ) ) );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 's', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-inherit', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_handles_a_loop_without_a_query_id(): void {
        // A Query Loop with no queryId still provides `query` context; the
        // Search block in it must join that loop's form (spec §5.3).
        $html      = $this->render_search( array( 'query' => array( 'inherit' => false ) ) );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 'query-0-s', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-0', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_ignores_a_block_outside_a_loop(): void {
        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = array();

        $content = '<form class="wp-block-search"><input class="wp-block-search__input" name="s" /></form>';

        $this->assertSame(
            $content,
            ( new BlockFilters() )->render_block_search( $content, array(), $instance )
        );
    }
```

Add `$_GET = array();` to `tearDown()`.

- [ ] **Step 2: Run them and watch them fail**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php --filter render_block_search`
Expected: the new tests fail. The old Search tests that assert on core's form will fail too — **delete those, they encode behaviour the spec removes.** Name each deletion in the commit body.

- [ ] **Step 3: Implement**

Replace the body of `render_block_search()` after the context guard and the `wp_enqueue_script_module()` call:

```php
        $params    = QueryParams::from_block( $instance );
        $query_var = $params->key( 's' );
        $form_id   = $params->form_id();

        // Get and sanitize the current search value.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search forms don't require nonces for GET requests.
        $value = isset( $_GET[ $query_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var ] ) ) : '';

        $context = wp_json_encode( array( 'searchValue' => $value ) );
        if ( false === $context ) {
            $context = '{}';
        }

        // Only the input and the submit button change. Core's <form>, and any
        // data-wp-* core put on it, are left exactly as they are: the loop
        // form is what submits, and the input joins it by id (spec §5.3).
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        while ( $processor->next_tag() ) {
            if ( 'INPUT' === $processor->get_tag() && $processor->has_class( 'wp-block-search__input' ) ) {
                $processor->set_attribute( 'name', $query_var );
                $processor->set_attribute( 'value', $value );
                $processor->set_attribute( 'form', $form_id );
                $processor->set_attribute( 'data-wp-context', 'pikari/gutenberg-query-filter::' . $context );
                $processor->set_attribute( 'data-wp-bind--value', 'pikari/gutenberg-query-filter::context.searchValue' );
                $processor->set_attribute( 'data-wp-on--input', 'pikari/gutenberg-query-filter::actions.change' );
                $processor->set_attribute( 'data-wp-on--compositionend', 'pikari/gutenberg-query-filter::actions.change' );
                // Ends a typing burst, so the next search pushes a history
                // entry instead of replacing one (spec §6.2).
                $processor->set_attribute( 'data-wp-on--blur', 'pikari/gutenberg-query-filter::actions.endBurst' );
                continue;
            }

            if ( 'BUTTON' === $processor->get_tag() && $processor->has_class( 'wp-block-search__button' ) ) {
                $processor->set_attribute( 'form', $form_id );
            }
        }

        return $processor->get_updated_html();
```

Delete the now-unused `$page_var`, `$wp_rewrite` / `$pagination_base` block, `$current_page`, `$action` and the `wp_interactivity_state()` call.

- [ ] **Step 4: Run them and watch them pass**

Run: `vendor/bin/phpunit tests/php/BlockFiltersTest.php --testdox && composer test`

- [ ] **Step 5: Mutate**

Delete the `set_attribute( 'form', … )` on the button and confirm its test fails. Restore.

- [ ] **Step 6: Hands-on check**

Rebuild, then with JavaScript **off** load a page whose loop contains a Search block: typing a term and pressing Enter must submit the loop form and keep the other filters. With JavaScript on, the input must still show its current value after a filter change elsewhere on the page.

- [ ] **Step 7: Lint and commit**

```bash
npm run lint:php && composer test
git add includes/Integrations/BlockFilters.php tests/php/BlockFiltersTest.php
git commit -m "feat: Join the core Search input to the loop form"
```

---

### Task 6: E2E fixtures and the no-JavaScript suite

The server half is now complete and independently provable: with JavaScript disabled, nothing in `view.js` runs. Write these specs here, before the store rewrite, so a pass means the markup is right rather than that the store papered over it.

**Files:**

- Modify: `tests/e2e/fixtures/content.js`
- Modify: `tests/e2e/setup/fixtures.js`
- Modify: `tests/e2e/utils.js`
- Create: `tests/e2e/specs/no-js.spec.js`

**Interfaces:**

- Consumes: `visitor`, `resultTitles`, `param` from `tests/e2e/utils.js`; `PAGES`, `TEMPLATES`, `newestTitles` from the fixtures.
- Produces, exported from `tests/e2e/fixtures/content.js`: `NON_LATIN_CATEGORY`, `NON_LATIN_AUTHOR`, `NON_LATIN_TITLE`, and `PAGES.buttonSearch`. Exported from `tests/e2e/utils.js`: `ownedParam`.

**`param()` is not enough here.** A no-JS submit of a checkbox group writes `query-1-category[]=news&query-1-category[]=events`; a JavaScript change writes `query-1-category=news,events`. Both are correct (Ruling 6), so `param( page, 'query-1-category' )` returns `null` after a no-JS submit and every assertion built on it fails. Add to `tests/e2e/utils.js`, and use it throughout this file:

```js
/**
 * A filter parameter of the current page, however the form wrote it.
 *
 * Submitting the form without JavaScript writes `name[]=a&name[]=b`;
 * buildUrl() writes `name=a,b`. Both are valid (spec §3.2), so specs that
 * run both ways compare the normalized form.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          name Parameter name, without `[]`.
 * @return {string|null} Comma-joined value, or null when absent.
 */
const ownedParam = (page, name) => {
	const search = new URL(page.url()).searchParams;
	const values = [
		...search.getAll(name),
		...search.getAll(`${name}[]`),
	].flatMap((value) => value.split(','));

	return values.length ? values.join(',') : null;
};
```

**Fixture rules — get these wrong and you will break the 28 existing specs:**

- The non-Latin post must be **older than every other post, including the sticky one** (`2025-12-29T09:00:00`), and its title must sort **after every existing title** (`Zucchini`). Then no `newestTitles()` result and no `titlesByTitle()` result changes.
- It gets its **own** new category and its **own** new author, so no existing category or author filter changes either.
- Create the category with a `name` only and let WordPress generate the slug. That is the path that produces a percent-encoded slug. Create the user with `slug: '山田太郎'` so `wp_insert_user` runs `sanitize_title()` on it; do not rely on the username, which REST sanitizes to ASCII.
- After adding the fixtures, run the **whole** E2E suite and fix any assertion that counted posts rather than naming them.

- [ ] **Step 1: Add the fixtures**

In `tests/e2e/fixtures/content.js`:

```js
// A category and an author whose slugs WordPress stores percent-encoded.
// sanitize_text_field() destroys those octets, which silently emptied the
// filter in B1 and showed every author's posts; these fixtures make that
// regression visible (roadmap #33).
const NON_LATIN_CATEGORY = { name: '新闻' };
const NON_LATIN_AUTHOR = {
	username: 'yamada',
	name: '山田太郎',
	slug: '山田太郎',
};

// Older than every other post, and last alphabetically, so adding it changes
// no existing expectation.
const NON_LATIN_TITLE = 'Zucchini';
```

Append to `POSTS`, chained after the existing sticky `.concat()`:

```js
	.concat({
		title: NON_LATIN_TITLE,
		date: '2025-12-29T09:00:00',
		category: 'nonLatin',
		author: 'yamada',
	});
```

`'nonLatin'` is the key the setup records the generated slug under (`categoryIds.nonLatin = term.slug`-style lookup), **not** the slug itself — the setup must not assume what WordPress generated.

Add a Query Loop page with core's button-only Search variant, which §8.4 requires and `PAGES` has never had:

```js
	buttonSearch: {
		slug: 'e2e-button-search',
		path: '/e2e-button-search/',
		queryId: 4,
		title: 'E2E button-only search',
		content: queryBlock(
			4,
			`<!-- wp:search {"label":"Search","buttonText":"Search","buttonPosition":"button-only","isSearchFieldHidden":true} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Category","displayType":"radio"} /-->`
		),
	},
```

Add a Search block **inside** the inherited loop of `TEMPLATES.category`, as the first inner block, so `s` is an owned name on an archive (roadmap #33):

```js
	`<!-- wp:search {"label":"Search","buttonText":"Search"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy",…
```

In `tests/e2e/setup/fixtures.js`, create the category from its name alone and record the generated slug, create the author, and assign them to the new post. Export nothing new from the setup — specs read the slug from the DOM (Ruling 4).

- [ ] **Step 2: Rebuild fixtures and confirm the suite is unchanged**

```bash
npm run build && npm run test:e2e
```

Expected: **28 passing**, apart from the JavaScript specs already red from Task 4. Record in the ledger exactly which specs are red and why, so the Task 9 reviewer can check they all come back.

- [ ] **Step 3: Write `tests/e2e/specs/no-js.spec.js`**

```js
/**
 * The filters without JavaScript.
 *
 * Nothing in view.js runs here, so these specs prove the server-rendered
 * form on its own: the `form` attributes, the hidden inputs, `novalidate`
 * and the <noscript> buttons (spec §5.1, §5.2, §8.4).
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const { PAGES, newestTitles } = require('../fixtures/content');
const { ownedParam, param, resultTitles, visitor } = require('../utils');

test.use({ javaScriptEnabled: false, storageState: visitor });

const APPLY = 'Apply filters';

test.describe('Filters without JavaScript', () => {
	test('shows an Apply button for each filter block', async ({ page }) => {
		await page.goto(PAGES.filters.path);

		// One per Query Filter block, each submitting the same form.
		await expect(page.getByRole('button', { name: APPLY })).toHaveCount(2);
	});

	test('applies every filter at once, with an empty search box', async ({
		page,
	}) => {
		// core/search marks its input required; only the form's novalidate
		// lets this submit at all (spec §5.2).
		await page.goto(PAGES.filters.path);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(ownedParam(page, 'query-1-category')).toBe('news');
		expect(await resultTitles(page)).toEqual(
			newestTitles((post) => post.category === 'news')
		);
	});

	test('honours a key[] URL', async ({ page }) => {
		await page.goto(
			`${PAGES.filters.path}?query-1-category[]=news&query-1-category[]=events`
		);

		await expect(page.getByRole('checkbox', { name: 'News' })).toBeChecked();
		await expect(page.getByRole('checkbox', { name: 'Events' })).toBeChecked();
	});

	test('submits the filters when Enter is pressed in the search box', async ({
		page,
	}) => {
		await page.goto(PAGES.filters.path);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('searchbox').fill('Apple');
		await page.getByRole('searchbox').press('Enter');

		expect(param(page, 'query-1-s')).toBe('Apple');
		expect(ownedParam(page, 'query-1-category')).toBe('news');
	});

	test('keeps a parameter it does not own', async ({ page }) => {
		await page.goto(`${PAGES.filters.path}?utm_source=newsletter&lang=fr`);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(param(page, 'utm_source')).toBe('newsletter');
		expect(param(page, 'lang')).toBe('fr');
	});

	test('resets pagination when a filter changes', async ({ page }) => {
		await page.goto(`${PAGES.filters.path}?query-1-page=2`);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(param(page, 'query-1-page')).toBeNull();
	});

	test('filters an archive that paginates through its path', async ({
		page,
	}) => {
		const response = await page.goto('/category/news/page/2/');
		expect(response.status()).toBe(200);

		await page.getByRole('combobox', { name: 'Author' }).selectOption({
			label: 'Jane Doe',
		});
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(new URL(page.url()).pathname).toBe('/category/news/');
		expect(ownedParam(page, 'query-author')).toBe('jane-doe');
	});

	test('filters by a term whose slug is percent-encoded', async ({ page }) => {
		await page.goto(PAGES.filters.path);

		const option = page.getByRole('checkbox', { name: '新闻' }).first();
		const slug = await option.getAttribute('value');

		// WordPress stores a non-Latin slug percent-encoded; the filter must
		// carry those octets through untouched (roadmap #33).
		expect(slug).toMatch(/%[0-9a-f]{2}/);

		await option.check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(ownedParam(page, 'query-1-category')).toBe(slug);
		expect(await resultTitles(page)).toEqual(['Zucchini']);
	});
});
```

The percent-encoded **author** case belongs in the same file, written the same way against the author select. Add it.

- [ ] **Step 4: Run it**

```bash
npm run build && npx wp-scripts test-playwright tests/e2e/specs/no-js.spec.js
```

Every test must pass. If the "Apply with empty search box" test passes, **remove `novalidate` from `LoopForm::render()` and confirm it fails**, then restore — that is the only proof `novalidate` is doing anything.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e
git commit -m "test: Cover the filters without JavaScript"
```

---

### Task 7: `buildUrl()` and `sameQuery()`

**Files:**

- Create: `src/utils/build-url.js`
- Create: `tests/unit/utils/build-url.test.js`
- Modify: `src/blocks/query-filter/view.js` (remove `stripInheritedPagination` and re-export nothing)
- Modify: `tests/unit/blocks/query-filter/view.test.js` (move the `stripInheritedPagination` tests out)

**Interfaces:**

- Produces:

  - `buildUrl( href, { entries, ownedNames, pageKey, inherit, paginationBase } ): string`
  - `sameQuery( a, b ): boolean`
  - `stripInheritedPagination( pathname, paginationBase ): string` (moved verbatim from `view.js`, with its JSDoc)

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/utils/build-url.test.js`. Assert with `URLSearchParams`, never string matching — parameter order is not part of the contract.

```js
import { buildUrl, sameQuery } from '../../../src/utils/build-url';

const BASE = 'https://example.com/library/';

/**
 * Parameters of a built URL, as a plain object.
 *
 * @param {string} url URL.
 * @return {Object} Decoded parameters.
 */
const params = (url) => Object.fromEntries(new URL(url).searchParams.entries());

const options = (overrides = {}) => ({
	entries: [],
	ownedNames: ['query-3-category', 'query-3-sort'],
	pageKey: 'query-3-page',
	inherit: false,
	paginationBase: 'page',
	...overrides,
});

describe('buildUrl', () => {
	it('writes an owned control value', () => {
		const url = buildUrl(
			BASE,
			options({ entries: [['query-3-category', 'news']] })
		);

		expect(params(url)).toEqual({ 'query-3-category': 'news' });
	});

	it('joins repeated values of one name with commas', () => {
		const url = buildUrl(
			BASE,
			options({
				entries: [
					['query-3-category[]', 'news'],
					['query-3-category[]', 'events'],
				],
			})
		);

		expect(params(url)).toEqual({ 'query-3-category': 'news,events' });
	});

	it('drops an owned name with no value', () => {
		const url = buildUrl(
			`${BASE}?query-3-category=news`,
			options({ entries: [['query-3-category', '']] })
		);

		expect(params(url)).toEqual({});
	});

	it('drops a value that is only whitespace', () => {
		const url = buildUrl(
			BASE,
			options({
				ownedNames: ['query-3-s'],
				entries: [['query-3-s', '   ']],
			})
		);

		expect(params(url)).toEqual({});
	});

	it('trims the value it writes', () => {
		const url = buildUrl(
			BASE,
			options({
				ownedNames: ['query-3-s'],
				entries: [['query-3-s', '  cats  ']],
			})
		);

		expect(params(url)).toEqual({ 'query-3-s': 'cats' });
	});

	it('leaves a parameter the form does not own', () => {
		// Hidden inputs are submitted but never owned, so they pass through.
		const url = buildUrl(
			`${BASE}?utm_source=newsletter&s=cats`,
			options({
				entries: [
					['utm_source', 'newsletter'],
					['s', 'cats'],
				],
			})
		);

		expect(params(url)).toEqual({
			utm_source: 'newsletter',
			s: 'cats',
		});
	});

	it('resets the page key, page and cst', () => {
		const url = buildUrl(
			`${BASE}?query-3-page=2&page=4&cst=1&lang=fr`,
			options()
		);

		expect(params(url)).toEqual({ lang: 'fr' });
	});

	it('drops a fragment', () => {
		expect(buildUrl(`${BASE}#results`, options())).toBe(BASE);
	});

	it('strips an inherited pagination segment', () => {
		const url = buildUrl(
			'https://example.com/category/news/page/2/',
			options({
				pageKey: 'paged',
				inherit: true,
				ownedNames: ['query-category'],
			})
		);

		expect(new URL(url).pathname).toBe('/category/news/');
	});

	it('keeps the trailing-slash style when stripping pagination', () => {
		const url = buildUrl(
			'https://example.com/category/news/page/2',
			options({
				pageKey: 'paged',
				inherit: true,
				ownedNames: ['query-category'],
			})
		);

		expect(new URL(url).pathname).toBe('/category/news');
	});

	it('leaves a custom loop path alone', () => {
		const url = buildUrl('https://example.com/library/page/2/', options());

		expect(new URL(url).pathname).toBe('/library/page/2/');
	});

	it('handles a loop with no queryId', () => {
		const url = buildUrl(
			`${BASE}?query-page=3`,
			options({
				pageKey: 'query-page',
				ownedNames: ['query-0-category'],
				entries: [['query-0-category', 'news']],
			})
		);

		expect(params(url)).toEqual({ 'query-0-category': 'news' });
	});

	it('keeps a percent-encoded slug intact', () => {
		const url = buildUrl(
			BASE,
			options({ entries: [['query-3-category', '%e6%96%b0%e9%97%bb']] })
		);

		expect(params(url)).toEqual({
			'query-3-category': '%e6%96%b0%e9%97%bb',
		});
	});
});

describe('sameQuery', () => {
	it('treats a comma list and its encoded form as equal', () => {
		expect(
			sameQuery(
				`${BASE}?query-3-category=news,events`,
				`${BASE}?query-3-category=news%2Cevents`
			)
		).toBe(true);
	});

	it('treats repeated bracket names as one comma list', () => {
		expect(
			sameQuery(
				`${BASE}?query-3-category[]=news&query-3-category[]=events`,
				`${BASE}?query-3-category=news,events`
			)
		).toBe(true);
	});

	it('ignores parameter order', () => {
		expect(sameQuery(`${BASE}?a=1&b=2`, `${BASE}?b=2&a=1`)).toBe(true);
	});

	it('notices a different path', () => {
		expect(sameQuery(BASE, 'https://example.com/other/')).toBe(false);
	});

	it('notices a different value', () => {
		expect(
			sameQuery(
				`${BASE}?query-3-category=news`,
				`${BASE}?query-3-category=events`
			)
		).toBe(false);
	});

	it('notices an extra parameter', () => {
		expect(sameQuery(BASE, `${BASE}?a=1`)).toBe(false);
	});
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `npm test -- --testPathPattern=build-url`
Expected: FAIL — cannot resolve `src/utils/build-url`.

- [ ] **Step 3: Implement**

Create `src/utils/build-url.js`. Move `stripInheritedPagination` and its JSDoc across from `view.js` unchanged, then:

```js
// Names a filter change always clears, whichever way the form is submitted.
// PHP's LoopForm::reset_names() drops the same three from the hidden inputs;
// if the two lists disagreed, a no-JS submit would keep a page number that a
// JavaScript filter change resets.
const RESET_NAMES = ['page', 'cst'];

/**
 * The bare name of a control, without a trailing `[]`.
 *
 * @param {string} name Control name.
 * @return {string} Bare name.
 */
const bareName = (name) => name.replace(/\[\]$/, '');

/**
 * Build the URL a filter change should navigate to.
 *
 * The form is the source of truth: every name it owns is rewritten from the
 * submitted entries, and every other parameter in the current URL is left
 * exactly as it is (spec §6.1).
 *
 * @param {string}   href                   URL the change happened on.
 * @param {Object}   options
 * @param {Array}    options.entries        FormData entries, as [ name, value ] pairs.
 * @param {string[]} options.ownedNames     Bare names the form owns.
 * @param {string}   options.pageKey        The loop's page parameter.
 * @param {boolean}  options.inherit        Whether the loop inherits the main query.
 * @param {string}   options.paginationBase The rewrite's pagination base.
 * @return {string} The URL to navigate to.
 */
export const buildUrl = (
	href,
	{ entries, ownedNames, pageKey, inherit, paginationBase }
) => {
	const url = new URL(href);
	url.hash = '';

	[pageKey, ...RESET_NAMES].forEach((name) => url.searchParams.delete(name));
	ownedNames.forEach((name) => url.searchParams.delete(name));

	if (inherit) {
		url.pathname = stripInheritedPagination(
			url.pathname,
			paginationBase || 'page'
		);
	}

	const owned = new Set(ownedNames);
	const grouped = new Map();

	entries.forEach(([name, value]) => {
		const bare = bareName(name);
		const trimmed = typeof value === 'string' ? value.trim() : '';

		if (!owned.has(bare) || trimmed === '') {
			return;
		}

		grouped.set(bare, [...(grouped.get(bare) ?? []), trimmed]);
	});

	grouped.forEach((values, name) =>
		url.searchParams.set(name, values.join(','))
	);

	return url.href;
};

/**
 * Whether two URLs describe the same query.
 *
 * `a,b`, `a%2Cb` and `key[]=a&key[]=b` all mean the same thing to this
 * plugin, so a change that produces any of them from any other is not worth
 * a navigation.
 *
 * @param {string} a First URL.
 * @param {string} b Second URL.
 * @return {boolean} True when both name the same path and parameters.
 */
export const sameQuery = (a, b) => {
	const first = new URL(a, window.location.href);
	const second = new URL(b, window.location.href);

	if (first.pathname !== second.pathname) {
		return false;
	}

	return normalize(first.searchParams) === normalize(second.searchParams);
};

/**
 * A comparable form of a query string.
 *
 * @param {URLSearchParams} searchParams Parameters.
 * @return {string} Sorted, flattened parameters.
 */
const normalize = (searchParams) => {
	const flat = [];

	searchParams.forEach((value, name) => {
		value.split(',').forEach((part) => flat.push(`${bareName(name)}=${part}`));
	});

	return flat.sort().join('&');
};
```

- [ ] **Step 4: Run them and watch them pass**

Run: `npm test -- --testPathPattern=build-url`

- [ ] **Step 5: Move the pagination helper and its tests**

Move the `stripInheritedPagination` tests from `view.test.js` into `build-url.test.js`, and in `view.js` replace the function definition and its JSDoc with an import:

```js
import { stripInheritedPagination } from '../../utils/build-url';
```

**Keep that import until Task 9.** `updateFilters`, `handleSelect`, `handleSort` and `search` still call the function; deleting it here is a `ReferenceError` and roughly forty Jest failures in a window this plan declares green for Jest. Task 9 removes the import along with those four actions.

Run `npm test`: the count must not drop — the tests moved, they did not disappear.

- [ ] **Step 6: Mutate**

Remove `url.hash = ''` and confirm the fragment test fails. Remove `owned.has( bare )` and confirm the hidden-input test fails. Restore.

- [ ] **Step 7: Lint and commit**

```bash
npm run lint:js && npm test
git add src/utils/build-url.js tests/unit src/blocks/query-filter/view.js
git commit -m "feat: Add buildUrl and sameQuery"
```

---

### Task 8: Teach the Jest mock to run store actions

`view.js`'s debounce timer calls `store( 'pikari/gutenberg-query-filter' ).actions.navigate( url )`. In WordPress that works because the store proxy wraps every function it returns in `withScope()`, and `withScope()` of a generator function returns an async function that runs it to completion. The current mock returns the bare definition, so the call would return an unrun generator and every debounce test would pass vacuously.

This is monorepo roadmap **#30**. The change lands in this plugin's copy first; the monorepo session moves it into the template after running modals' 192 JS tests against it.

**Files:**

- Modify: `tests/unit/__mocks__/@wordpress/interactivity.js`
- Modify: `.github/plugin.json`
- Create: `tests/unit/interactivity-mock.test.js`

**Interfaces:**

- Produces: `withScope( fn )` runs generator functions and passes everything else through unchanged; one-argument `store( name )` returns the registered definition with its functions wrapped; `store.getStore( name )` keeps returning the **raw** definition, so existing tests can still step generators by hand.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/interactivity-mock.test.js` — **not** inside `__mocks__/`, which Jest treats specially and would not discover:

```js
/**
 * The mock's own contract, which several suites depend on.
 *
 * WordPress's store proxy wraps every function it returns in withScope(),
 * and withScope() of a generator runs it to completion. Code that calls a
 * store action from a timer relies on that; so do its tests.
 */
import { store, withScope } from '@wordpress/interactivity';

describe('@wordpress/interactivity mock', () => {
	it('passes a plain function through withScope unchanged', () => {
		// The modals plugin calls withScope with arrow functions.
		const fn = () => 'value';

		expect(withScope(fn)).toBe(fn);
	});

	it('runs a generator wrapped in withScope', async () => {
		const seen = [];
		const wrapped = withScope(function* (input) {
			seen.push(yield Promise.resolve(input));
			return 'done';
		});

		await expect(wrapped('a')).resolves.toBe('done');
		expect(seen).toEqual(['a']);
	});

	it('runs a generator action read back off the store', async () => {
		const ran = [];
		store('test/ns', {
			actions: {
				*go(value) {
					ran.push(yield Promise.resolve(value));
				},
			},
		});

		await store('test/ns').actions.go('x');

		expect(ran).toEqual(['x']);
	});

	it('still hands getStore the raw definition', () => {
		store('test/raw', { actions: { *go() {} } });

		expect(store.getStore('test/raw').actions.go().next).toBeInstanceOf(
			Function
		);
	});

	it('gives an unregistered namespace an empty state', () => {
		expect(store('core/router')).toEqual({ state: {} });
	});
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npm test -- --testPathPattern=interactivity-mock`
Expected: the generator tests fail — `withScope` currently returns the callback unchanged and `store( name )` gives `{ state: {} }`.

- [ ] **Step 3: Implement**

In `tests/unit/__mocks__/@wordpress/interactivity.js`, record every registration inside the `store()` implementation:

```js
const store = jest.fn((storeName, storeDefinition) => {
	if (storeDefinition) {
		registered.set(storeName, storeDefinition);
	}

	return storeDefinition ?? readStore(storeName);
});
```

`store.getLastStore()` and `store.getStore()` keep reading `store.mock.calls` and keep returning the **raw** definition, so existing tests can still step generators by hand. Then replace `withScope` and `readStore`:

```js
// Registered store definitions, by namespace. Derived from store.mock.calls
// this would not survive jest.clearAllMocks(), which the monorepo's own
// testing examples call in beforeEach — after which store( ns ).actions would
// be undefined and every timer-driven test would throw.
const registered = new Map();

const withScope = jest.fn((func) => {
	// WordPress returns non-generators untouched; the modals plugin passes
	// plain arrow functions through here.
	if (func?.constructor?.name !== 'GeneratorFunction') {
		return func;
	}

	return async (...args) => {
		const generator = func(...args);
		let result = generator.next();

		while (!result.done) {
			result = generator.next(await result.value);
		}

		return result.value;
	};
});

// WordPress's store proxy scopes every function it hands out, which is what
// makes `store( ns ).actions.go()` run a generator action.
const scopeHandlers = {
	get: (target, key) => {
		const result = Reflect.get(target, key);

		if (typeof result === 'function') {
			return withScope(result);
		}

		return result;
	},
};

function readStore(storeName) {
	const definition = registered.get(storeName);

	if (definition) {
		return new Proxy(definition, {
			get: (target, key) => {
				const result = Reflect.get(target, key);

				return result && typeof result === 'object'
					? new Proxy(result, scopeHandlers)
					: result;
			},
		});
	}

	if (!readStores.has(storeName)) {
		readStores.set(storeName, { state: {} });
	}

	return readStores.get(storeName);
}
```

- [ ] **Step 4: Run it and watch it pass**

Run: `npm test`
Expected: the mock's own tests pass and **all 44 existing Jest tests still pass** — the `core/router` path is untouched because that namespace is never registered with a definition.

- [ ] **Step 5: Protect it from the config sync**

Add the mock to `skip-sync` in `.github/plugin.json`:

```json
	"skip-sync": [".eslintrc.js", "tests/unit/__mocks__/@wordpress/interactivity.js"]
```

Confirm with `../.github/sync-all.sh --dry-run` that the file is no longer listed for this plugin. Record in the ledger that this entry comes out again once monorepo #30 lands the change in the template.

- [ ] **Step 6: Leave the mock's own spec in place**

Those five tests pin a contract two suites depend on, and they are what monorepo #30 runs against modals before the change goes into the template.

- [ ] **Step 7: Commit**

```bash
npm run lint:js && npm test
git add tests/unit/__mocks__ tests/unit/interactivity-mock.test.js .github/plugin.json
git commit -m "test: Run generator store actions in the interactivity mock"
```

---

### Task 9: Rewrite the store

This is the largest task and the one that turns the JavaScript specs green again.

**Files:**

- Rewrite: `src/blocks/query-filter/view.js`
- Rewrite: `tests/unit/blocks/query-filter/view.test.js`
- Modify: `CLAUDE.md`

**Interfaces:**

- Consumes: `buildUrl`, `sameQuery` from Task 7; the `data-query-*` attributes from Task 2; the control attributes from Tasks 4 and 5.
- Produces the store's public surface: `actions.change`, `actions.submit`, `actions.endBurst`, `actions.navigate`, `callbacks.restoreInjectedStyles`.

**Keep exactly as they are:** `injectedStyles`, `captureInjectedStyles()`, `enableInjectedStyles()`, the `popstate` listener and `callbacks.restoreInjectedStyles`. They fix roadmap #18 and #24 and are not in scope.

**Delete:** `updateFilters`, `handleSelect`, `handleSort`, `search`, and the `navigate` generator they shared.

- [ ] **Step 1: Write the failing tests**

Rewrite `tests/unit/blocks/query-filter/view.test.js`, keeping its injected-style tests verbatim and replacing the action tests. Build a real form in the JSDOM document so `FormData` and `form.elements` do the work:

```js
/**
 * Build a loop form and its controls in the document.
 *
 * @param {Object} options
 * @param {string} options.pageKey  Page parameter name.
 * @param {boolean} options.inherit Whether the loop inherits the main query.
 * @return {HTMLFormElement} The form.
 */
function addForm({ pageKey = 'query-3-page', inherit = false } = {}) {
	document.body.innerHTML = `
		<div data-wp-interactive="pikari/gutenberg-query-filter">
			<select name="query-3-category" form="f"><option value=""></option><option value="news">News</option></select>
			<input type="search" name="query-3-s" form="f" value="" />
			<form id="f" method="get" action="/library/"
				data-query-page-key="${pageKey}"
				data-query-inherit="${inherit}"
				data-query-pagination-base="page">
				<input type="hidden" name="lang" value="fr" />
			</form>
		</div>`;
	return document.getElementById('f');
}
```

The tests to write, one behaviour each. Use `jest.useFakeTimers()` and **`await jest.advanceTimersByTimeAsync( ms )`** — the synchronous `advanceTimersByTime` does not flush the promise chain the timer starts, and every one of these tests would pass without asserting anything.

- a select `change` navigates after 250 ms, and not before 249 ms;
- a search `input` navigates after 400 ms, not 250 ms;
- a second `change` inside the window replaces the first, and only one navigation happens, to the second URL;
- an `input` event with `isComposing: true` is ignored — no timer is started;
- a `compositionend` event is never ignored, even with a pending composition;
- a search `input` sets `context.searchValue` immediately, before any navigation;
- a `change` whose URL matches the current location does not navigate;
- a `change` back to the starting value **cancels** the pending navigation, so nothing happens at all — pick "News", then "All" 100 ms later, advance past 250 ms, assert the router was never called;
- a `change` whose URL matches the in-flight URL does not navigate;
- while a navigation is in flight, a `change` navigates **immediately**, with no timer;
- `submit` calls `preventDefault`, cancels the pending timer, and navigates once;
- `submit` is wrapped: `withSyncEvent` was called with the submit handler;
- the second search navigation in a burst passes `{ replace: true }`, and the first does not;
- `endBurst` makes the next search navigation push again;
- a navigation by a non-search control ends the burst;
- the URL is built from the form as it is when the event fires, not when the timer runs (change the select's value again after the event and assert the first URL is used);
- `navigate` re-enables injected styles after the router resolves.

The `withSyncEvent` assertion reads the mock: `expect( withSyncEvent ).toHaveBeenCalledWith( expect.any( Function ) )`. Pin it, because dropping the wrapper reintroduces roadmap #21's deprecation warning silently.

- [ ] **Step 2: Run them and watch them fail**

Run: `npm test -- --testPathPattern=view`
Expected: failures naming `actions.change` / `actions.submit` as undefined.

- [ ] **Step 3: Implement**

Rewrite `src/blocks/query-filter/view.js`. Keep the file's existing top section (the injected-styles comment block through the `popstate` listener) unchanged; `stripInheritedPagination` has already moved out in Task 7.

```js
import { store, getContext, withSyncEvent } from '@wordpress/interactivity';
import { buildUrl, sameQuery } from '../../utils/build-url';

const NAMESPACE = 'pikari/gutenberg-query-filter';

// A filter change is one deliberate act; a keystroke is one of many, so
// typing waits longer before it costs a request (spec §6.2).
const FILTER_DELAY = 250;
const SEARCH_DELAY = 400;

// Pending debounce timers, keyed by form id, so two loops on one page don't
// cancel each other.
const timers = new Map();

// The URL the router is currently fetching, if any.
let inFlightUrl = null;

// True between the first search navigation of a typing burst and its end, so
// a pause every few keystrokes doesn't fill the history with near-identical
// entries. A burst ends on blur or on submit.
let searchBurst = false;

/**
 * Everything buildUrl() needs, read from a loop form.
 *
 * form.elements is the browser's own answer to "what filters this loop?" —
 * controls join by their `form` attribute wherever they sit in the DOM.
 * Hidden inputs are submitted but never owned, so they pass through untouched.
 *
 * @param {HTMLFormElement} form The loop form.
 * @return {Object} buildUrl options.
 */
const formOptions = (form) => {
	const ownedNames = Array.from(form.elements)
		.filter((element) => element.name && element.type !== 'hidden')
		.map((element) => element.name.replace(/\[\]$/, ''));

	return {
		entries: [...new FormData(form)],
		ownedNames: [...new Set(ownedNames)],
		pageKey: form.dataset.queryPageKey,
		inherit: form.dataset.queryInherit === 'true',
		paginationBase: form.dataset.queryPaginationBase,
	};
};

/**
 * Cancel a form's pending navigation, if it has one.
 *
 * @param {string} formId Form id.
 */
const cancel = (formId) => {
	if (timers.has(formId)) {
		clearTimeout(timers.get(formId));
		timers.delete(formId);
	}
};

/**
 * Start the navigation, through the store so the generator actually runs.
 *
 * @param {string}  url      URL to navigate to.
 * @param {boolean} isSearch Whether a search keystroke triggered it.
 */
const run = (url, isSearch) => {
	const replace = isSearch && searchBurst;
	searchBurst = isSearch;

	// The store proxy scopes and runs the generator; a bare generator here
	// would never execute. It reads no context or element, so it needs no scope.
	store(NAMESPACE)
		.actions.navigate(url, replace)
		.catch(() => {});
};

store(NAMESPACE, {
	// Copy the existing `callbacks` object across verbatim, comment included.
	// restoreInjectedStyles fixes roadmap #18 and #24 and is not in scope.
	callbacks: {
		restoreInjectedStyles() {
			captureInjectedStyles();

			if (store('core/router').state.url) {
				enableInjectedStyles();
			}
		},
	},
	actions: {
		/**
		 * A control changed: work out where that points, and go there.
		 *
		 * @param {Event} event change, input or compositionend.
		 */
		change(event) {
			// Mid-composition keystrokes are not yet a search term.
			if ('input' === event.type && event.isComposing) {
				return;
			}

			const control = event.target;
			const isSearch =
				'input' === event.type || 'compositionend' === event.type;

			// Bound straight away, so the typed text survives the region
			// re-render that the navigation is about to cause.
			if (isSearch) {
				getContext().searchValue = control.value;
			}

			const form = document.getElementById(control.getAttribute('form'));
			if (!form) {
				return;
			}

			// Built now, from the DOM as it is at the event, not as it will
			// be when the timer fires.
			const url = buildUrl(window.location.href, formOptions(form));

			// Cancel first, and unconditionally: this URL describes the whole
			// form as it now stands, so any pending one is already stale. A
			// user who picks a category and puts it back within the window
			// must end up going nowhere, not at the category they abandoned.
			cancel(form.id);

			if (sameQuery(url, inFlightUrl ?? window.location.href)) {
				return;
			}

			// A request is already out: go now and let the router discard the
			// stale response, rather than leaving the newer choice waiting.
			if (inFlightUrl) {
				run(url, isSearch);
				return;
			}

			timers.set(
				form.id,
				setTimeout(
					() => {
						timers.delete(form.id);
						run(url, isSearch);
					},
					isSearch ? SEARCH_DELAY : FILTER_DELAY
				)
			);
		},

		// Submitted by an Apply button, by Enter in the search box, or by a
		// keyboard user activating a control. preventDefault() needs the
		// synchronous event (roadmap #21).
		submit: withSyncEvent((event) => {
			event.preventDefault();

			const form = event.target;
			cancel(form.id);
			searchBurst = false;

			run(buildUrl(window.location.href, formOptions(form)), false);
		}),

		/**
		 * End a typing burst, so the next search pushes a history entry.
		 */
		endBurst() {
			searchBurst = false;
		},

		/**
		 * Navigate, keeping script-injected styles alive across the swap.
		 *
		 * @param {string}  url     URL to navigate to.
		 * @param {boolean} replace Whether to replace the history entry.
		 */
		*navigate(url, replace) {
			captureInjectedStyles();
			inFlightUrl = url;

			try {
				const { actions } = yield import('@wordpress/interactivity-router');
				yield actions.navigate(url, replace ? { replace: true } : {});
				enableInjectedStyles();
			} finally {
				// Only if no newer navigation has taken over.
				if (inFlightUrl === url) {
					inFlightUrl = null;
				}
			}
		},
	},
});
```

- [ ] **Step 4: Run the tests and the suite**

Run: `npm test`
Expected: all Jest tests pass.

- [ ] **Step 5: Run the browser suite**

```bash
npm run build && npm run test:e2e
```

Expected: **every spec green again**, including the ones red since Task 4, plus Task 6's no-JS file. Any that stay red is a real defect — diagnose it, do not adjust the test.

- [ ] **Step 6: Mutate**

Delete `searchBurst = isSearch;` and confirm the `replace` test fails. Change `sameQuery( url, inFlightUrl ?? window.location.href )` to `sameQuery( url, window.location.href )` and confirm the in-flight test fails. Remove `withSyncEvent` and confirm its assertion fails. Restore all three.

- [ ] **Step 7: Update the plugin's `CLAUDE.md`**

Three places in "Query Filter render flow" and "Extension rules" are now wrong:

- Step 5 says `view.js` reads `input.value` on change and navigates. Rewrite it: every control carries `name` and `form`; `BlockFilters::render_block_query()` injects the loop form; `view.js` reads the form with `FormData` and `buildUrl()` rewrites only the names the form owns. Keep the existing paragraph about the router needing `data-wp-interactive` on the wrapper — it is still true and still the reason filtering works.
- The extension rule "Keep the `<input>` outside filterable markup. `view.js` depends on its `type`, `value`, `name`, and `data-wp-on--change` attributes" now also depends on `form`. Say so.
- "The Sort block has no radios or checkboxes, and none of these filters apply to it" is followed by B3 giving Sort a `name`, a `form` and a `<noscript>` button. Correct the sentence to say the three option filters don't apply, but the form contract does.

- [ ] **Step 8: Lint and commit**

```bash
npm run lint:all && composer test && npm test
git add src/blocks/query-filter/view.js tests/unit CLAUDE.md
git commit -m "feat: Drive every filter change from the loop form"
```

---

### Task 10: The remaining browser specs

Close out §8.4's "With JavaScript" and "Accessibility" lists. Several items are already covered by the 28 existing specs — **read them first and add only what is missing.** Duplicating a passing assertion costs runtime and hides which spec broke.

**Files:**

- Modify: `tests/e2e/specs/filters.spec.js`
- Create: `tests/e2e/specs/search.spec.js`

**Cases to have covered by the end of this task**, in one file or another:

- two categories checked give `category=a,b` and the matching results;
- the author select writes a nicename;
- sort in a Sort-only loop;
- `pressSequentially` typing is grouped into one navigation and the typed text survives;
- a change made while a response is delayed with `page.route` wins — for a select, a checkbox and a radio, asserting both the final URL **and** the rendered titles;
- a filter change resets pagination;
- the search template keeps `s` when the Search block sits outside the loop;
- a Search block **inside** an inherited loop resets `/page/N/` (the fixture from Task 6, roadmap #33). Note that `?s=` on `/category/news/` renders the **search** template, not the category one — `is_search` comes before `is_archive` in the template hierarchy — so assert on the path and on `s`, never on the archive heading;
- the sticky post is absent from filtered results;
- the button-only Search variant still expands on click and does not submit the loop form while collapsed;
- Enter on a checkbox navigates;
- a URL with a `#fragment` filters without a console error;
- Back and Forward restore results;
- an aria snapshot shows named groups and labelled selects;
- focus stays on the triggering control after a navigation.

Two rules that came out of B1 and B2, and cost a day between them:

- **Any assertion about content outside the Query wrapper needs a full `page.goto()`.** A filter click never re-renders it.
- **Name the expected titles.** Do not assert a count; a count passes for the wrong five posts.

- [ ] **Step 1: Read the existing specs and list the gaps in the ledger**
- [ ] **Step 2: Write the missing specs, watching each fail first where you can make it fail**
- [ ] **Step 3: Run the whole suite**

```bash
npm run build && npm run test:e2e
```

- [ ] **Step 4: Commit**

```bash
git add tests/e2e
git commit -m "test: Cover the remaining filter behaviour in a browser"
```

---

### Task 11: Visual baselines

**Files:**

- Create: `tests/e2e/specs/visual.spec.js`

Baselines are `-chromium-darwin.png` and are committed. Playwright does not run on CI (roadmap #28), so nothing regenerates them; when #28 lands, that job must either regenerate for Linux or skip this file. Put that sentence in the spec's header comment.

- [ ] **Step 1: Write the spec**

One screenshot per display type (select, radio, checkbox) and one for the horizontal layout, taken of the filter block's bounding box rather than the page, with `animations: 'disabled'`. Then the layout check that roadmap #17 established: measure each filter block's bounding box before a filter change and after, and assert they are equal — the `<form>` is injected as the last child specifically so it cannot shift flow-layout gaps.

- [ ] **Step 2: Generate and inspect the baselines**

```bash
npm run build && npx wp-scripts test-playwright tests/e2e/specs/visual.spec.js --update-snapshots
```

**Open each PNG and look at it.** A baseline generated from broken markup pins broken markup for ever.

- [ ] **Step 3: Re-run without `--update-snapshots` and confirm it passes**
- [ ] **Step 4: Commit**

```bash
git add tests/e2e
git commit -m "test: Add visual baselines for the filter display types"
```

---

### Task 12: Translations

**Files:**

- Modify: `languages/pikari-gutenberg-query-filter.pot`
- Modify: `languages/pikari-gutenberg-query-filter-fr_CA.po`
- Modify: `languages/*.mo`, `languages/*.json`

The `.pot` is **stale** — it has "All" but not "Default", which the Sort block has used since 0.3.x. Regenerating it will surface every string added since, not just B3's "Apply filters". Translate all of them.

- [ ] **Step 1: Start wp-env** (`npm run wp-env`) — every i18n script runs through it.
- [ ] **Step 2: Regenerate the template**

```bash
npm run i18n:pot
git diff languages/pikari-gutenberg-query-filter.pot
```

Confirm the diff contains at least `Apply filters` and `Default`, and note every other new entry.

- [ ] **Step 3: Merge and translate**

```bash
msgmerge --update languages/pikari-gutenberg-query-filter-fr_CA.po languages/pikari-gutenberg-query-filter.pot
```

Translate every new `msgid` and resolve every `#, fuzzy` entry the merge produced. "Apply filters" is `Appliquer les filtres`; "Default" in the Sort block's sense (the loop's own order) is `Par défaut`.

- [ ] **Step 4: Compile**

```bash
npm run i18n:mo && npm run i18n:json
```

- [ ] **Step 5: Check it in a browser**

Set the site language to French in wp-env, load a filtered page with JavaScript off, and confirm the Apply button reads `Appliquer les filtres`.

- [ ] **Step 6: Commit**

```bash
git add languages
git commit -m "chore: Update translations for the form-based filters"
```

---

### Task 13: Changelog, README and the roadmap amendment

**Files:**

- Modify: `CHANGELOG.md`
- Modify: `README.md`
- Modify: `_log/roadmap.md` **in the monorepo repository**, as its own commit

- [ ] **Step 1: Write the breaking-changes section**

`CHANGELOG.md` gets a "Breaking changes" section under the unreleased 1.0.0 heading, listing everything in spec §3.6 that B1–B3 changed, plus B3's own: the radio group's `name` is now the query parameter; the Sort block emits a `name` and a `form`; core's Search form is no longer modified, only its input and button; the Search block's `data-wp-context` moved from the form to the input; `wp_interactivity_state( 'searchValue' )` is gone.

- [ ] **Step 2: Update `README.md`**

Say that the filters work without JavaScript, and that a page-level cache must include `query-*`, `s` and `paged` in its cache key (spec §7) — a cache that strips tracking parameters can otherwise serve one visitor's hidden inputs to another.

- [ ] **Step 3: Amend roadmap #33**

Two of its three items are now covered by fixtures. The third — the stale-object-cache path — **cannot** be covered here: wp-env has no persistent object cache. Rewrite #33 to say that, so it is a known limitation rather than a dropped task, and strike the two that are done.

- [ ] **Step 4: Commit**

```bash
git add CHANGELOG.md README.md
git commit -m "docs: Record the 1.0 breaking changes"
```

`_log/roadmap.md` is in the **monorepo's** repository, not this one, so it cannot ride in this commit. Make the #33 amendment a separate commit from `/Users/steveariss/Sites/pikari/wordpress-plugins`, and say in the ledger that you did.

---

## Before opening the pull request

- [ ] `npm run lint:all && composer test && npm test && npm run build && npm run test:e2e` — all green, and note the suite counts.
- [ ] A hands-on browser pass on `http://localhost:5884`: a custom loop, an archive, and the search template; with JavaScript on and off; with the keyboard only. Screenshot anything that looks different from before.
- [ ] Check the two real sites' dependencies by eye (spec §8.5): Kindler targets `__radio-group`, `__radio-item` and `.category_*`, none of which B3 renames; CCLF has no markup dependency but see §10.3.
- [ ] PR description: what changed, the suite counts, the note that Playwright does not run on CI so the no-JS and browser behaviour is verified locally only, and that the mock now diverges from the template pending monorepo #30.
- [ ] **Label the PR `breaking` by hand.**
- [ ] Do not publish a release. 1.0.0 ships in sub-project E (todo #529).
