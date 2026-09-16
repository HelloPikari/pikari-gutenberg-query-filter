# Query Filter 1.0 — URL Contract, Inherited Queries, and Form-Based Filters

- **Status:** Approved in conversation 2026-09-16. Awaiting review of this written spec.
- **Scope:** Sub-project B of the 1.0 effort. It ships as one PR on `feature/url-contract-forms`, labelled `breaking` by hand, and the release draft resolves to 1.0.0.
- **Source:** The UX/DX review of 0.3.3 (todo #522) and the design discussion that followed.

## 1. Why

1.0 freezes this plugin's public contract: URL parameters, markup and classes, block attributes, and PHP hooks. Three things in that contract need to change first.

1. **Filters do nothing in inherited Query Loops.**
   - This covers archive and search templates, where the loop has "Inherit query from template" set.
   - Core applies `query_loop_block_query_vars` only to custom loops (`wp-includes/blocks/post-template.php`, WP 7.1). The plugin has no `pre_get_posts` fallback.
   - Taxonomy, post type, author and sort parameters are written to the URL and never read. Only core Search works there, because it uses `s`.
   - The README says inherited loops are supported.
2. **Filters don't work without JavaScript.**
   - Controls sit outside any `<form>`, and radios get a random UUID `name`.
   - The README says the plugin degrades gracefully. It doesn't.
3. **The URL format has gaps that can't be closed after 1.0:**
   - authors are identified by numeric ID;
   - sort uses two parameters, which no native form control can submit;
   - `orderby` is passed to `WP_Query` without validation.

## 2. Decisions

| Decision                               | Choice                                                                                 |
| -------------------------------------- | -------------------------------------------------------------------------------------- |
| Inherited loops                        | Supported: the same parameters are applied to the main query through `pre_get_posts`.  |
| Parameter naming                       | Keep the current core-style names (`query-3-{key}`).                                   |
| Author values                          | User nicename (`jane-doe`). Numeric IDs are still accepted, so old links keep working. |
| Sort parameter                         | One key: `query-3-sort=title-asc`. It looks up an option in a filterable list.         |
| Form structure                         | One hidden `<form>` per Query Loop. Controls join it with the HTML `form` attribute.   |
| Parameters no block on the page offers | A valid parameter still applies, the same way core treats `?category_name=`.           |
| No-JS submit control                   | A `<noscript>` "Apply filters" button in each Query Filter and Sort block. See §5.4.   |

## 3. URL contract

This section becomes a public section of `docs/hooks.md`.

### 3.1 Parameter names

| Filter    | Custom loop (queryId 3)          | Inherited loop                 |
| --------- | -------------------------------- | ------------------------------ |
| Post type | `query-3-post_type=post,page`    | `query-post_type=post,page`    |
| Taxonomy  | `query-3-{taxonomy}=news,events` | `query-{taxonomy}=news,events` |
| Author    | `query-3-author=jane-doe,sam`    | `query-author=jane-doe,sam`    |
| Sort      | `query-3-sort=title-asc`         | `query-sort=title-asc`         |
| Search    | `query-3-s=term`                 | `s=term` (core)                |
| Page      | `query-3-page=2` (core)          | `/page/2/` or `paged=2` (core) |

### 3.2 Values

- **Multiple values:** a comma-separated list is the canonical form, and the JavaScript always writes it. Without JavaScript, a form submit sends the repeated `key[]=value` form instead. The server reads both the same way.
- **Empty values** (`query-3-category=`) are ignored.
- **Taxonomy values** are term slugs, cleaned with `sanitize_title()`.
- **Post type values** are post type names. Each must exist and be viewable (`is_post_type_viewable()`).
- **Taxonomy keys** must name a taxonomy that exists and is `public`. Any other key is ignored.
- **Author values** resolve to users in this order:

  1. `get_user_by( 'slug', $value )`;
  2. if that finds no one and the value is all digits, `get_user_by( 'id', $value )`.

  Values that match no user are ignored. If none match, no author filter applies.

- **Sort values** must be a key in `pikari_gutenberg_query_filter_sort_options` (§4.3). Unknown keys are ignored.

### 3.3 Which parameters a loop owns

A loop owns every parameter with its prefix. Changing any filter replaces that loop's parameters with the form's current values and removes pagination. Every other parameter is kept.

- **Custom loop:** every parameter whose name starts with `query-{queryId}-`. That includes core's `query-{queryId}-page`.
- **Inherited loop:**
  - every parameter matching `^query-(?!\d+-)`;
  - `s` and `paged`;
  - a `/{pagination_base}/{n}/` segment at the end of the path.

The same rule is applied twice: in PHP, to build the no-JS form, and in `buildUrl()`, for JavaScript navigation. Both must produce equivalent URLs.

### 3.4 Reserved keys

A taxonomy named `post_type`, `author`, `sort`, `s` or `page` would collide with a built-in key, so it can't be filtered. This is documented and not worked around.

### 3.5 Breaking changes from 0.3.x

- `query-3-orderby` / `query-3-order` are no longer read. Old sort links show the loop's default order.
- Author links with numeric IDs still work, but new links use nicenames.

## 4. Server architecture

### 4.1 New classes

These replace `Core\QueryLoopHandler`, the three config methods in `Helpers\AbstractQueryHelper` (`get_query_config`, `get_taxonomy_query_config`, `get_sort_query_config`), and the parameter-naming code duplicated in `Integrations\BlockFilters::render_block_search()`.

| Class                          | Responsibility                                                                                                                                                                                                                                                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Url\QueryParams`              | Parameter names for one loop. Built from `( ?int $query_id, bool $inherit )`. Provides `key( $name )` for `post_type`, `author`, `sort`, `s` and taxonomy names, `page_key()`, `owns( $param_name )`, and `strip_pagination( $url )`. The only place parameter names are defined.                                  |
| `Url\FilterState`              | Parsed, validated values. `FilterState::from_request( array $get, QueryParams $params )` returns an object with `post_types`, `taxonomies` (taxonomy ⇒ slugs), `author_ids`, `search`, and `sort` (the resolved option array, or null). It reads string and array input alike, and applies the validation in §3.2. |
| `Query\QueryArgs`              | `QueryArgs::apply( array $args, FilterState $state ): array` is a pure merge; see §4.2.                                                                                                                                                                                                                            |
| `Query\SortOptions`            | `SortOptions::all(): array` returns the filtered option list. `SortOptions::find( string $key ): ?array` looks up one option. `SortOptions::match( string $orderby, string $order ): ?array` finds the option for a loop's own order.                                                                              |
| `Integrations\MainQueryFilter` | Hooks `pre_get_posts` for inherited loops; see §4.4.                                                                                                                                                                                                                                                               |

- `Core\QueryLoopHandler` keeps the `query_loop_block_query_vars` hook at priority 19. It becomes a thin adapter: build `QueryParams` from block context, then `FilterState::from_request( $_GET, … )`, then `QueryArgs::apply`.
- Class names follow the existing PSR-4 layout under `includes/`. Run `composer dump-autoload` after adding them.

### 4.2 `QueryArgs::apply`

These rules match 0.3.3's behaviour for custom loops, except sort and author:

- **Post types:** override `post_type`. Set `ignore_sticky_posts` if the loop hasn't set it, and unset `post__in`.
- **Taxonomies:** one `IN` clause per taxonomy, by slug. Two or more clauses use `relation => AND`. They are ANDed with any `tax_query` the loop already has.
- **Authors:** set `author__in` to the resolved IDs.
- **Search:** set `s` (custom loops only; inherited loops use core's `s`).
- **Sort:** set `orderby` and `order` from the option. Set `meta_key` if the option has one.

### 4.3 Sort options

```php
apply_filters( 'pikari_gutenberg_query_filter_sort_options', array(
    array(
        'key'     => 'date-desc',
        'label'   => __( 'Date (Newest First)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'date',
        'order'   => 'DESC',
    ),
    // date-asc, title-asc, title-desc follow the same shape.
), $attributes );
```

- `$attributes` is the Sort block's attributes. It is an empty array when the list is read while building a query.
- Options may add `meta_key`. `key` is passed through `sanitize_key()`. Options with no `key`, `label` or `orderby` are dropped.
- The list is also the allowlist: an `orderby` that isn't in an option can't reach `WP_Query`.
- **Sort block pre-selection:**
  - With a valid sort parameter, that option is selected.
  - Without one, `SortOptions::match()` compares the loop's own `query.orderBy` / `query.order` from block context. If an option matches, it is selected and no empty option is rendered.
  - If nothing matches, an empty option labelled with `emptyLabel` (default "Default") is rendered and selected.
- The Sort block's `render.php` renders options from `SortOptions::all()` and no longer defines its own list.

### 4.4 Inherited loops: `MainQueryFilter`

- **The hook:** `pre_get_posts`, priority 10.
- **When it runs:** it returns immediately unless all of these are true:

  - `$query->is_main_query()`;
  - `! is_admin()`;
  - it isn't a REST or feed request;
  - some key in `$_GET` matches `^query-(?!\d+-)`.

  That last check is a single loop over `$_GET` keys, so requests without filter parameters never call `get_taxonomies()`.

- **What it does:**
  - builds `QueryParams( null, true )` and `FilterState`;
  - applies each non-empty part with `$query->set()`, using the same rules as `QueryArgs::apply`;
  - merges `tax_query` with the query's existing `tax_query` var. Core still adds archive query vars such as `category_name` in `parse_tax_query()`.
- **What it leaves alone:** `s` belongs to core.
- **Zero results:** filtering a matched archive or a search down to nothing doesn't 404, because core doesn't 404 a matched archive or a search. Requesting a page past the end still 404s, which is why the JavaScript strips `/page/N/` (§6.1).

### 4.5 Option lists

- `FilterHelper::get_filter_options()`: an author option's `value` becomes `user_nicename`.
- `FilterHelper::get_filter_post_types()`: in inherited loops, the post types come from `$wp_query->get( 'post_type' )`.
  - If that is `any` or empty on a search, use public post types that aren't excluded from search.
  - Remove `attachment` unless attachment pages are enabled.
  - This replaces the read of `query-filter-post_type`, which nothing sets.
- The config helpers used by `render.php` and `render_block_search()` are rewritten on top of `QueryParams`.

## 5. Markup

### 5.1 The loop form

The first Query Filter or Sort block to render in a Query Loop prints the form as the first child inside its own wrapper:

```html
<form
	hidden
	id="pikari-gutenberg-query-filter-form-3"
	class="wp-block-pikari-gutenberg-query-filter__form"
	method="get"
	action="/resources/library/"
	data-wp-on--submit="actions.submit"
	data-query-prefix="query-3-"
	data-query-inherit="false"
>
	<input type="hidden" name="lang" value="fr" />
</form>
```

- **`hidden`:** keeps the form out of layout, including block-gap spacing. Controls outside a hidden form still submit with it.
- **`action`:** the current request path with the pagination segment removed. A query string is useless here, because a GET form replaces it.
- **Hidden inputs:** one for each current `$_GET` parameter the loop doesn't own (§3.3), such as `lang`, UTM tags, other loops' filters, and `p` / `page_id` / `cat` on plain permalinks. Scalar values and one level of array values are carried; deeper arrays are dropped.
- **Printing once:** a static per-loop flag, reset in the existing `render_block_data` hook when a `core/query` block starts rendering, ensures each Query Loop prints its form once. A block that returns early (no options) prints nothing, and the next block prints the form instead.
- **Building it:** the form's markup, action and hidden inputs come from a `FormHelper` class. `render.php` only calls it, following the extension rules in `CLAUDE.md`.

### 5.2 Controls

Every control gets `form="pikari-gutenberg-query-filter-form-{queryId}"` (`…-form-inherit` for inherited loops) and a real `name`:

| Control         | `name`                                        |
| --------------- | --------------------------------------------- |
| Select filter   | `query-3-category`                            |
| Radio filter    | `query-3-category` (replaces the random UUID) |
| Checkbox filter | `query-3-category[]`                          |
| Sort select     | `query-3-sort`                                |

- **Events:**
  - Controls keep `data-wp-on--change`, now `actions.change`.
  - The radio "All" option keeps `value=""`.
  - Selects keep the empty first option.
- **Unchanged:** the classes, the `<fieldset>` / `<legend>` / `<label>` structure, the per-option `{key}_{slug}` classes, and the three existing PHP filters.
- **Context:** `data-wp-context` no longer carries `queryVar` / `pageVar`. The form's `data-query-*` attributes carry ownership instead.

### 5.3 Core Search

- **In a loop that has a form** (detected by finding the form's `id` in the rendered HTML):
  - `BlockFilters::render_block_query()` post-processes the rendered Query HTML with `WP_HTML_Tag_Processor`.
  - Search inputs whose `name` this loop owns get `form="…-form-{queryId}"`, and so do the submit buttons in the same Search block.
  - Doing this after all inner blocks have rendered means render order doesn't matter: Search can come before or after the filters.
  - Pressing Enter in Search then submits search and filters together.
  - The Search input's `data-wp-on--input` becomes `actions.change` (§6.2).
- **In a loop with Search but no filter blocks:** the core Search form keeps today's behaviour. `render_block_search()` still sets its `name`, value and `data-wp-on--submit`, and now also sets the `data-query-*` ownership attributes.
- **Scoping:** nested loops are safe, because each loop only matches inputs with its own parameter names.

### 5.4 No-JS submit button

Each Query Filter and Sort block ends its wrapper with:

```html
<noscript>
	<button
		type="submit"
		form="pikari-gutenberg-query-filter-form-3"
		class="wp-block-pikari-gutenberg-query-filter__submit"
	>
		Apply filters
	</button>
</noscript>
```

- **Why this changed from the in-chat design (one button, hidden by JS):**
  - A visible button hidden on hydration flashes and shifts layout on every JavaScript page load.
  - "The last block in the loop" isn't knowable while blocks are still rendering.
  - `<noscript>` renders only when JavaScript is off, so it costs JavaScript users nothing.
- **The cost:** a loop with four filters shows four buttons without JavaScript. Each one submits every filter.
- **Also:** the label is translatable. There is no button when JavaScript is on but the view module fails to load; Search still submits with Enter.

## 6. Frontend JavaScript

### 6.1 `buildUrl()`

A pure function in `src/utils/build-url.js`:

```js
buildUrl( location, entries, { prefix, inherit, paginationBase } ) => string
```

1. Start from `location` (`URL`).
2. Delete every search parameter the loop owns (§3.3). For an inherited loop, `prefix` is `query-`, and ownership uses the `^query-(?!\d+-)` rule plus `s` and `paged`.
3. If `inherit`, remove a trailing `/{paginationBase}/{n}/` from `pathname`.
4. Group `entries` (from `FormData`) by name, removing a trailing `[]`. Skip empty values. Write each group as one comma-joined parameter.
5. Return `href`.

The form carries `paginationBase` as `data-query-pagination-base`, taken from `$wp_rewrite->pagination_base`.

### 6.2 Store `pikari/gutenberg-query-filter`

- **`actions.change`:** for select, radio, checkbox and sort changes, and search `input` events.
  - Reads `getElement().ref.form` and schedules a navigation.
  - A pending navigation for the same form is replaced, so a burst of changes becomes one request.
  - The delay is 250 ms, or 400 ms when the event comes from a search input.
- **`actions.submit`:** for the form's `submit` event.
  - Wrapped in `withSyncEvent()`; calls `event.preventDefault()`.
  - Cancels any pending navigation and navigates immediately.
  - This closes roadmap #21, because it is the only action that calls `preventDefault()`.
- **Navigation:** `buildUrl( window.location, new FormData( form ), ownership )`, then the existing `navigate()` generator, which calls the router and restores injected styles.
  - If the new URL equals the current one, do nothing.
- **While typing in Search:** the router re-renders the region with the value the server saw, which can be older than what's in the box.
  - If a search input in the region has focus when navigation finishes, its current value and caret position are put back.
  - This replaces the `data-wp-bind--value="context.searchValue"` binding.
- **Removed:** `actions.updateFilters`, `actions.handleSelect`, `actions.handleSort` and `actions.search`.
- **Kept:**
  - `callbacks.restoreInjectedStyles`;
  - the capture of injected styles at hydration;
  - the `popstate` listener.

### 6.3 Sort block script

The Sort block's `block.json` points `viewScriptModule` at the shared view module. Sub-project A fixes the handle; this spec relies on that fix.

## 7. Edge cases

| Case                                                         | Behaviour                                                                                          |
| ------------------------------------------------------------ | -------------------------------------------------------------------------------------------------- |
| Two Query Loops on a page                                    | Separate prefixes, forms and ownership. Changing one keeps the other's parameters.                 |
| Nested Query Loops                                           | Each prints its own form. Search `form` attributes are scoped by parameter name (§5.3).            |
| Two loops with the same `queryId`                            | Unsupported, as in core (their pagination already collides). Documented.                           |
| Two blocks filtering the same taxonomy in one loop           | They share a parameter, and radios share a group. Documented.                                      |
| Invalid or unknown parameter values                          | Ignored (§3.2).                                                                                    |
| Non-existent term slug                                       | Its `IN` clause matches no posts, as in 0.3.3.                                                     |
| Plain permalinks                                             | `p`, `page_id`, `cat`, etc. become hidden inputs. `paged` is owned by inherited loops and dropped. |
| Filtering from `/page/3/` of an archive                      | JavaScript strips the path segment, and the no-JS form's `action` has none.                        |
| Filter or Sort block inside a synced pattern within the loop | Renders in the loop's context and prints or joins the form as usual.                               |
| Old `query-3-orderby` links                                  | Ignored: the loop's default order applies.                                                         |

## 8. Testing

Test-first, following the plugin's `CLAUDE.md`.

### 8.1 PHP (Brain\Monkey)

1. **Characterization first.** Write `QueryLoopHandlerTest` against 0.3.3 behaviour before anything changes:

   - post type validation;
   - tax_query merging and AND relation;
   - author IDs;
   - search;
   - order validation.

   Then port these assertions to `QueryArgsTest` / `FilterStateTest`. Change only the behaviour this spec changes (sort, author slugs).

2. **Unit tests:**
   - `QueryParams`: names, ownership, pagination stripping;
   - `FilterState`: string and array input, and every validation rule;
   - `QueryArgs`;
   - `SortOptions`: filter applied, invalid options dropped, `match()`;
   - `FormHelper`: hidden inputs exclude owned parameters, array values, action without pagination;
   - `MainQueryFilter`: the gating conditions, and `set()` calls for each part.
3. **Hooks:** every new hook ships with a `Filters\expectApplied` test and a section in `docs/hooks.md`.
4. **`BlockFiltersTest`:** the Search `form` attribute post-processing, including the nested-loop scoping. It needs a real `WP_HTML_Tag_Processor`, so it stays skipped on CI (roadmap #15).

### 8.2 Jest

- **`build-url.test.js`:**
  - ownership for custom and inherited loops;
  - pagination path stripping;
  - joining `[]` values;
  - empty values;
  - unrelated parameters kept;
  - another loop's parameters kept.
- **`view.test.js`:**
  - `change` grouping and delays, using fake timers;
  - `submit` cancels pending navigation and calls `preventDefault`;
  - no navigation to the current URL;
  - search value preservation.

### 8.3 Playwright

- **Setup:**
  - `test:e2e` moves to `wp-scripts test-playwright`, adding `@playwright/test` and `@wordpress/e2e-test-utils-playwright`.
  - Tests run against the wp-env tests instance (port 5885).
  - A global setup creates posts across two categories and two authors, a page with a custom loop (taxonomy checkbox, author select, sort, search), and a category archive template with the same blocks in an inherited loop, through the templates REST endpoint.
- **With JavaScript:**
  - Checking two categories updates the results and writes `query-N-category=a,b`.
  - Author select writes a nicename.
  - Sort works when it is the only block in a loop.
  - Search coalesces typing into one request and keeps the typed text.
  - Filter changes reset `query-N-page`.
  - On the archive, filtering from `/page/2/` lands on page 1 without a 404.
  - Back and Forward restore results.
  - An injected style stays enabled after filtering and after paginating. The global setup recreates session 6's fixture: a Custom HTML block whose inline script adds an id-less `<style>`.
- **Without JavaScript** (`javaScriptEnabled: false`):
  - The Apply button is visible.
  - Submitting applies every filter.
  - The URL uses `key[]=` and the server honours it.
  - Enter in Search submits the filters too.
- **Accessibility:** the aria snapshot shows every radio and checkbox group named, and every select labelled.
- **Visual:**
  - `toHaveScreenshot` covers each display type, and horizontal layout, with JavaScript on.
  - These run locally only; baselines depend on the OS.
  - CI e2e needs the shared `ci.yml` template and is out of scope (roadmap #23).

### 8.4 Hands-on and real sites

- **Playwright MCP, before and after this change:**
  - front end, editor, and a keyboard-only pass;
  - measured bounding boxes for filter blocks, which must not move with JavaScript on;
  - screenshots of anything that differs.
- **Before 1.0.0 is published:** Kindler's Insights page and CCLF's Resource Library, locally. CCLF needs the plugin activated first.

## 9. Documentation and release

- **`docs/hooks.md`, in the same PR:**
  - the URL contract (§3);
  - markup with the form, `name` and `form` attributes, and the `__form` / `__submit` classes;
  - the `sort_options` filter;
  - author option `value` is now a nicename;
  - the edge cases a theme author would hit.
- **Plugin `CLAUDE.md`:** rewrite "Query Filter render flow" step 5 and the extension rules that mention `input.value` and `queryVar`.
- **`CHANGELOG.md`:** add a "Breaking changes" entry for:
  - sort links;
  - author link format;
  - control `name`s and the new `form` attribute;
  - the hidden form as the wrapper's first child, which themes using `:first-child` should check;
  - the `<noscript>` button;
  - the removed store actions.
- **The PR:** labelled `breaking` by hand. Merging it doesn't publish anything; 1.0.0 ships when the release is published after sub-projects C–E.

## 10. Out of scope

| Item                                                                                                                                                                                | Where it goes               |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------- |
| Sort block script and stylesheet handles, `@view-transition` removal, `block.json` version from the plugin constant, dead code, README / Plugin URI / CHANGELOG corrections         | Sub-project A (lands first) |
| Sort block inspector cleanup, label semantics, choosing post types, taxonomy term controls, inserter `example`, pattern                                                             | Sub-project C               |
| Loading state, clear all, JS navigation event, author helper fixes (`can`, `post`-only count, cache on external object caches), adding router attributes only to loops with filters | Sub-project D               |
| README rewrite, `readme.txt`, 1.0 release                                                                                                                                           | Sub-project E               |
| Per-block custom URL keys, "Apply" mode, filters outside the loop, option counts, hierarchical terms, chip styles                                                                   | 1.x                         |
