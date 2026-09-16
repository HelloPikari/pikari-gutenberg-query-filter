# Query Filter 1.0 — URL Contract, Inherited Queries, and Form-Based Filters

- **Status:** Revision 2, awaiting Steve's review.
  - Revision 1 was approved in conversation on 2026-09-16.
  - Three independent reviews followed: server side, frontend, and process. Their findings were verified against WP 7.1 core, the CCLF site and browser tests before being applied here.
  - Revision 2 changes the design in seven places; §11 lists them.
- **Scope:** sub-project B of the 1.0 effort, delivered as four PRs (§10).
- **Sources:** the UX/DX review of 0.3.3 (todo #522), the design discussion, and the review findings.

## 1. Why

1.0 freezes this plugin's public contract: URL parameters, markup and classes, block attributes, and PHP hooks. Several things in that contract need to change first.

1. **Filters do nothing in inherited Query Loops** (archive and search templates).
   - Core applies `query_loop_block_query_vars` only to custom loops (`wp-includes/blocks/post-template.php:55-73`, WP 7.1).
   - The plugin has no main-query fallback, so taxonomy, post type, author and sort parameters are written to the URL and never read.
   - The README says inherited loops are supported.
2. **Filters don't work without JavaScript.** Controls sit outside any `<form>`, and radios get a random UUID `name`.
3. **The URL format has gaps that can't be closed after 1.0:**
   - authors are identified by numeric ID;
   - sort uses two parameters, which no native control can submit;
   - `orderby` isn't restricted to the options the Sort block offers.
4. **Existing bugs the new query code must not carry over:**
   - sticky posts are added to filtered results even when they don't match;
   - any taxonomy filter forces `relation => AND` onto the loop's own `tax_query`;
   - `render_block_search()` overwrites core Search's `data-wp-interactive` and `data-wp-context`, which breaks the button-only Search variant.

## 2. Decisions

| Decision                         | Choice                                                                                                                |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Inherited loops                  | Supported on home, archive and search requests (§4.4).                                                                |
| Parameter naming                 | Keep the current core-style names (`query-3-{key}`).                                                                  |
| Author values                    | User nicename. Numeric IDs are still accepted.                                                                        |
| Sort parameter                   | One key, `query-3-sort=title-asc`, looked up in a filterable list.                                                    |
| Form structure                   | One hidden `<form>` per Query Loop, injected after the loop renders. Controls join it with the HTML `form` attribute. |
| Which parameters a loop replaces | Its pagination, plus the names of the controls in its form (§3.3).                                                    |
| No-JS submit control             | A `<noscript>` "Apply filters" button in each Query Filter and Sort block.                                            |
| Delivery                         | Four PRs, B0–B3 (§10).                                                                                                |

## 3. URL contract

This section becomes public, in `docs/hooks.md`.

### 3.1 Parameter names

| Filter    | Custom loop (queryId 3)          | Inherited loop                 |
| --------- | -------------------------------- | ------------------------------ |
| Post type | `query-3-post_type=post,page`    | `query-post_type=post,page`    |
| Taxonomy  | `query-3-{taxonomy}=news,events` | `query-{taxonomy}=news,events` |
| Author    | `query-3-author=jane-doe,sam`    | `query-author=jane-doe,sam`    |
| Sort      | `query-3-sort=title-asc`         | `query-sort=title-asc`         |
| Search    | `query-3-s=term`                 | `s=term` (core)                |
| Page      | `query-3-page=2` (core)          | `/page/2/` or `paged=2` (core) |

A custom loop with no `queryId`, which includes every loop in Twenty Twenty-Five, uses the prefix `query-0-`. Its page key is `query-page`, because that is what core uses (`post-template.php:50`).

### 3.2 Values

- **Multiple values:**
  - A comma-separated list is canonical, and JavaScript always writes it.
  - Without JavaScript, a form submit sends the repeated `key[]=value` form. The server reads both the same way.
  - Values are de-duplicated and capped at 50 per key; anything after the 50th is ignored.
- **Empty values** are ignored.
- **Taxonomy keys** must name a taxonomy for which `is_taxonomy_viewable()` is true. That matches what the editor offers (`visibility.publicly_queryable`) and what core's Query block uses.
- **Taxonomy values** are term slugs, cleaned with `sanitize_title_for_query()`. A slug that matches no term returns no results for that taxonomy.
- **Post type values** must satisfy `is_post_type_viewable()`. `attachment` is also rejected unless attachment pages are enabled, the same rule the option list uses.
- **Author values:**
  - They resolve in at most two `get_users()` calls, limited to members of the current site. `WP_User_Query` ANDs `nicename__in` with `include`, so the two can't share a call.
    1. `nicename__in` for every value, with `fields => array( 'ID', 'user_nicename' )`.
    2. `include` for the all-digit values that didn't match a nicename.
  - A nicename match wins over an ID match.
  - If the parameter is present but resolves to no user, the filter returns no results (`author__in => array( 0 )`). This matches unknown term slugs and avoids revealing whether a user exists.
- **Sort values** must be a key in `pikari_gutenberg_query_filter_sort_options` (§4.3). An empty value, or an unknown key, means the loop's default order.

### 3.3 What a filter change replaces

A loop **owns**:

1. **Its pagination:**
   - `query-{id}-page` (or `query-page`) for a custom loop;
   - `paged`, plus a trailing `/{pagination_base}/{n}/` path segment, for an inherited loop.
2. **The names of the controls in its form** (§5), with any trailing `[]` removed.

When any control changes or the form is submitted, the loop's owned parameters are removed and the form's non-empty control values are written back. Everything else is kept, including:

- parameters with the loop's prefix that no control offers, which keep applying as they did in 0.3.3;
- `s` on a search template whose Search block sits outside the loop;
- other loops' parameters;
- `lang`, UTM tags, and `p` / `page_id` on plain permalinks.

JavaScript (§6.1) and the no-JS form (§5.2) use this same rule, so both produce the same query state. They differ only in list encoding: commas from JavaScript, `[]` without it.

### 3.4 Where inherited parameters apply

- **They apply to** the main query of home, archive and search requests.
- **They never apply to:**

  - singular requests (posts, pages, a static front page);
  - 404s, feeds, or the admin.

  So a stray `?query-post_type=post` can't 404 a page.

- **On a post type archive,** `query-post_type` is ignored.
- **On an author archive,** the author filter is intersected with the archive's author. An author filter that doesn't include the archive's author returns no results.

### 3.5 Reserved keys

These taxonomy names collide with built-in keys and can't be filtered: `post_type`, `author`, `sort`, `s` and `page`. Also reserved, in inherited loops only: taxonomy names that start with digits followed by a hyphen (`2024-events`), because they read as a loop ID. Both limits are documented and not worked around.

### 3.6 Breaking and behaviour changes from 0.3.x

- **Old URLs:**
  - `query-{id}-orderby` / `query-{id}-order` are no longer read, so old sort links show the loop's default order.
  - New author links use nicenames. Numeric IDs still work.
  - An author value that matches no user now returns no results. In 0.3.3 an invalid numeric ID was dropped, which showed everything.
- **Markup and the store:**
  - Controls have real `name`s and a `form` attribute, and each Query Loop gains a hidden `<form>` as its last child (§5).
  - `data-wp-context` on filter and sort wrappers no longer carries `queryVar`, `pageVar`, `orderbyVar` or `orderVar`.
  - The store actions `updateFilters`, `handleSelect`, `handleSort` and `search` are removed.
  - The Sort block no longer renders a separate empty option when the loop's order matches an option (§4.3).
- **Query results:**
  - Sticky posts are no longer added to filtered results (§4.2).
  - The loop's own `tax_query` relation is preserved.
  - A post type filter no longer removes the loop's `post__in`, such as sticky "only".
- **Other:**
  - Core Search keeps its own interactivity attributes. This fixes the button-only variant.
  - Internal helpers are removed: `SortHelper`, `AbstractQueryHelper`'s config methods, `FilterHelper::get_current_filter_value()`, `FilterHelper::get_*_filter_config()`, and `AuthorHelper::get_author_filter_config()`.

## 4. Server architecture

### 4.1 Classes

| Class                          | Responsibility                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Url\QueryParams`              | Parameter names for one loop, built from `( ?int $query_id, bool $inherit )`. `key( $name )`, `page_key()`, `form_id()`, `strip_pagination( string $path ): string` (inherited only). The only place parameter names and the form ID are defined.                                                                                                                                                                                                                                                                                                |
| `Url\FilterState`              | Parsed and validated values. `FilterState::for_loop( QueryParams $params ): FilterState` reads `$_GET` once per loop per request and memoizes the result, keyed by prefix, because core builds a custom loop's query vars up to 6 times per page. `FilterState::from_array( array $get, QueryParams $params )` is the uncached constructor, used by tests. It exposes `post_types`, `taxonomies` (taxonomy ⇒ slugs), `author_ids` (null when absent, `[0]` when nothing resolves), `search`, `sort` (option array or null), and `has_filters()`. |
| `Query\QueryArgs`              | `QueryArgs::apply( array $args, FilterState $state ): array` is a pure merge for custom loops; see §4.2.                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `Query\SortOptions`            | `all()`, `find( string $key )`, `match( string $orderby, string $order )` (case-insensitive).                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `Integrations\MainQueryFilter` | Filtering for inherited loops; see §4.4. It also records the main query's unfiltered `post_type`, `orderby` and `order` for §4.3 and §4.5.                                                                                                                                                                                                                                                                                                                                                                                                       |
| `Integrations\LoopForm`        | Builds the form markup and its hidden inputs (§5.2).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |

- **Adapters over the new classes:**
  - `Core\QueryLoopHandler` keeps the `query_loop_block_query_vars` hook at priority 19, and becomes a thin adapter.
  - `Integrations\BlockFilters` keeps its hooks and gains form injection (§5.2).
- **Removed:** the helpers listed in §3.6.
- **Autoloading:** classes follow the PSR-4 layout under `includes/`. Run `composer dump-autoload` after adding them.

### 4.2 `QueryArgs::apply` (custom loops)

- **Post types:** override `post_type`. Leave `post__in` alone.
- **Taxonomies:** build one `IN` clause per taxonomy, by slug. With two or more clauses, join them with `relation => AND`.
  - If the loop already has a `tax_query`, nest both, leaving the existing one untouched: `array( 'relation' => 'AND', $existing, $filters )`.
- **Authors:** set `author__in`.
- **Search:** set `s`.
- **Sort:** set `orderby` and `order` from the option, and `meta_key` if the option has one.
- **Sticky posts:** when `has_filters()` is true, set `ignore_sticky_posts => true` unless the loop has already set it.
  - Core prepends sticky posts on page 1 without applying tax, author or search conditions, and `is_home` stays true for nested tax queries and author-only filters (`class-wp-query.php:3583-3628`).

### 4.3 Sort options

```php
/**
 * Filters the options offered by Sort blocks and accepted in the sort URL parameter.
 *
 * The same list is used to render every Sort block and to validate every request.
 */
apply_filters( 'pikari_gutenberg_query_filter_sort_options', array(
    array(
        'key'     => 'date-desc',
        'label'   => __( 'Date (Newest First)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'date',
        'order'   => 'DESC',
    ),
    // date-asc, title-asc, title-desc follow the same shape.
) );
```

- **No block attributes are passed.** The list must be the same everywhere, or an option could render and then be rejected on the query side.
- **Normalizing:**
  - `key` goes through `sanitize_key()`, and `order` is uppercased.
  - Options missing `key`, `label` or `orderby` are dropped.
  - Options whose `orderby` is `meta_value` or `meta_value_num` without a `meta_key` are dropped too.
- **Documented limitation:** a `meta_key` sort leaves out posts that don't have that key.
- **The loop's default order:**
  - custom loops: block context `query.orderBy` / `query.order`;
  - inherited loops: the main query's unfiltered `orderby` / `order`, recorded by `MainQueryFilter`.
  - An empty `orderby` on search (relevance) matches no option.
- **Sort block rendering:**
  - The option that `SortOptions::match()` finds for the default order renders with `value=""`, so choosing it removes the parameter and the URL stays clean.
  - If no option matches, an empty first option renders, labelled with `emptyLabel` (`?:`, default "Default").
  - A valid sort parameter selects its option. Otherwise the default option (or the empty option) is selected.
- **Documented limitation:** Advanced Query Loop's inherited mode rebuilds `$wp_query` from the block's own order, so the sort parameter has no effect there. Its custom mode, which CCLF uses, is unaffected.

### 4.4 Inherited loops: `MainQueryFilter`

- **Gate** (`pre_get_posts`, priority 10). Returns immediately unless every condition holds:

  - `$query->is_main_query()`;
  - `! is_admin()` and `! $query->is_feed()`;
  - `$query->is_home() || $query->is_archive() || $query->is_search()`;
  - `! $query->is_singular()` and `! $query->is_404()`;
  - some `$_GET` key matches `^query-(?!\d+-|page$)`.

  REST requests never reach the main query, so there's no separate check for them.

- **Before any change,** record `post_type`, `orderby` and `order`.
- **Post types:** `set( 'post_type' )`, except on a post type archive (§3.4).
- **Taxonomies:** never touch the `tax_query` query var.
  - That var is parsed before the archive's own term, and core takes the first terms it finds per taxonomy as the queried terms. The archive's title, template and `cat` / `term` vars would then follow the filter (`class-wp-query.php:1177-1181`, `class-wp-tax-query.php:168-174`, `class-wp-query.php:2346-2380`; reproduced on CCLF).
  - Don't use `WP_Tax_Query::get_sql()` either. A new instance's first `IN` clause joins `term_relationships` with no alias (`class-wp-tax-query.php:418-426`). Core's own archive clause joins the same table, and two un-aliased joins on one table are a MySQL error.
  - Instead:
    1. Resolve each taxonomy's slugs to `term_taxonomy_id`s. For hierarchical taxonomies, include child terms, matching core's `include_children` default.
    2. In a `posts_where` callback that acts only on this query object and removes itself afterwards, append one `AND {$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( … ) )` per taxonomy, with the IDs cast to integers.
    3. A taxonomy whose slugs resolve to nothing appends `AND 0 = 1`.
  - This adds no join, so no `GROUP BY` is needed and the archive's queried object never changes.
- **Authors:** set `author__in`, intersected on author archives (§3.4). Core adds the archive's `author` var to `author__in` on plain permalinks (`class-wp-query.php:2398-2408`), so compute the intersection first.
- **Sort:** set `orderby`, `order` and `meta_key`.
- **Sticky posts:** when anything above applied, `set( 'ignore_sticky_posts', true )`.
- **Search:** left to core (`s`).
- **404s:** core already skips the 404 for zero-result home, search, tax, category, tag, post type and author archives whose queried object resolves (`class-wp.php:783-795`). Date archives aren't exempt.
  - A `pre_handle_404` callback returns true when this request's main query had filters applied, `is_date()`, and `! is_paged()`.
  - A page past the end still 404s, which is why pagination is stripped on every filter change.

### 4.5 Option lists

- **Authors:** `FilterHelper::get_filter_options()` sets an author option's `value` to `user_nicename`. The editor preview in `edit.js` does the same.
- **Post types, inherited loops:** the options come from the unfiltered main-query `post_type` recorded by `MainQueryFilter`, falling back to `$wp_query->query['post_type']`. They replace the block context's `postType`.
  - **Search, where the value is `any` or empty:** public post types that aren't excluded from search.
  - **Taxonomy archives, empty value:** the viewable post types the taxonomy is registered for.
  - **Anything else, empty value:** `post`.
  - `attachment` is removed unless attachment pages are enabled.
- **Taxonomy lists:** the editor already filters on `visibility.publicly_queryable`, which is the same test as `is_taxonomy_viewable()`, so it doesn't change.

## 5. Markup

### 5.1 Controls

Every control gets a `name`, and `form="pikari-gutenberg-query-filter-form-{id}"` (`…-form-inherit` for inherited loops):

| Control           | `name`                                 |
| ----------------- | -------------------------------------- |
| Select filter     | `query-3-category`                     |
| Radio filter      | `query-3-category`                     |
| Checkbox filter   | `query-3-category[]`                   |
| Sort select       | `query-3-sort`                         |
| Core Search input | `query-3-s` (custom) / `s` (inherited) |

- **Directive values** the plugin adds are namespaced, for example `data-wp-on--change="pikari/gutenberg-query-filter::actions.change"`. A loop's wrapper may be `core/query` or `core/search`.
- **Unchanged:** the classes, the `<fieldset>` / `<legend>` / `<label>` structure, the per-option `{key}_{slug}` classes, the radio "All" option with `value=""`, and the three existing PHP filters.
- **Filter and Sort wrappers** keep `data-wp-interactive="pikari/gutenberg-query-filter"` and drop their `data-wp-context`.
- **Each Query Filter and Sort block** ends its wrapper with a no-JS button:

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

  - `<noscript>` renders only when JavaScript is off, so JavaScript users never see a button flash or a layout shift.
  - The router removes `<noscript>` elements from fetched pages.
  - A loop with four filters shows four buttons without JavaScript, and each one submits every filter.
  - The label is translatable.

### 5.2 The loop form

`BlockFilters::render_block_query()` runs after the loop and all its inner blocks have rendered.

- **When it injects the form:** only if the Query HTML contains an element whose `form` attribute exactly equals this loop's form ID. The comparison is by attribute value, never by substring, because `…-form-3` must not match `…-form-30`.
- **Where:** as the **last child** of the Query wrapper.
  - Core's `WP_Interactivity_API_Directives_Processor` is `final`, so it can't be reused.
  - `render_block_core/query` receives only this block's HTML, so the wrapper is its first tag. Read the wrapper's tag name with `WP_HTML_Tag_Processor::get_tag()`, which can be `div`, `main`, `section` or `aside`.
  - Insert the form before the last case-insensitive `</{tag}>` in the string.
  - As the last child it doesn't shift flow-layout block gaps.
  - Rendering order, blocks hidden after rendering (core block visibility, the Block Visibility plugin) and fragment caches don't matter.

```html
<form
	hidden
	novalidate
	style="display:none"
	id="pikari-gutenberg-query-filter-form-3"
	class="wp-block-pikari-gutenberg-query-filter__form"
	method="get"
	action="/resources/library/"
	data-wp-interactive="pikari/gutenberg-query-filter"
	data-wp-on--submit="actions.submit"
	data-query-page-key="query-3-page"
	data-query-inherit="false"
	data-query-pagination-base="page"
>
	<input type="hidden" name="lang" value="fr" />
</form>
```

- **`hidden` plus inline `display:none`:** keeps the form out of layout even if a theme overrides `[hidden]`. Controls outside a hidden form still submit with it.
- **`data-query-page-key`:** `query-{id}-page`, or `query-page`, for custom loops, and `paged` for inherited loops.
- **`novalidate`:** core Search's input is `required`, which would otherwise block every submit while it's empty (`blocks/search.php:64`).
- **`action`:**
  - it's the request path, with the pagination segment stripped for inherited loops only;
  - it carries no query string or fragment, since a GET submit replaces them.
- **Hidden inputs:**
  - Built from `$_SERVER['QUERY_STRING']` pairs, not `$_GET`, which rewrites names and drops repeated keys. If `QUERY_STRING` isn't set, as in tests and CLI, fall back to `$_GET`.
  - A pair is dropped when its base name (before any `[`) is one of the loop's owned names: its page key, `paged` for inherited loops, or a control `name` found in the Query HTML with this loop's `form` attribute.
  - `cst` is also dropped; core's pagination-numbers block adds it.
  - Names and values are decoded, then escaped with `esc_attr()`.
- **Loops with Search but no filter blocks** get the same form, so a no-JS search keeps unrelated parameters.

### 5.3 Core Search

`render_block_search()` changes only the Search input. The `<form>` element, and core's `data-wp-interactive` / `data-wp-context` on it, are left alone.

- **On the input:**
  - `name`, `value`, `form`;
  - `data-wp-context='pikari/gutenberg-query-filter::{"searchValue":"…"}'`;
  - `data-wp-bind--value="pikari/gutenberg-query-filter::context.searchValue"`;
  - `data-wp-on--input` and `data-wp-on--compositionend`, both `pikari/gutenberg-query-filter::actions.change`.
- **On Search's submit button:** `form`, so a click submits the loop form.
- **Context guard:** runs whenever the block has `query` context. A missing `queryId` context means a loop without an ID (§3.1). The current code's `empty( $query_id )` check skips those loops.
- **Script:** enqueues the view module.
- **Enter in the input** submits the loop form, which fires `actions.submit`.

## 6. Frontend JavaScript

### 6.1 `buildUrl()`

A pure function in `src/utils/build-url.js`:

```js
buildUrl( href, { entries, ownedNames, pageKey, inherit, paginationBase } ) => string
```

1. `const url = new URL( href ); url.hash = '';`
2. Delete `pageKey` and every name in `ownedNames`. If `inherit`, also remove a trailing `/{paginationBase}/{n}` (with or without a slash) from `pathname`. An inherited loop's `pageKey` is `paged`.
3. For each entry whose bare name (trailing `[]` removed) is in `ownedNames`:

   - skip empty values;
   - group values by bare name;
   - write each group as one comma-joined parameter.

   Hidden inputs are not in `ownedNames`, so they are never rewritten.

4. Return `url.href`.

`ownedNames` are the bare names of the form's non-hidden `form.elements`. `pageKey`, `inherit` and `paginationBase` come from the form's `data-query-*` attributes.

A companion `sameQuery( a, b )` compares two URLs by path plus sorted, decoded parameters. `a,b`, `a%2Cb` and `key[]=a&key[]=b` count as equal.

### 6.2 Store `pikari/gutenberg-query-filter`

- **`actions.change`:** for select, radio, checkbox and sort `change` events, and search `input` / `compositionend` events.
  1. Ignore `input` events where `event.isComposing` is true.
  2. For search inputs, set `context.searchValue = event.target.value` straight away. The bound value then survives the region re-render and never loses typed text.
  3. Find the form with `document.getElementById( event.target.getAttribute( 'form' ) )`, and build the URL **now**, from the DOM as it is when the event fires.
  4. Skip if `sameQuery( url, inFlightUrl ?? location.href )`.
  5. If a navigation is in flight, navigate immediately. The router's `navigatingTo` check then discards the stale response (`interactivity-router`, WP 7.1).
  6. Otherwise, store the URL as this form's pending navigation, replacing any earlier one, and start or restart a timer: 250 ms, or 400 ms for search.
- **`actions.submit`:** wrapped in `withSyncEvent()`.
  1. `event.preventDefault()`, which closes roadmap #21.
  2. Cancel the pending timer.
  3. Build the URL from the form and navigate immediately.
- **Navigation:**
  - The timer callback calls a store action through the store proxy, never a bare generator. It doesn't call `getElement` or `getContext`, and it attaches `.catch()`.
  - `inFlightUrl` is set before the router call and cleared in `finally`.
  - Search navigations after the first in one typing burst pass `replace: true`, so every pause doesn't add a history entry. A burst ends when focus leaves the input, or on submit.
  - The injected-style restore after navigation, `callbacks.restoreInjectedStyles`, the hydration-time style capture and the `popstate` listener are kept unchanged.
- **Known limitation:** on Windows, arrowing through a closed `<select>` fires `change` on every step. The debounce groups fast steps together but doesn't stop a slow keyboard user from navigating at each pause. Documented; the fix is out of scope.
- **Deferred to D:** router announcements ("Page Loaded.") stay as they are. A result-count live region is sub-project D.

### 6.3 Sort block script

The Sort block's `block.json` points `viewScriptModule` at the shared view module. Sub-project A fixes the handle, and B depends on that.

## 7. Edge cases

| Case                                                      | Behaviour                                                                                                                                                                           |
| --------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Two custom Query Loops on a page                          | Separate prefixes, forms and owned names.                                                                                                                                           |
| Nested Query Loops                                        | Each injects its own form. A nested loop that repeats per post repeats its form `id`, and controls join the first. Documented as unsupported.                                       |
| Two loops with the same `queryId`, or two inherited loops | They share parameters and a form `id`, as core's pagination already does. Documented as unsupported.                                                                                |
| Two blocks filtering the same taxonomy in one loop        | They share one parameter. Radios become a single group: only the last "All" is checked on load, the first group loses its Tab stop, and arrow keys move across both. Documented.    |
| First filter block hidden by a visibility rule            | The form is still injected, because the check happens after rendering (§5.2).                                                                                                       |
| Search template with Search outside the loop              | `s` isn't owned, so it's kept on filter changes (§3.3).                                                                                                                             |
| Stray `query-*` parameter on a single post or page        | Ignored (§3.4).                                                                                                                                                                     |
| Filtered to zero results                                  | Home, search and matched archives, including date archives, return 200. A page past the end returns 404.                                                                            |
| Unknown term slug                                         | No results for that taxonomy, and the archive's queried object is unaffected.                                                                                                       |
| Plain permalinks                                          | `p`, `page_id`, `cat` etc. become hidden inputs. Inherited loops own `paged`.                                                                                                       |
| URL with a `#fragment`                                    | Dropped on filter change.                                                                                                                                                           |
| Old `query-{id}-orderby` links                            | Ignored; the default order applies.                                                                                                                                                 |
| Full-page caches and CDNs                                 | `query-*`, `s` and `paged` must be part of the cache key. Caches that ignore tracking parameters can serve another visitor's UTM values in hidden inputs. Documented.               |
| Nicenames                                                 | Often the same as the login name. The author filter makes them public, as author archives already do. Documented, and the existing options filter can change the displayed options. |

## 8. Testing

### 8.1 Test tooling (first commits of B0)

- **Jest mock drift.** `tests/unit/__mocks__/@wordpress/interactivity.js` has drifted from the shared template. It adds `readStore`, and it isn't in `skip-sync`, so a sync would break `view.test.js`.
  - Move that change into the monorepo template, or add the file to `skip-sync`.
  - The mock's `withScope` must run generator functions, as core's does.
- **CI facts:**
  - `BlockFiltersTest` already runs on CI (#95), and CI provides only `WP_HTML_Tag_Processor`, not `WP_HTML_Processor`.
  - Playwright doesn't run on CI. Roadmap #28 covers that.
  - Until it does, the inherited-loop SQL, no-JS and browser behaviour are verified locally only. The PR descriptions say so.

### 8.2 PHP (Brain\Monkey)

1. **Characterization tests first (B0).** Write `QueryLoopHandlerTest` against 0.3.3:

   - post type validation;
   - tax_query merging;
   - author IDs;
   - search;
   - order validation.

   When B1 ports these to the new classes, only the §3.6 changes may differ, and each difference is written as its own test named for the change.

2. **Unit tests:**
   - `QueryParams`: keys, loops without a `queryId`, form ID, pagination stripping.
   - `FilterState`: string and array input, de-duplication and the cap, every validation rule, author resolution including "nothing resolves", memoization.
   - `QueryArgs`: every rule in §4.2, including nesting and sticky posts.
   - `SortOptions`: normalization, dropped options, case-insensitive `match()`.
   - `MainQueryFilter`: the gate matrix (singular, 404, feed, admin, home, archive, search, `query-page` only); post type and author archive rules; recorded originals; the `posts_where` callback only acting on its own query, with an unknown slug giving `0 = 1`; `pre_handle_404` conditions.
   - `LoopForm`: hidden inputs from a raw query string, owned-name dropping, `cst`, array-style names.
3. **`BlockFiltersTest`** (real Tag Processor):
   - form injection position;
   - exact `form` matching, so loop 3 doesn't match loop 30;
   - nested loops;
   - Search input attributes without touching core's form attributes.
4. **Filters:** every new filter has a `Filters\expectApplied` test.
5. **SQL behaviour in the main query** can't run under Brain\Monkey, including the `posts_where` subqueries alongside an archive's own join. It's covered by Playwright (§8.4).

### 8.3 Jest

- **`build-url.test.js`:**
  - owned names only;
  - hidden inputs untouched;
  - `[]` joining;
  - empty values;
  - custom loops, loops without a `queryId`, and inherited loops, including pagination with and without a trailing slash;
  - fragments dropped;
  - `s` kept when not owned.
  - Assert with `URLSearchParams`, not string matching.
- **`same-query.test.js`:** the encoding variants.
- **`view.test.js`,** with fake timers:
  - debounce delays;
  - immediate navigation while one is in flight;
  - skipping the same query;
  - IME composition;
  - `submit` cancels pending navigations and calls `preventDefault`;
  - search context updates on every input;
  - `replace` across a typing burst.

### 8.4 Playwright

- **Configuration:**
  - A root `playwright.config.js` extends `@wordpress/scripts/config/playwright.config.js` with `testDir: './tests/e2e'`, and chains the default authentication global setup with a fixture setup.
  - `test:e2e` runs `wp-scripts test-playwright`.
  - `@playwright/test` and `@wordpress/e2e-test-utils-playwright` are added as explicit devDependencies.
  - `artifacts/` is added to `.gitignore`.
- **Environment:**
  - Tests run against the wp-env tests instance (port 5885).
  - `.wp-env.json` `lifecycleScripts` sets pretty permalinks on the tests instance with wp-cli.
  - The fixture setup fails early if `build/` is missing.
- **Fixtures,** created through REST in the fixture setup:
  - 12 posts across two categories and two authors, including one sticky post; loops show 5 per page.
  - A page with a custom loop _without_ enhanced pagination (taxonomy checkbox, author select, sort, search, and a Custom HTML block that injects an id-less `<style>` from an inline script).
  - A page with a custom loop _with_ enhanced pagination.
  - A page with a loop containing only a Sort block.
  - A page with a button-only Search variant inside a loop.
  - A category archive template with an inherited loop and filters.
  - A search template like Twenty Twenty-Five's, with Search outside the loop and filters inside.
- **With JavaScript:**
  - checking two categories updates results, and `URLSearchParams` shows `category=a,b`;
  - the author select writes a nicename;
  - sort in a Sort-only loop;
  - `pressSequentially` typing is grouped and keeps the typed text;
  - changes land while a response is delayed with `page.route` (select, checkbox and radio), and the final URL and DOM reflect the last change;
  - filter changes reset pagination;
  - archive filtering from `/page/2/` returns 200, with the archive title unchanged;
  - an unknown slug on an archive returns an empty 200, not a 404;
  - the search template keeps `s`;
  - a single post with `?query-post_type=page` returns 200;
  - the sticky post is absent from filtered results;
  - enhanced pagination and plugin navigation both keep the injected style enabled;
  - button-only Search still expands;
  - Enter on a checkbox navigates;
  - a URL with a hash filters without errors;
  - Back and Forward restore results.
- **Without JavaScript** (`javaScriptEnabled: false`):
  - Apply buttons are visible;
  - Apply with an empty search box submits every filter;
  - `key[]=` URLs are honoured;
  - Enter in Search submits the filters;
  - unrelated parameters survive.
- **Accessibility:** an aria snapshot shows named groups and labelled selects, and focus stays on the triggering control after navigation.
- **Visual:** `toHaveScreenshot` covers each display type and horizontal layout, with JavaScript on.
  - Filter-block bounding boxes are asserted equal before and after a navigation.
  - Screenshot baselines run locally only.

### 8.5 Hands-on and real sites

- **Before and after each PR,** use Playwright MCP to check the front end, the editor and a keyboard-only pass, with screenshots of any difference.
- **Before 1.0.0 is published,** check locally:
  - **Kindler Insights:** its theme only targets `__radio-group`, `__radio-item` and `.category_*`, none of which change.
  - **CCLF Resource Library:** the plugin must be active under its current folder name; see §10.3.

## 9. Documentation and translations

- **`docs/hooks.md`:**
  - the URL contract (§3);
  - markup with the form, `name` / `form` attributes, and the `__form` / `__submit` classes;
  - the `sort_options` filter and its limitations;
  - author values are nicenames;
  - the §7 edge cases a theme author would hit;
  - the editor's Sort preview ignores `sort_options`.
- **Plugin `CLAUDE.md`:**
  - rewrite "Query Filter render flow" step 5 and the extension rules that mention `input.value` and `queryVar`;
  - correct the line saying none of the filters apply to the Sort block.
- **`CHANGELOG.md`:** a "Breaking changes" section listing everything in §3.6.
- **Translations:**
  - New strings: "Apply filters" and "Default".
  - Run the plugin `CLAUDE.md` i18n workflow: `i18n:pot`, `msgmerge` into `fr_CA.po`, translate, `i18n:mo` and `i18n:json`.

## 10. Delivery

### 10.1 Pull requests

| PR  | Branch                              | Contents                                                                                                                                                                                                                                                                        | Label                |
| --- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| B0  | `chore/query-filter-test-harness`   | §8.1 tooling, Playwright config and fixtures against 0.3.3 behaviour, characterization tests. No production code changes.                                                                                                                                                       | `skip-changelog`     |
| B1  | `feature/url-contract-custom-loops` | `QueryParams`, `FilterState`, `QueryArgs`, `SortOptions`; the adapter; the single sort key (render and current JS); author nicenames (options, editor preview); validation; sticky, relation and `post__in` fixes; `hooks.md` URL contract and `sort_options`; custom-loop E2E. | `breaking` (by hand) |
| B2  | `feature/inherited-loops`           | `MainQueryFilter`; inherited post type options and sort defaults; archive and search-template E2E.                                                                                                                                                                              | `feature`            |
| B3  | `feature/form-based-filters`        | Controls, `LoopForm` and injection, Search rework, `<noscript>` buttons, store rewrite, i18n, `CHANGELOG`, `CLAUDE.md`, the remaining E2E and visual baselines.                                                                                                                 | `breaking` (by hand) |

Each PR has an implementation plan, is written test-first, and gets a hands-on browser pass before merging.

### 10.2 Release sequencing

1. **Sub-project A** (cleanup) merges and is **published as 0.3.4** before B0. B depends on A's Sort script handle fix and on A having already removed dead code.
2. **When B1 merges,** the automatic bump PR moves `main` to 1.0.0, and the draft title shows a clean `v1.0.0` once the bump lands. **Don't publish until sub-project E is done.** Todo #529 records this.
3. **An urgent 0.3.x fix** during this window is released from a branch cut from the latest 0.3.x tag.

### 10.3 Upgrade notes for real sites

- **CCLF:**
  - Its Composer constraint `^0.3` never resolves 1.0.0; it needs `^1.0`.
  - Production still runs v0.1.13, installed as `plugins/pikari-query-filter/`. Moving to the package index renames the folder to `plugins/pikari-gutenberg-query-filter/`, which deactivates the plugin. Todo #478 has the two remedies.
  - Deploy 0.3.x first and confirm the plugin is active.
- **Kindler:** no markup dependencies affected. Check its bookmarked sort links, if any exist.

## 11. Revision 2 changes

1. **Inherited taxonomy filters** are added as `posts_where` subqueries, not through the `tax_query` var or `WP_Tax_Query` SQL. The gate is limited to home, archive and search requests, with rules for post type and author archives and `pre_handle_404` for date archives.
2. **A loop owns** its pagination plus the controls in its form, not every parameter with its prefix.
3. **The form is injected** as the Query wrapper's last child after rendering, with `novalidate`. Revision 1 had the first filter block print it as its first child.
4. **Core Search keeps its own interactivity attributes.** Plugin directive values are namespaced, and the search value stays bound to context.
5. **Navigation:** the URL is built when the event fires, navigation is immediate while another is in flight, IME composition is ignored, `replace` history is used while typing, and the fragment is dropped.
6. **B ships as four PRs.**
7. **A is published as 0.3.4 first,** and nothing is published between B1 merging and E.

Also fixed:

- **Query results:** sticky posts; `tax_query` relation; `post__in`.
- **Validation and lookups:** `is_taxonomy_viewable()`; `sanitize_title_for_query()`; list caps; author lookup and unresolved authors.
- **Performance and markup:** memoization; loops without a `queryId`; hidden inputs from the raw query string.
- **Sort:** filter signature; `meta_value` options; default-order matching; the default option's empty value.
- **Tests and docs:** Playwright specifics; the Jest mock; CI facts; translations; CCLF upgrade notes.
