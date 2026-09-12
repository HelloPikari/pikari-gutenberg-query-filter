# Pikari Gutenberg Query Filter — Theming & Hooks Reference

How the Query Filter block renders on the frontend, the classes themes can target, and the PHP filters for changing the options and their markup.

---

## Frontend Markup

A taxonomy filter (`category`) with the **Checkbox** display type renders:

```html
<div
	class="wp-block-pikari-gutenberg-query-filter"
	data-wp-interactive="pikari/gutenberg-query-filter"
	data-wp-context="{…}"
>
	<label class="wp-block-pikari-gutenberg-query-filter__label" for="…"
		>Categories</label
	>
	<div class="wp-block-pikari-gutenberg-query-filter__checkbox-group">
		<label
			class="wp-block-pikari-gutenberg-query-filter__checkbox-item category_news"
		>
			<input
				type="checkbox"
				value="news"
				data-wp-on--change="actions.updateFilters"
			/>
			<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text"
				>News</span
			>
		</label>
		<!-- …one <label> per term… -->
	</div>
</div>
```

The **Radio** display type is the same shape with `radio` in place of `checkbox` (`__radio-group`, `__radio-item`, `__radio-text`). Its first option is the "All" choice, which has `value=""` and clears the filter. The **Horizontal** layout adds `has-layout-horizontal` to the group element.

The **Select** display type renders a plain `<select class="wp-block-pikari-gutenberg-query-filter__select">`. Its `<option>` elements get no per-option classes and cannot contain HTML, so the class and label filters below do not apply to it.

| Element                  | Class                                                            |
| ------------------------ | ---------------------------------------------------------------- |
| Block wrapper            | `wp-block-pikari-gutenberg-query-filter`                         |
| Block label              | `wp-block-pikari-gutenberg-query-filter__label`                  |
| Option group             | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-group` |
| Option `<label>`         | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-item`  |
| Option `<label>`, unique | `{key}_{slug}` — see [Option Classes](#option-classes)           |
| Option text              | `wp-block-pikari-gutenberg-query-filter__{radio,checkbox}-text`  |
| Dropdown                 | `wp-block-pikari-gutenberg-query-filter__select`                 |

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

| Key     | Type                                         | Description                                                                                      |
| ------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `value` | `string`                                     | Written to the URL query variable when selected: term slug, post type name, or user ID.          |
| `label` | `string`                                     | Human-readable label. **Not escaped** — escape it when you output it.                            |
| `slug`  | `string`                                     | Used to build the unique class. Optional; falls back to `value`.                                 |
| `item`  | `WP_Term`, `WP_Post_Type`, `WP_User`, `null` | The source object. `null` for the "All" radio. May be missing on options added through a filter. |

## The Block Attributes Array

Each filter also receives the block's `$attributes`:

| Key               | Type      | Values                                                   |
| ----------------- | --------- | -------------------------------------------------------- |
| `filterType`      | `string`  | `post-type`, `taxonomy`, or `author`                     |
| `taxonomy`        | `string`  | Taxonomy name. Set only when `filterType` is `taxonomy`. |
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

An option's `value` is applied to the query as a term slug, post type name, or author ID, depending on the filter type. Options you add must use a value of that kind.

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
- The `<input>` is not part of this markup and cannot be changed. The frontend script relies on its `value` and `data-wp-on--change` attributes.

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

## For Contributors

| What                                  | Where                                                                          |
| ------------------------------------- | ------------------------------------------------------------------------------ |
| Option normalization + options filter | `FilterHelper::get_filter_options()` in `includes/Helpers/FilterHelper.php`    |
| Label classes + classes filter        | `FilterHelper::get_option_classes()`                                           |
| Label markup + label filter           | `FilterHelper::get_option_label_html()`                                        |
| Template                              | `src/blocks/query-filter/render.php`                                           |
| Editor preview class (mirrors PHP)    | `src/utils/option-class-name.js`                                               |
| Tests                                 | `tests/php/FilterHelperTest.php`, `tests/unit/utils/option-class-name.test.js` |

The `{key}_{slug}` format is implemented twice, in PHP and in JS. Change both together.
