# Pikari Gutenberg Query Filter — Theming & Hooks Reference

How the Query Filter block renders on the frontend, the classes themes can target, and the PHP filters for changing the options and their markup.

---

## URL Parameters

Query Filter, Sort and Search read and write plain URL query parameters. A **custom Query Loop** — one with a `queryId`, which is any Query Loop that isn't set to inherit the main query — uses the numbered parameters below. An **inherited Query Loop** — one that uses the template's own query, as on home, archive and search templates — reads and writes the same filters without a loop number; see [Inherited loops](#inherited-loops) for where those apply.

### Parameter names

A custom loop's names come from its `queryId`, following the same `query-{id}-{key}` scheme the block editor already uses for pagination. An inherited loop drops the loop number entirely:

| Filter    | Custom loop (`queryId` 3)        | Inherited loop                 |
| --------- | -------------------------------- | ------------------------------ |
| Post type | `query-3-post_type=post,page`    | `query-post_type=post,page`    |
| Taxonomy  | `query-3-{taxonomy}=news,events` | `query-{taxonomy}=news,events` |
| Author    | `query-3-author=jane-doe,sam`    | `query-author=jane-doe,sam`    |
| Sort      | `query-3-sort=title-asc`         | `query-sort=title-asc`         |
| Search    | `query-3-s=term`                 | `s=term` (core)                |
| Page      | `query-3-page=2` (core)          | `/page/2/` or `paged=2` (core) |

A **custom loop with no `queryId`** — every Query Loop in Twenty Twenty-Five, for example — uses the prefix `query-0-`. Its page parameter is the one exception: it stays `query-page`, because that's what core itself uses for a loop with no ID. This is different from an inherited loop, which never has a `queryId` and uses `query-` with no loop number at all, rather than falling back to `query-0-`.

### Values

- **Multiple values** are a comma-separated list, for example `query-3-category=news,events`. Values are de-duplicated and capped at 50 per parameter; anything past the 50th is ignored. They're written in the order their controls appear on the page (DOM order), not the order they were selected — checking "News" then "Events" writes `events,news` if Events is listed first in the block.
- **Empty values** are ignored.
- **Taxonomy keys** must name a taxonomy that's publicly viewable (`is_taxonomy_viewable()`) — the same test the editor uses to offer it as a filter.
- **Taxonomy values** are term slugs. A slug that matches no term returns no results for that taxonomy; it never falls back to showing everything.
- **Post type values** must be viewable (`is_post_type_viewable()`). `attachment` is accepted only when attachment pages are enabled.
- **Author values** are the user's nicename, for example `query-3-author=jane-doe`. A plain numeric value is still accepted and resolved by user ID, but a nicename match always wins over an ID match for the same value. An author value that matches no user returns no results — the same as an unknown taxonomy slug — rather than showing every author's posts.
- **Sort values** must be a key from [`pikari_gutenberg_query_filter_sort_options`](#pikari_gutenberg_query_filter_sort_options). An empty or unrecognized value means the loop's own default order.

### Inherited loops

- **They apply to** the main query on the home page, archives and search results.
- **They never apply to** a single post or page, a 404, a feed, or the admin — a stray `?query-post_type=post` can't 404 a page.
- **On a post type archive,** `query-post_type` is ignored; the archive is already that post type.
- **On an author archive,** `query-author` is intersected with the archive's own author rather than replacing it. An author filter that doesn't include the archive's own author returns no results.
- **A taxonomy name that starts with digits and a hyphen** (`2024-events`) can't be filtered in an inherited loop: its parameter, `query-2024-events`, reads like loop 2024's own `events` key and is ignored rather than read as a taxonomy filter.
- **Filtering never changes what the page is.** Its title, template and queried object stay the same. An unknown taxonomy term shows an empty result rather than a 404, and a filtered date archive doesn't 404 either — unless a hand-built link also pages it past the end, which still 404s.
- **Filtering, sorting or searching resets pagination.** A change made from `/page/2/` returns to page 1.
- **The Sort block starts on "Default"** on an inherited loop's first, unfiltered load. This plugin only records the archive's own order once a filter parameter is already present in the request, so with nothing recorded yet, no sort option matches and the empty placeholder shows.

**Things to know:**

- **Plain permalinks on an author archive.** If the site uses plain URLs (`?p=123`) and a filter picks an author other than the archive's own, WordPress re-adds the archive's own author to the query after this plugin runs, so the results show that author's posts instead of none. Pretty permalinks (`/author/jane-doe/`) aren't affected.

---

## Frontend Markup

A taxonomy filter (`category`) with the **Checkbox** display type renders:

```html
<div
	class="wp-block-pikari-gutenberg-query-filter"
	data-wp-interactive="pikari/gutenberg-query-filter"
>
	<fieldset class="wp-block-pikari-gutenberg-query-filter__fieldset">
		<legend class="wp-block-pikari-gutenberg-query-filter__label">
			Categories
		</legend>
		<div class="wp-block-pikari-gutenberg-query-filter__checkbox-group">
			<label
				class="wp-block-pikari-gutenberg-query-filter__checkbox-item category_news"
			>
				<input
					type="checkbox"
					name="query-3-category[]"
					form="pikari-gutenberg-query-filter-form-3"
					value="news"
					data-wp-on--change="pikari/gutenberg-query-filter::actions.change"
				/>
				<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text"
					>News</span
				>
			</label>
			<!-- …one <label> per term… -->
		</div>
	</fieldset>
	<noscript>
		<button
			type="submit"
			form="pikari-gutenberg-query-filter-form-3"
			class="wp-block-pikari-gutenberg-query-filter__submit"
		>
			Apply filters
		</button>
	</noscript>
</div>
```

The `<fieldset>` and its `<legend>` name the group of options for screen readers. The plugin's stylesheet removes the fieldset's default border, margin and padding.

The **Radio** display type is the same shape with `radio` in place of `checkbox` (`__radio-group`, `__radio-item`, `__radio-text`). Its first option is the "All" choice, which has `value=""` and clears the filter. The **Horizontal** layout adds `has-layout-horizontal` to the group element.

The **Select** display type has no fieldset. It renders `<label class="wp-block-pikari-gutenberg-query-filter__label" for="…">` followed by a plain `<select class="wp-block-pikari-gutenberg-query-filter__select">`. Its `<option>` elements get no per-option classes and cannot contain HTML, so the class and label filters below do not apply to it.

The block label is a `<legend>` for radio and checkbox filters and a `<label>` for select filters, so target it by class, not by element.

| Element                  | Class                                                            |
| ------------------------ | ---------------------------------------------------------------- |
| Block wrapper            | `wp-block-pikari-gutenberg-query-filter`                         |
| Option fieldset          | `wp-block-pikari-gutenberg-query-filter__fieldset`               |
| Block label              | `wp-block-pikari-gutenberg-query-filter__label`                  |
| Option group             | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-group` |
| Option `<label>`         | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-item`  |
| Option `<label>`, unique | `{key}_{slug}` — see [Option Classes](#option-classes)           |
| Option text              | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-text`  |
| Dropdown                 | `wp-block-pikari-gutenberg-query-filter__select`                 |
| Injected loop form       | `wp-block-pikari-gutenberg-query-filter__form`                   |
| No-JS submit button      | `wp-block-pikari-gutenberg-query-filter__submit`                 |

See [Form and Controls](#form-and-controls) for the injected `<form>`, the `name` / `form` attributes on each control, and the no-JS `<noscript>` button.

---

## Form and Controls

Every filter and sort control lives inside a real `<form>`, so the block works with JavaScript off. `BlockFilters::render_block_query()` scans the rendered Query Loop after every inner block has rendered; if it finds any control carrying a matching `form` attribute, it injects the loop's hidden `<form class="wp-block-pikari-gutenberg-query-filter__form">` as the **last child** of the Query wrapper. A control whose `form` attribute doesn't match means no form is injected at all — the `name` and `form` attributes below are not cosmetic, they're what makes a control submit.

The form itself is `hidden`, has an inline `style="display:none"`, and takes no layout space. It carries the loop's page-number key, whether the loop is inherited, and the rewrite's pagination base as `data-query-*` attributes, plus a hidden input for every other parameter already on the URL (language, UTM params, etc.) so a no-JS submit doesn't drop them.

### Control attributes

Every control gets a `name` and a `form="pikari-gutenberg-query-filter-form-{id}"` (`…-form-inherit` for inherited loops):

| Control           | `name`                                 |
| ----------------- | -------------------------------------- |
| Select filter     | `query-3-category`                     |
| Radio filter      | `query-3-category`                     |
| Checkbox filter   | `query-3-category[]`                   |
| Sort select       | `query-3-sort`                         |
| Core Search input | `query-3-s` (custom) / `s` (inherited) |

**The radio group's `name` is the query parameter, not the block's own ID.** That's what lets a radio filter submit through a plain `<form>`. One consequence: two Query Filter blocks in the same loop filtering the same taxonomy share one `name` and therefore merge into a single radio group — selecting an option in one visually separate block also selects it in the other. This is accepted, not a bug. Because they share a `name`, arrow-key navigation and assistive-technology announcements ("1 of 8") span both `<fieldset>`s even though each shows only 4 options, and if both blocks render an "All" choice only one of the two identical-`name` radios can ever be checked.

Every plugin directive value on a control is namespaced, for example `data-wp-on--change="pikari/gutenberg-query-filter::actions.change"`, because inside a loop the nearest `data-wp-interactive` ancestor may belong to `core/query` rather than to this plugin.

**The `<input>` remains outside all filterable markup.** `view.js` depends on a control's `name`, `form`, and `value` attributes — not on a `queryVar` from `data-wp-context`, which the Filter and Sort block wrappers no longer carry.

### The no-JS Apply button

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

- It renders only when JavaScript is off, so JavaScript users never see it, not even briefly.
- A loop with four filter/sort blocks shows four buttons without JavaScript, and clicking any one of them submits every filter and sort control in the loop, because they all share the same `form`.
- Core's Search block needs no `<noscript>` button of its own: its own submit button already carries a `form` attribute pointing at the loop's form (see [Control attributes](#control-attributes)), so clicking it submits the loop's hidden `<form>` — not core's own `<form>`, whose `action` is left as-is but which nothing joins it to anymore.

### Limitation: two inherited loops on one page

Every inherited loop shares the form id `pikari-gutenberg-query-filter-form-inherit`, because inherited loops share one set of URL parameters. Two inherited loops on the same page therefore emit two `<form>` elements with the same DOM id, and HTML resolves a `form` attribute to the **first** element with that id — so every control on the page associates with the first loop's form.

The two forms are not interchangeable. Each one's hidden inputs are built from the controls of its own loop, so the first form can carry a hidden input for a parameter that only the second loop's controls own. A submit usually still lands on the live value — the stale hidden copy comes first in tree order, the live control's value later, and the later value wins — but the duplicate id is observable markup, so don't script or style against it expecting one form per loop. The two loops read the same parameters in any case, so they filter each other; give one of them its own query settings if they need to filter independently.

---

## Option Classes

Every radio and checkbox `<label>` gets a unique class built as `{key}_{slug}`:

| Filter type | `key`                                  | `slug`         | Example           |
| ----------- | -------------------------------------- | -------------- | ----------------- |
| Taxonomy    | Taxonomy name (`category`, `post_tag`) | Term slug      | `category_news`   |
| Post type   | `post-type`                            | Post type name | `post-type_page`  |
| Author      | `author`                               | User nicename  | `author_jane-doe` |
| "All" radio | Same key as the filter                 | `all`          | `category_all`    |

### Example: Style individual options

```css
/* Colour one category. */
.wp-block-pikari-gutenberg-query-filter .category_news {
	color: #b00020;
}

/* Emphasise whichever option is checked. */
.wp-block-pikari-gutenberg-query-filter__checkbox-item:has(input:checked) {
	font-weight: 700;
}

/* Hide the native control on one option and draw your own. */
.post-type_page input {
	appearance: none;
	width: 1rem;
	height: 1rem;
	border: 2px solid currentColor;
	border-radius: 50%;
}

.post-type_page input:checked {
	background: currentColor;
}
```

**Things to know:**

- Classes are sanitized with `sanitize_html_class()`, which removes percent-encoded characters. A term whose slug is non-Latin (for example `%e6%97%a5`) gets **no** unique class. Use [`pikari_gutenberg_query_filter_option_classes`](#pikari_gutenberg_query_filter_option_classes) to add one, for example from the term ID.
- A term whose slug is literally `all` shares its class with the "All" radio. Target `input[value=""]` to tell them apart.
- Classes are the same in every block instance. Two blocks filtering `category` both output `category_news`. To style one block only, give it an **Additional CSS class** in the editor and scope your selector to it.
- The editor preview adds the same unique classes, so theme styles show in the editor. The PHP filters on this page run on the frontend only.

---

## The Option Array

All three filters receive options as associative arrays:

| Key     | Type                                         | Description                                                                                                 |
| ------- | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `value` | `string`                                     | Written to the URL query variable when selected: term slug, post type name, or (for authors) user nicename. |
| `label` | `string`                                     | Human-readable label. **Not escaped** — escape it when you output it.                                       |
| `slug`  | `string`                                     | Used to build the unique class. Optional; falls back to `value`.                                            |
| `item`  | `WP_Term`, `WP_Post_Type`, `WP_User`, `null` | The source object. `null` for the "All" radio. May be missing on options added through a filter.            |

## The Block Attributes Array

Each filter also receives the block's `$attributes`:

| Key               | Type      | Values                                                   |
| ----------------- | --------- | -------------------------------------------------------- |
| `filterType`      | `string`  | `post-type`, `taxonomy`, or `author`                     |
| `taxonomy`        | `string`  | Taxonomy name. Required when `filterType` is `taxonomy`. |
| `displayType`     | `string`  | `select`, `radio`, or `checkbox`                         |
| `layoutDirection` | `string`  | `vertical` or `horizontal`                               |
| `label`           | `string`  | Block label text, if set in the editor                   |
| `showLabel`       | `boolean` | Whether the block label is visible                       |
| `emptyLabel`      | `string`  | Label for the "All" choice; empty means "All"            |

---

## Filters

### `pikari_gutenberg_query_filter_options`

Filters the list of options before any markup is rendered. Applies to every display type, including Select. Runs once per block render.

The "All" choice is not in this list; editors control its text with the block's **Empty Choice Label** setting.

**Parameters:**

| Parameter     | Type      | Description                                                                      |
| ------------- | --------- | -------------------------------------------------------------------------------- |
| `$options`    | `array[]` | Options. See [The Option Array](#the-option-array).                              |
| `$attributes` | `array`   | Block attributes. See [The Block Attributes Array](#the-block-attributes-array). |

**Return:** `array[]` — The options to render. Return an empty array to hide the block.

An option's `value` is applied to the query as a term slug, post type name, or (for authors) user nicename, depending on the filter type. Options you add must use a value of that kind.

#### Example: Hide the Uncategorized term

```php
/**
 * Remove "Uncategorized" from category filters.
 *
 * @param array $options    Filter options.
 * @param array $attributes Block attributes.
 * @return array
 */
function my_theme_hide_uncategorized( array $options, array $attributes ): array {
    if ( 'category' !== ( $attributes['taxonomy'] ?? '' ) ) {
        return $options;
    }

    return array_values(
        array_filter(
            $options,
            fn( array $option ): bool => 'uncategorized' !== $option['value']
        )
    );
}

add_filter( 'pikari_gutenberg_query_filter_options', 'my_theme_hide_uncategorized', 10, 2 );
```

#### Example: Rename a post type in the filter

```php
/**
 * Show "Articles" instead of "Posts" in post type filters.
 *
 * @param array $options    Filter options.
 * @param array $attributes Block attributes.
 * @return array
 */
function my_theme_rename_posts_option( array $options, array $attributes ): array {
    if ( 'post-type' !== $attributes['filterType'] ) {
        return $options;
    }

    foreach ( $options as $index => $option ) {
        if ( 'post' === $option['value'] ) {
            $options[ $index ]['label'] = __( 'Articles', 'my-theme' );
        }
    }

    return $options;
}

add_filter( 'pikari_gutenberg_query_filter_options', 'my_theme_rename_posts_option', 10, 2 );
```

---

### `pikari_gutenberg_query_filter_option_classes`

Filters the classes on each radio or checkbox option's `<label>`, including the "All" radio. The default array holds the item class (`wp-block-pikari-gutenberg-query-filter__checkbox-item`) and the unique `{key}_{slug}` class.

**Parameters:**

| Parameter     | Type       | Description                                            |
| ------------- | ---------- | ------------------------------------------------------ |
| `$classes`    | `string[]` | Class names.                                           |
| `$option`     | `array`    | The option. See [The Option Array](#the-option-array). |
| `$attributes` | `array`    | Block attributes.                                      |

**Return:** `string[]` — Class names. Each is escaped with `esc_attr()` on output.

Removing the `__checkbox-item` or `__radio-item` class also removes the plugin's layout styles from that option.

#### Example: Add a term ID class

Useful when term slugs are non-Latin and produce no unique class.

```php
/**
 * Add a term-{id} class to taxonomy filter options.
 *
 * @param string[] $classes    Label classes.
 * @param array    $option     Filter option.
 * @param array    $attributes Block attributes.
 * @return string[]
 */
function my_theme_option_term_id_class( array $classes, array $option, array $attributes ): array {
    $term = $option['item'] ?? null;

    if ( $term instanceof WP_Term ) {
        $classes[] = 'term-' . $term->term_id;
    }

    return $classes;
}

add_filter( 'pikari_gutenberg_query_filter_option_classes', 'my_theme_option_term_id_class', 10, 3 );
```

---

### `pikari_gutenberg_query_filter_option_label`

Filters the markup inside each radio or checkbox option's `<label>`, after the `<input>`. Applies to the "All" radio as well. The default markup is the text span:

```html
<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text">News</span>
```

**Parameters:**

| Parameter     | Type     | Description                                                                                   |
| ------------- | -------- | --------------------------------------------------------------------------------------------- |
| `$html`       | `string` | Default markup, with the label already escaped.                                               |
| `$option`     | `array`  | The option. See [The Option Array](#the-option-array). `$option['label']` is **not** escaped. |
| `$attributes` | `array`  | Block attributes.                                                                             |

**Return:** `string` — Markup to render.

- The returned markup is passed through `wp_kses_post()`. Tags and attributes allowed in post content survive, including `<span>`, `<strong>`, `<img>`, `class`, and `style`. `<script>`, `<svg>`, `<input>`, and inline event handlers are stripped. For icons, use an `<img>` or a CSS background on a class.
- The `<input>` is not part of this markup and cannot be changed. The frontend script relies on its `name`, `form`, and `value` attributes; see [Form and Controls](#form-and-controls).

#### Example: Append the post count to taxonomy options

```php
/**
 * Show each term's post count after its label.
 *
 * @param string $html       Label markup.
 * @param array  $option     Filter option.
 * @param array  $attributes Block attributes.
 * @return string
 */
function my_theme_option_label_count( string $html, array $option, array $attributes ): string {
    $term = $option['item'] ?? null;

    if ( ! $term instanceof WP_Term ) {
        return $html;
    }

    return $html . sprintf( ' <span class="my-theme-count">(%d)</span>', (int) $term->count );
}

add_filter( 'pikari_gutenberg_query_filter_option_label', 'my_theme_option_label_count', 10, 3 );
```

#### Example: Add a colour swatch from term meta

```php
/**
 * Prefix taxonomy options with a swatch from the term's "color" meta.
 *
 * @param string $html       Label markup.
 * @param array  $option     Filter option.
 * @param array  $attributes Block attributes.
 * @return string
 */
function my_theme_option_label_swatch( string $html, array $option, array $attributes ): string {
    $term = $option['item'] ?? null;

    if ( ! $term instanceof WP_Term ) {
        return $html;
    }

    $color = sanitize_hex_color( get_term_meta( $term->term_id, 'color', true ) );

    if ( ! $color ) {
        return $html;
    }

    return sprintf(
        '<span class="my-theme-swatch" style="background-color: %s"></span>%s',
        esc_attr( $color ),
        $html
    );
}

add_filter( 'pikari_gutenberg_query_filter_option_label', 'my_theme_option_label_swatch', 10, 3 );
```

---

### `pikari_gutenberg_query_filter_sort_options`

Filters the list of options offered by every Sort block, everywhere on the site. The same list also validates the `sort` URL parameter, so no block attributes are passed to this filter: an option that renders must also be an option the query accepts, and the two can't drift apart.

**Parameters:**

| Parameter  | Type      | Description                               |
| ---------- | --------- | ----------------------------------------- |
| `$options` | `array[]` | Sort options. See the option array below. |

**Return:** `array[]` — The options to render and accept.

| Key        | Type     | Description                                                         |
| ---------- | -------- | ------------------------------------------------------------------- |
| `key`      | `string` | Written to and read from the URL. Sanitized with `sanitize_key()`.  |
| `label`    | `string` | Human-readable label shown in the Sort block's `<select>`.          |
| `orderby`  | `string` | A `WP_Query` `orderby` value.                                       |
| `order`    | `string` | `ASC` or `DESC`. Any case is accepted and uppercased automatically. |
| `meta_key` | `string` | Required when `orderby` is `meta_value` or `meta_value_num`.        |

An option missing `key`, `label` or `orderby` is dropped, as is a `meta_value` / `meta_value_num` option with no `meta_key`.

#### Example: Add a "Menu Order" option

```php
/**
 * Add a Menu Order option to every Sort block.
 *
 * @param array[] $options Sort options.
 * @return array[]
 */
function my_theme_add_menu_order_sort( array $options ): array {
    $options[] = array(
        'key'     => 'menu-order-asc',
        'label'   => __( 'Menu Order', 'my-theme' ),
        'orderby' => 'menu_order',
        'order'   => 'ASC',
    );

    return $options;
}

add_filter( 'pikari_gutenberg_query_filter_sort_options', 'my_theme_add_menu_order_sort' );
```

**Things to know:**

- The same list renders every Sort block on the site and validates every sort request. Remove an option here and it also stops being accepted in the URL; there's no way to offer it in one place but not the other.
- A `meta_value` or `meta_value_num` option needs a `meta_key`. Without one, the option is silently dropped.
- A meta-key sort hides posts that don't have that meta key at all — this is WordPress's own query behaviour, not something this filter adds or can work around.
- The block editor's Sort block preview does not call this filter. It always shows the same four built-in options in the editor canvas; only the frontend render and the URL validation follow what you return here.
