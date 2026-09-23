# Query Filter 1.0 — C: Editor UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop the editor writing translated defaults into post content, remove two dead Sort attributes, warn in the editor about filters that merge or render nothing, and make the preview list what the frontend will render.

**Architecture:**

- Label resolution moves into one PHP helper, `FilterHelper::get_label()`, which both `render.php` files call. Missing, empty and whitespace-only labels all resolve to the default.
- Every editor decision that can be written as a pure function is one, in `src/utils/`: which notices apply (`filter-notices.js`) and which REST queries the preview uses (`preview-queries.js`). `edit.js` only wires them up.
- `edit.js` switches to `useEntityRecords`, so it can tell "still loading" from "loaded and empty". It reuses `FilterInspectorControls` instead of its inline copy.

**Tech Stack:** PHP 8.4, PHPUnit 9.6 with Brain\Monkey; Jest with `@wordpress/scripts` (jsdom); the WordPress 7.1 block editor (`@wordpress/block-editor`, `@wordpress/core-data`, `@wordpress/compose`, `@wordpress/components`).

**Spec:** `docs/superpowers/specs/2026-09-23-query-filter-1.0-c-d-design.md`, approved 2026-09-23. This plan implements §4, the C parts of §5, and the C row of §7. D has its own plan.

## Global Constraints

- **Branch:** `feature/editor-ux`, cut from `main` at `cad45bc`, where B3 is merged and the plugin version is 1.0.0, unpublished. The spec and this plan are already committed on it.
- **Label:** `feature`. The autolabeler applies it from the branch name. `main` is already at 1.0.0, so the version isn't affected.
- **Nothing is published.** 1.0.0 ships in sub-project E (todo #529).
- **Code style:**
  - PHP follows WordPress Coding Standards with **4 spaces, not tabs**. `phpcs.xml` skips `tests/`, so check each new or edited PHP test with `grep -c $'\t' <file>`, which must print 0.
  - JavaScript is formatted by ESLint, with tabs. `npm run lint:js` only covers `src/`, so run `npx wp-scripts lint-js <file>` on each new or edited file under `tests/unit/` yourself.
- **Text domain** is `pikari-gutenberg-query-filter`. Every user-facing string is translated.
- **Commits:** `type: Brief description`. No `Co-Authored-By` or "Generated with" trailers. Never `--no-verify`.
- **TDD, with a mutation step:**
  - Every behaviour change starts with a failing test, and you must watch it fail for the right reason.
  - Once a test passes, **mutate the production line it covers** (delete it, or invert the condition) and confirm the test fails, then restore.
  - A test that survives its own mutation is not a test. In B3, five plan-supplied tests could not fail. Each task below names its mutation.
- **Environment:** this plugin's wp-env only, on ports 5884 (development) and 5885 (tests). Never touch other Docker containers or ddev sites, and never run `wp-env destroy` or `wp-env clean`.
- **Suites at the start:** PHP **232**, Jest **78**, both green. Both must be green at the end of every task. C doesn't touch Playwright, but run it once in Task 6.

## Push back on this plan

It's argued from the spec and from reading WordPress 7.1's bundles, not from a run. B3's plan was wrong in eight places, and each was caught by an implementer or reviewer, never by the plan. If a task's code doesn't do what its prose says, say so and rule on it; don't transcribe it.

## Facts established while writing this plan

Verified in `~/.wp-env/*/WordPress/wp-includes/`, WP 7.1.1. Do not re-derive them.

- **REST terms ignore `number`.** `WP_REST_Terms_Controller` maps `per_page → number` and doesn't register `number` itself. So today's preview query `{ number: 50 }` is silently dropped, and the preview gets REST's default of 10 terms, **including empty ones**. The frontend (`FilterHelper::get_taxonomy_filter_terms()`) shows up to 100, `hide_empty => true`.
- **REST users accept `has_published_posts: true`**, with no permission check. `who: 'authors'` returns anyone who _can_ author, including people with no published posts, so the preview listed authors the frontend (`AuthorHelper`, `has_published_posts => true`) never renders.
- **Author preview for Editor-role users.** core-data's `root/user` entity has `baseURLParams: { context: 'edit' }`, and REST refuses `context=edit` on users without `list_users` (`rest_forbidden_context`). So **for any user below Administrator, the author preview has been empty.** A query's own args override `baseURLParams` (`{ ...entityConfig.baseURLParams, ...query }` in core-data), so `context: 'view'` fixes it. `view` includes `id`, `name` and `slug`, which is all the preview uses.
- **`useEntityRecords( kind, name, queryArgs, { enabled } )`** returns `records: EMPTY_ARRAY` when `enabled` is false, and `records: null` with `hasResolved: false` while loading.
- **`getBlockParentsByBlockName( clientId, blockName, ascending )`** with `ascending = true` lists the nearest parent first. **`getClientIdsOfDescendants( rootIds )`** takes one id or an array.
- **An empty `label` currently renders an empty `<legend>`.** Both `render.php` files use `$attributes['label'] ?? $default`, so `label: ""` (what `TextControl` stores when a user clears the field) prints an empty label, while the editor's `label || getDefaultLabel()` shows the default. The help text "If empty then no label will be shown" was therefore true of the frontend, and it's an accessibility failure: a nameless group. **Ruling: empty means default everywhere.** Hiding the label is what the Show Label toggle is for, and it keeps the text for screen readers. This goes one step beyond spec §4.4, which called the help text "false"; the frontend is what changes.

## File Structure

| File                                                         | Responsibility                                                                  |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| `includes/Helpers/FilterHelper.php` (modify)                 | Adds `get_label()`: the one place a block's label text is resolved.             |
| `src/blocks/query-filter/render.php` (modify)                | Calls `get_label()`.                                                            |
| `src/blocks/sort/render.php` (modify)                        | Calls `get_label()`.                                                            |
| `src/blocks/query-filter/variations.js` (modify)             | No `label` in any variation.                                                    |
| `src/blocks/sort/block.json` (modify)                        | Drops `width`, `widthUnit`.                                                     |
| `src/utils/filter-notices.js` (create)                       | Pure: which notices apply to a filter, their messages, and duplicate detection. |
| `src/utils/preview-queries.js` (create)                      | The REST query args for the term and author previews.                           |
| `src/components/FilterInspectorControls.js` (modify)         | `placeholder` instead of the bogus `defaultValue`; the corrected help text.     |
| `src/blocks/query-filter/edit.js` (rewrite)                  | Wiring only: data, notices, preview.                                            |
| `src/blocks/sort/edit.js` (modify)                           | `useInstanceId()`; empty label means default.                                   |
| `tests/php/FilterHelperTest.php` (modify)                    | `get_label()`.                                                                  |
| `tests/unit/blocks/query-filter/variations.test.js` (create) | No stored label.                                                                |
| `tests/unit/blocks/block-metadata.test.js` (modify)          | Sort's dead attributes are gone.                                                |
| `tests/unit/utils/filter-notices.test.js` (create)           | Every notice condition, and duplicate detection including nested loops.         |
| `tests/unit/utils/preview-queries.test.js` (create)          | The preview queries match the frontend's.                                       |

---

### Task 1: One label resolver for both blocks

**Files:**

- Modify: `includes/Helpers/FilterHelper.php` (add after `get_all_option()`)
- Modify: `src/blocks/query-filter/render.php` (the `$label_text` line)
- Modify: `src/blocks/sort/render.php:35`
- Test: `tests/php/FilterHelperTest.php`

**Interfaces:**

- Produces: `FilterHelper::get_label( array $attributes, string $default_label ): string`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/FilterHelperTest.php`, next to the `get_all_option` test:

```php
    public function test_get_label_returns_the_stored_label(): void {
        $this->assertSame( 'Topics', FilterHelper::get_label( array( 'label' => 'Topics' ), 'Category' ) );
    }

    public function test_get_label_falls_back_when_no_label_is_stored(): void {
        $this->assertSame( 'Category', FilterHelper::get_label( array(), 'Category' ) );
    }

    /*
     * A cleared Label field stores "". It must not print an empty <legend>:
     * hiding the label is what showLabel is for.
     */
    public function test_get_label_falls_back_for_an_empty_label(): void {
        $this->assertSame( 'Category', FilterHelper::get_label( array( 'label' => '' ), 'Category' ) );
    }

    public function test_get_label_falls_back_for_a_whitespace_label(): void {
        $this->assertSame( 'Category', FilterHelper::get_label( array( 'label' => '   ' ), 'Category' ) );
    }

    public function test_get_label_ignores_a_non_string_label(): void {
        $this->assertSame( 'Category', FilterHelper::get_label( array( 'label' => array( 'x' ) ), 'Category' ) );
    }
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit --filter test_get_label`
Expected: 5 errors, "Call to undefined method … get_label()".

- [ ] **Step 3: Implement**

In `includes/Helpers/FilterHelper.php`, after `get_all_option()`:

```php
    /**
     * Get the text for a block's label or legend.
     *
     * An empty or whitespace-only label means "use the default", as it does
     * in the editor. A label is hidden with showLabel, which keeps it for
     * screen readers, never by leaving it empty.
     *
     * @param array  $attributes    Block attributes.
     * @param string $default_label Translated default for this block.
     * @return string Label text.
     */
    public static function get_label( array $attributes, string $default_label ): string {
        $label = $attributes['label'] ?? '';

        return is_string( $label ) && '' !== trim( $label ) ? $label : $default_label;
    }
```

In `src/blocks/query-filter/render.php`, replace:

```php
$label_text    = $attributes['label'] ?? $default_label;
```

with:

```php
$label_text    = FilterHelper::get_label( $attributes, $default_label );
```

In `src/blocks/sort/render.php`, add `use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;` to the `use` block at the top, and replace line 35:

```php
$label_text  = $attributes['label'] ?? __( 'Sort By', 'pikari-gutenberg-query-filter' );
```

with:

```php
$label_text  = FilterHelper::get_label( $attributes, __( 'Sort By', 'pikari-gutenberg-query-filter' ) );
```

- [ ] **Step 4: Run the tests to verify they pass, then mutate**

Run: `vendor/bin/phpunit --filter test_get_label`, which should pass. Then:

- Mutation 1: change the body to `return $attributes['label'] ?? $default_label;`. The empty and whitespace tests must fail.
- Mutation 2: remove `is_string( $label ) &&`. The non-string test must error.
- Restore after each.

Run: `composer test`
Expected: `OK (237 tests, …)`.

- [ ] **Step 5: Lint and commit**

```bash
npm run lint:php && grep -c $'\t' tests/php/FilterHelperTest.php   # must print 0
git add includes/Helpers/FilterHelper.php src/blocks/query-filter/render.php src/blocks/sort/render.php tests/php/FilterHelperTest.php
git commit -m "fix: Treat an empty filter label as the default label"
```

---

### Task 2: Stop storing a translated label, and drop Sort's dead attributes

**Files:**

- Modify: `src/blocks/query-filter/variations.js`
- Modify: `src/blocks/sort/block.json`
- Create: `tests/unit/blocks/query-filter/variations.test.js`
- Modify: `tests/unit/blocks/block-metadata.test.js`

**Interfaces:** none; the attributes' own shape is the contract.

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/blocks/query-filter/variations.test.js`:

```js
import variations from '../../../../src/blocks/query-filter/variations';

describe('query-filter variations', () => {
	// A stored label freezes the editor's language into the post and goes
	// stale when the taxonomy changes. The default is resolved at render.
	it.each(variations.map((variation) => [variation.name, variation]))(
		'should not store a label in the %s variation',
		(name, variation) => {
			expect(variation.attributes).not.toHaveProperty('label');
		}
	);

	it('should still set one filter type per variation', () => {
		expect(
			variations.map((variation) => variation.attributes.filterType)
		).toEqual(['post-type', 'taxonomy', 'author']);
	});
});
```

In `tests/unit/blocks/block-metadata.test.js`, add inside the top-level `describe`, after the Sort view-module test:

```js
// Nothing ever read them. Removing an attribute is breaking, so it
// happens before 1.0 or never.
it('should not declare the unused width attributes on the Sort block', () => {
	const { attributes } = readMetadata('sort');

	expect(attributes).not.toHaveProperty('width');
	expect(attributes).not.toHaveProperty('widthUnit');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npm test -- --testPathPattern='variations|block-metadata'`
Expected: 3 "label" failures and the width failure. The filter-type test passes already, which is fine: it guards Step 3 against deleting too much.

- [ ] **Step 3: Implement**

In `src/blocks/query-filter/variations.js`, delete the `label: __( … ),` line from each of the three `attributes` objects, leaving only `filterType`.

In `src/blocks/sort/block.json`, delete the `width` and `widthUnit` entries from `attributes`, keeping the JSON valid (no trailing comma after `showLabel`).

- [ ] **Step 4: Run to verify they pass, then mutate**

Run: `npm test -- --testPathPattern='variations|block-metadata'`, which should pass.

Mutation: put `label: 'x'` back in one variation. Exactly one "should not store a label" case must fail. Restore.

Run: `npm test`
Expected: all pass, with 83 tests.

- [ ] **Step 5: Lint and commit**

```bash
npx wp-scripts lint-js tests/unit/blocks/query-filter/variations.test.js tests/unit/blocks/block-metadata.test.js && npm run lint:js
git add src/blocks/query-filter/variations.js src/blocks/sort/block.json tests/unit/blocks/query-filter/variations.test.js tests/unit/blocks/block-metadata.test.js
git commit -m "fix: Stop storing default labels and drop unused Sort attributes"
```

---

### Task 3: Notice logic

**Files:**

- Create: `src/utils/filter-notices.js`
- Create: `tests/unit/utils/filter-notices.test.js`

**Interfaces:**

- Produces:

  - `NOTICE` — `{ DUPLICATE: 'duplicate', NO_TAXONOMY: 'no-taxonomy', NO_TERMS: 'no-terms', NO_AUTHORS: 'no-authors' }`.
  - `RENDERS_NOTHING` — `[ NOTICE.NO_TAXONOMY, NOTICE.NO_TERMS, NOTICE.NO_AUTHORS ]`.
  - `getFilterNotices( attributes, { hasDuplicate = false, optionsResolved = false, optionCount = 0 } = {} ): string[]` — notice keys, in display order.
  - `getNoticeMessage( key: string ): string` — the translated message.
  - `hasDuplicateFilter( selectors, clientId: string ): boolean` — `selectors` is the `core/block-editor` store's selectors, or any object with `getBlockName`, `getBlockAttributes`, `getBlockParentsByBlockName` and `getClientIdsOfDescendants`.

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/utils/filter-notices.test.js`:

```js
import {
	NOTICE,
	RENDERS_NOTHING,
	getFilterNotices,
	getNoticeMessage,
	hasDuplicateFilter,
} from '../../../src/utils/filter-notices';

const FILTER = 'pikari-gutenberg-query-filter/query-filter';

/**
 * Block-editor selectors over a fixed tree of { name, parent, attrs }.
 *
 * @param {Object} tree Blocks keyed by client ID.
 * @return {Object} The four selectors hasDuplicateFilter() uses.
 */
const selectorsFor = (tree) => {
	const ancestors = (id) => {
		const list = [];
		for (let p = tree[id].parent; p; p = tree[p].parent) {
			list.push(p);
		}
		return list;
	};

	return {
		getBlockName: (id) => tree[id].name,
		getBlockAttributes: (id) => tree[id].attrs,
		getBlockParentsByBlockName: (id, name, ascending = false) => {
			const matches = ancestors(id).filter((p) => tree[p].name === name);
			return ascending ? matches : matches.reverse();
		},
		getClientIdsOfDescendants: (root) =>
			Object.keys(tree).filter((id) => ancestors(id).includes(root)),
	};
};

const filter = (parent, attrs) => ({ name: FILTER, parent, attrs });
const category = { filterType: 'taxonomy', taxonomy: 'category' };

describe('hasDuplicateFilter', () => {
	const tree = {
		loop: { name: 'core/query', parent: null },
		group: { name: 'core/group', parent: 'loop' },
		first: filter('loop', category),
		second: filter('group', category),
		tags: filter('loop', { filterType: 'taxonomy', taxonomy: 'post_tag' }),
		author: filter('loop', { filterType: 'author' }),
		// A nested loop is a separate loop with its own parameters.
		inner: { name: 'core/query', parent: 'loop' },
		innerTags: filter('inner', {
			filterType: 'taxonomy',
			taxonomy: 'post_tag',
		}),
		innerAuthor: filter('inner', { filterType: 'author' }),
		sort: {
			name: 'pikari-gutenberg-query-filter/sort',
			parent: 'loop',
			attrs: {},
		},
		orphan: filter(null, category),
	};
	const selectors = selectorsFor(tree);

	it('should flag two filters on the same taxonomy in one loop, at any depth', () => {
		expect(hasDuplicateFilter(selectors, 'first')).toBe(true);
		expect(hasDuplicateFilter(selectors, 'second')).toBe(true);
	});

	it('should not flag a filter on a different taxonomy', () => {
		expect(hasDuplicateFilter(selectors, 'tags')).toBe(false);
	});

	it('should not count filters in a nested loop against the outer loop', () => {
		expect(hasDuplicateFilter(selectors, 'author')).toBe(false);
		expect(hasDuplicateFilter(selectors, 'innerTags')).toBe(false);
		expect(hasDuplicateFilter(selectors, 'innerAuthor')).toBe(false);
	});

	it('should not flag a filter outside any Query Loop', () => {
		expect(hasDuplicateFilter(selectors, 'orphan')).toBe(false);
	});

	it('should flag two post type or two author filters', () => {
		const pair = selectorsFor({
			loop: { name: 'core/query', parent: null },
			a: filter('loop', { filterType: 'post-type' }),
			b: filter('loop', { filterType: 'post-type' }),
			c: filter('loop', { filterType: 'author' }),
			d: filter('loop', { filterType: 'author' }),
		});

		expect(hasDuplicateFilter(pair, 'a')).toBe(true);
		expect(hasDuplicateFilter(pair, 'c')).toBe(true);
	});

	it('should not flag two taxonomy filters that have no taxonomy yet', () => {
		const unset = selectorsFor({
			loop: { name: 'core/query', parent: null },
			a: filter('loop', { filterType: 'taxonomy' }),
			b: filter('loop', { filterType: 'taxonomy' }),
		});

		expect(hasDuplicateFilter(unset, 'a')).toBe(false);
	});
});

describe('getFilterNotices', () => {
	it('should return nothing for a working filter', () => {
		expect(
			getFilterNotices(category, { optionsResolved: true, optionCount: 3 })
		).toEqual([]);
	});

	it('should report a duplicate first', () => {
		expect(
			getFilterNotices({ filterType: 'taxonomy' }, { hasDuplicate: true })
		).toEqual([NOTICE.DUPLICATE, NOTICE.NO_TAXONOMY]);
	});

	it('should report a taxonomy filter with no taxonomy', () => {
		expect(getFilterNotices({ filterType: 'taxonomy' })).toEqual([
			NOTICE.NO_TAXONOMY,
		]);
	});

	it('should report no terms only once the terms have loaded', () => {
		expect(getFilterNotices(category, { optionsResolved: false })).toEqual([]);
		expect(
			getFilterNotices(category, { optionsResolved: true, optionCount: 0 })
		).toEqual([NOTICE.NO_TERMS]);
	});

	it('should report no authors only once the authors have loaded', () => {
		const author = { filterType: 'author' };

		expect(getFilterNotices(author, { optionsResolved: false })).toEqual([]);
		expect(
			getFilterNotices(author, { optionsResolved: true, optionCount: 0 })
		).toEqual([NOTICE.NO_AUTHORS]);
	});

	it('should not report empty options for a post type filter', () => {
		expect(
			getFilterNotices(
				{ filterType: 'post-type' },
				{ optionsResolved: true, optionCount: 0 }
			)
		).toEqual([]);
	});
});

describe('getNoticeMessage', () => {
	it.each(Object.values(NOTICE))('should have a message for %s', (key) => {
		expect(getNoticeMessage(key)).toEqual(expect.any(String));
		expect(getNoticeMessage(key).length).toBeGreaterThan(0);
	});

	it('should treat every notice except a duplicate as rendering nothing', () => {
		expect(RENDERS_NOTHING).toEqual([
			NOTICE.NO_TAXONOMY,
			NOTICE.NO_TERMS,
			NOTICE.NO_AUTHORS,
		]);
	});
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `npm test -- --testPathPattern=filter-notices`
Expected: FAIL with "Cannot find module '../../../src/utils/filter-notices'".

- [ ] **Step 3: Implement**

Create `src/utils/filter-notices.js`:

```js
import { __ } from '@wordpress/i18n';

export const NOTICE = {
	DUPLICATE: 'duplicate',
	NO_TAXONOMY: 'no-taxonomy',
	NO_TERMS: 'no-terms',
	NO_AUTHORS: 'no-authors',
};

// Notices whose filter renders nothing at all on the frontend.
export const RENDERS_NOTHING = [
	NOTICE.NO_TAXONOMY,
	NOTICE.NO_TERMS,
	NOTICE.NO_AUTHORS,
];

/**
 * Whether two filters write the same URL parameter.
 *
 * @param {Object} a Block attributes.
 * @param {Object} b Block attributes.
 * @return {boolean} True when they share a parameter.
 */
const isSameFilter = (a, b) =>
	a.filterType === b.filterType &&
	(a.filterType !== 'taxonomy' || (!!a.taxonomy && a.taxonomy === b.taxonomy));

/**
 * Whether another filter in this block's Query Loop writes the same URL
 * parameter. Two such filters behave as one control: radios merge into a
 * single group across both blocks (spec C/D §4.2).
 *
 * @param {Object} selectors core/block-editor selectors.
 * @param {string} clientId  This block's client ID.
 * @return {boolean} True when a duplicate exists.
 */
export function hasDuplicateFilter(selectors, clientId) {
	const {
		getBlockName,
		getBlockAttributes,
		getBlockParentsByBlockName,
		getClientIdsOfDescendants,
	} = selectors;

	const nearestLoop = (id) =>
		getBlockParentsByBlockName(id, 'core/query', true)[0];

	const loop = nearestLoop(clientId);
	if (!loop) {
		return false;
	}

	const name = getBlockName(clientId);
	const attributes = getBlockAttributes(clientId);

	return getClientIdsOfDescendants(loop).some(
		(id) =>
			id !== clientId &&
			getBlockName(id) === name &&
			// A nested loop has its own parameters.
			nearestLoop(id) === loop &&
			isSameFilter(attributes, getBlockAttributes(id))
	);
}

/**
 * The editor notices that apply to a filter, in display order.
 *
 * @param {Object}  attributes              Block attributes.
 * @param {Object}  state                   What the editor knows.
 * @param {boolean} state.hasDuplicate      From hasDuplicateFilter().
 * @param {boolean} state.optionsResolved   Whether the preview's options have loaded.
 * @param {number}  state.optionCount       How many options loaded.
 * @return {string[]} NOTICE values.
 */
export function getFilterNotices(
	{ filterType, taxonomy },
	{ hasDuplicate = false, optionsResolved = false, optionCount = 0 } = {}
) {
	const notices = [];

	if (hasDuplicate) {
		notices.push(NOTICE.DUPLICATE);
	}

	if (filterType === 'taxonomy' && !taxonomy) {
		notices.push(NOTICE.NO_TAXONOMY);
	} else if (optionsResolved && optionCount === 0) {
		if (filterType === 'taxonomy') {
			notices.push(NOTICE.NO_TERMS);
		} else if (filterType === 'author') {
			notices.push(NOTICE.NO_AUTHORS);
		}
	}

	return notices;
}

/**
 * The message for a notice.
 *
 * @param {string} key A NOTICE value.
 * @return {string} Translated message.
 */
export function getNoticeMessage(key) {
	return {
		[NOTICE.DUPLICATE]: __(
			'Another filter in this Query Loop filters by the same thing. Both use one URL parameter, so they act as one control. Remove one of them.',
			'pikari-gutenberg-query-filter'
		),
		[NOTICE.NO_TAXONOMY]: __(
			'Choose a taxonomy. This filter displays nothing until you do.',
			'pikari-gutenberg-query-filter'
		),
		[NOTICE.NO_TERMS]: __(
			'This taxonomy has no terms with posts yet, so this filter displays nothing.',
			'pikari-gutenberg-query-filter'
		),
		[NOTICE.NO_AUTHORS]: __(
			'No authors have published posts yet, so this filter displays nothing.',
			'pikari-gutenberg-query-filter'
		),
	}[key];
}
```

- [ ] **Step 4: Run to verify they pass, then mutate**

Run: `npm test -- --testPathPattern=filter-notices`, which should pass. Then:

- Mutation 1: delete the `nearestLoop( id ) === loop &&` line. The nested-loop test must fail, because `author` would match `innerAuthor`.
- Mutation 2: delete `!! a.taxonomy &&`. The no-taxonomy-yet test must fail.
- Mutation 3: delete `optionsResolved &&`. Both "only once loaded" tests must fail.
- Restore after each.

- [ ] **Step 5: Lint and commit**

```bash
npx wp-scripts lint-js tests/unit/utils/filter-notices.test.js && npm run lint:js
git add src/utils/filter-notices.js tests/unit/utils/filter-notices.test.js
git commit -m "feat: Add editor notice logic for duplicate and empty filters"
```

---

### Task 4: Preview queries that match the frontend

**Files:**

- Create: `src/utils/preview-queries.js`
- Create: `tests/unit/utils/preview-queries.test.js`

**Interfaces:**

- Produces: `TERM_PREVIEW_QUERY` and `AUTHOR_PREVIEW_QUERY`, plain objects passed as `useEntityRecords` query args.

- [ ] **Step 1: Write the failing tests**

Create `tests/unit/utils/preview-queries.test.js`:

```js
import {
	AUTHOR_PREVIEW_QUERY,
	TERM_PREVIEW_QUERY,
} from '../../../src/utils/preview-queries';

// Each query must ask REST for what the frontend helper renders. The keys
// are REST's, not get_terms()' or WP_User_Query's: REST ignores `number`.
describe('preview queries', () => {
	it('should match FilterHelper::get_taxonomy_filter_terms()', () => {
		expect(TERM_PREVIEW_QUERY).toEqual({
			per_page: 100,
			hide_empty: true,
		});
	});

	it('should match AuthorHelper::get_filter_authors(), readable below Administrator', () => {
		expect(AUTHOR_PREVIEW_QUERY).toEqual({
			per_page: 100,
			has_published_posts: true,
			context: 'view',
		});
	});
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `npm test -- --testPathPattern=preview-queries`
Expected: FAIL, "Cannot find module".

- [ ] **Step 3: Implement**

Create `src/utils/preview-queries.js`:

```js
/*
 * REST query args for the editor preview, so it lists what render.php will.
 * Keep in step with FilterHelper::get_taxonomy_filter_terms() and
 * AuthorHelper::get_filter_authors().
 */

export const TERM_PREVIEW_QUERY = {
	per_page: 100,
	hide_empty: true,
};

export const AUTHOR_PREVIEW_QUERY = {
	per_page: 100,
	has_published_posts: true,
	// core-data requests users with context=edit, which REST refuses to
	// anyone without list_users, leaving Editors with an empty preview.
	context: 'view',
};
```

- [ ] **Step 4: Run to verify they pass, then mutate**

Run: `npm test -- --testPathPattern=preview-queries`, which should pass. Mutation: change `per_page` to `number` in `TERM_PREVIEW_QUERY`, the bug this replaces. The test must fail. Restore.

These tests pin constants, which is only worth doing because each constant encodes a regression: mutating one back to the old bug must fail. The real check is the browser pass in Task 5.

- [ ] **Step 5: Lint and commit**

```bash
npx wp-scripts lint-js tests/unit/utils/preview-queries.test.js && npm run lint:js
git add src/utils/preview-queries.js tests/unit/utils/preview-queries.test.js
git commit -m "fix: Query the editor preview the way the frontend does"
```

---

### Task 5: Wire it into the editor

The editor components have no unit tests and this task doesn't add a render harness: everything decidable is already tested in Tasks 3–4. Its gate is the browser pass in Step 5, and **that pass is not optional**.

**Files:**

- Rewrite: `src/blocks/query-filter/edit.js`
- Modify: `src/blocks/sort/edit.js`
- Modify: `src/components/FilterInspectorControls.js`

**Interfaces:**

- Consumes: `getFilterNotices`, `getNoticeMessage`, `hasDuplicateFilter`, `RENDERS_NOTHING` (Task 3); `TERM_PREVIEW_QUERY`, `AUTHOR_PREVIEW_QUERY` (Task 4).

- [ ] **Step 1: Fix `FilterInspectorControls`**

In `src/components/FilterInspectorControls.js`, in the Label `TextControl`:

- replace `defaultValue={ defaultLabel }` with `placeholder={ defaultLabel }`. `TextControl` is controlled, so `defaultValue` did nothing;
- replace the help string with `__( 'Leave empty to use the default label.', 'pikari-gutenberg-query-filter' )`.

- [ ] **Step 2: Rewrite `src/blocks/query-filter/edit.js`**

Replace the whole file with:

```js
/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	Warning,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import classNames from 'classnames';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEntityRecords } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import FilterInput from '../../components/FilterInput';
import FilterInspectorControls from '../../components/FilterInspectorControls';
import getOptionClassName from '../../utils/option-class-name';
import {
	RENDERS_NOTHING,
	getFilterNotices,
	getNoticeMessage,
	hasDuplicateFilter,
} from '../../utils/filter-notices';
import {
	AUTHOR_PREVIEW_QUERY,
	TERM_PREVIEW_QUERY,
} from '../../utils/preview-queries';

export default function Edit({ attributes, setAttributes, context, clientId }) {
	const {
		filterType,
		taxonomy,
		emptyLabel,
		label,
		showLabel,
		displayType,
		layoutDirection,
	} = attributes;

	const id = useInstanceId(Edit, 'query-filter');

	// An inherited loop's post types come from the page's main query, which
	// the editor can't see; context.query.postType is only the default.
	const isInherited = !!context.query?.inherit;

	const allPostTypes = useSelect(
		(select) => {
			if (filterType !== 'post-type') {
				return [];
			}
			return (select('core').getPostTypes({ per_page: 100 }) || []).filter(
				(type) => type.viewable
			);
		},
		[filterType]
	);

	const taxonomies = useSelect(
		(select) => {
			if (filterType !== 'taxonomy') {
				return [];
			}
			return (select('core').getTaxonomies({ per_page: 100 }) || []).filter(
				(tax) => tax.visibility.publicly_queryable
			);
		},
		[filterType]
	);

	// A new taxonomy filter starts on the first taxonomy. Only the taxonomy
	// is stored; the label default is resolved at render.
	useEffect(() => {
		if (filterType === 'taxonomy' && !taxonomy && taxonomies.length) {
			setAttributes({ taxonomy: taxonomies[0].slug });
		}
	}, [filterType, taxonomy, taxonomies, setAttributes]);

	const { records: terms, hasResolved: termsResolved } = useEntityRecords(
		'taxonomy',
		taxonomy || '',
		TERM_PREVIEW_QUERY,
		{ enabled: filterType === 'taxonomy' && !!taxonomy }
	);

	const { records: authors, hasResolved: authorsResolved } = useEntityRecords(
		'root',
		'user',
		AUTHOR_PREVIEW_QUERY,
		{
			enabled: filterType === 'author',
		}
	);

	const hasDuplicate = useSelect(
		(select) => hasDuplicateFilter(select(blockEditorStore), clientId),
		[clientId]
	);

	let contextPostTypes = [];
	if (filterType === 'post-type' && context.query && !isInherited) {
		contextPostTypes = (context.query.postType || '')
			.split(',')
			.map((type) => type.trim());

		// Support for enhanced query loop block plugin
		if (Array.isArray(context.query.multiple_posts)) {
			contextPostTypes = contextPostTypes.concat(context.query.multiple_posts);
		}
	}

	const postTypes = contextPostTypes.map(
		(postType) =>
			allPostTypes.find((type) => type.slug === postType) || {
				slug: postType,
				name: postType,
			}
	);

	const getDefaultLabel = () => {
		switch (filterType) {
			case 'post-type':
				return __('Content Type', 'pikari-gutenberg-query-filter');
			case 'taxonomy':
				return (
					taxonomies.find((tax) => tax.slug === taxonomy)?.name ||
					__('Filter by', 'pikari-gutenberg-query-filter')
				);
			case 'author':
				return __('Author', 'pikari-gutenberg-query-filter');
			default:
				return __('Filter', 'pikari-gutenberg-query-filter');
		}
	};

	// Normalize preview items, like FilterHelper::get_filter_options().
	const previewOptions =
		{
			'post-type': postTypes.map((postType) => ({
				key: postType.slug,
				value: postType.slug,
				label: postType.name,
				slug: postType.slug,
			})),
			taxonomy: (terms || []).map((term) => ({
				key: term.id,
				value: term.slug,
				label: term.name,
				slug: term.slug,
			})),
			author: (authors || []).map((author) => ({
				key: author.id,
				value: author.slug,
				label: author.name,
				slug: author.slug,
			})),
		}[filterType] || [];

	const notices = getFilterNotices(attributes, {
		hasDuplicate,
		optionsResolved:
			{ taxonomy: termsResolved, author: authorsResolved }[filterType] ?? false,
		optionCount: previewOptions.length,
	});
	const blankNotices = notices.filter((key) => RENDERS_NOTHING.includes(key));

	const blockProps = useBlockProps({
		className: classNames('wp-block-pikari-gutenberg-query-filter', {
			'has-layout-horizontal': layoutDirection === 'horizontal',
		}),
	});

	const labelClassName = classNames(
		'wp-block-pikari-gutenberg-query-filter__label',
		{
			'screen-reader-text': !showLabel,
		}
	);
	// Mirrors FilterHelper::get_label().
	const labelText = label?.trim() ? label : getDefaultLabel();
	const inheritedNote = __(
		"Options come from the page's query.",
		'pikari-gutenberg-query-filter'
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Filter Settings', 'pikari-gutenberg-query-filter')}
				>
					{notices.map((key) => (
						<Notice key={key} status="warning" isDismissible={false}>
							{getNoticeMessage(key)}
						</Notice>
					))}
					{filterType === 'post-type' && isInherited && (
						<Notice status="info" isDismissible={false}>
							{inheritedNote}
						</Notice>
					)}
					{filterType === 'taxonomy' && (
						<SelectControl
							label={__('Taxonomy', 'pikari-gutenberg-query-filter')}
							value={taxonomy || ''}
							options={[
								{
									label: __(
										'Select a taxonomy',
										'pikari-gutenberg-query-filter'
									),
									value: '',
								},
								...taxonomies.map((tax) => ({
									label: tax.name,
									value: tax.slug,
								})),
							]}
							onChange={(newTaxonomy) =>
								setAttributes({ taxonomy: newTaxonomy })
							}
						/>
					)}
					<FilterInspectorControls
						attributes={attributes}
						setAttributes={setAttributes}
						defaultLabel={getDefaultLabel()}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{blankNotices.length > 0 && (
					<Warning>{blankNotices.map(getNoticeMessage).join(' ')}</Warning>
				)}

				{!blankNotices.length && displayType === 'select' && (
					<>
						<label className={labelClassName} htmlFor={id}>
							{labelText}
						</label>
						<select
							className="wp-block-pikari-gutenberg-query-filter__select"
							id={id}
							disabled
						>
							<option value="">
								{emptyLabel || __('All', 'pikari-gutenberg-query-filter')}
							</option>
							{previewOptions.map((option) => (
								<option key={option.key} value={option.value}>
									{option.label}
								</option>
							))}
						</select>
					</>
				)}

				{!blankNotices.length &&
					(displayType === 'radio' || displayType === 'checkbox') && (
						<fieldset className="wp-block-pikari-gutenberg-query-filter__fieldset">
							<legend className={labelClassName}>{labelText}</legend>
							<div
								className={classNames(
									`wp-block-pikari-gutenberg-query-filter__${displayType}-group`,
									{
										'has-layout-horizontal': layoutDirection === 'horizontal',
									}
								)}
							>
								{displayType === 'radio' && (
									<FilterInput
										type="radio"
										disabled
										checked
										className={getOptionClassName(attributes, 'all')}
									>
										{emptyLabel || __('All', 'pikari-gutenberg-query-filter')}
									</FilterInput>
								)}
								{previewOptions.slice(0, 3).map((option) => (
									<FilterInput
										key={option.key}
										type={displayType}
										disabled
										className={getOptionClassName(attributes, option.slug)}
									>
										{option.label}
									</FilterInput>
								))}
							</div>
						</fieldset>
					)}

				{!blankNotices.length && filterType === 'post-type' && isInherited && (
					<p>{inheritedNote}</p>
				)}
			</div>
		</>
	);
}
```

Deliberate changes from the old file, which reviewers should check against this list:

- the inline Display Type, Layout, Label, Show Label and Empty Choice Label controls are replaced by `FilterInspectorControls`;
- `setAttributes` has moved out of the `useSelect` selector into `useEffect`, without `label`;
- the terms and authors lookups now use `useEntityRecords` with the Task 4 queries;
- `Math.random()` is replaced by `useInstanceId`;
- notices, the canvas `Warning` and the inherited note are new;
- `labelText` treats whitespace as empty.

- [ ] **Step 3: Update `src/blocks/sort/edit.js`**

- Import `useInstanceId` from `@wordpress/compose` and replace the `Math.random()` line with `const id = useInstanceId( Edit, 'sort' );`.
- Replace `{ label || __( 'Sort By', 'pikari-gutenberg-query-filter' ) }` with `{ label?.trim() ? label : __( 'Sort By', 'pikari-gutenberg-query-filter' ) }`.

- [ ] **Step 4: Build, lint, test**

```bash
npm run build && npm run lint:js && npm test && composer test
```

Expected: the build succeeds, with `@wordpress/compose` and `@wordpress/core-data` in `build/blocks/query-filter/index.asset.php`'s dependencies; lint is clean; Jest has 83 + Task 3 + Task 4 tests, all passing; PHP has 237.

- [ ] **Step 5: Browser pass (required)**

Start the plugin's wp-env (`npm run wp-env`) and log in at `http://localhost:5884/wp-admin` (`admin` / `password`). Use Playwright MCP, and take a screenshot of each item. On a new page with a Query Loop:

1. Insert each of the three variations. In the Code editor, confirm none of the three block comments contains `"label"`, and the taxonomy one contains `"taxonomy":"category"`.
2. Change the taxonomy filter to Tags. The legend follows ("Tags"), and still no `label` is stored.
3. Type a label, then clear it. The legend returns to the default. Save, view the page, and confirm the frontend legend is the default too (Task 1).
4. Add a second Category filter to the same loop. Both blocks show the duplicate notice. Change one to Tags, and both notices clear.
5. Pick a taxonomy whose terms all have a count of 0 (check with `npx wp-env run cli wp term list post_tag --fields=slug,count`; Tags usually qualifies on the dev site). The canvas shows the `Warning` and the inspector shows the "no terms" notice.
6. Compare the Category preview with the frontend: the same terms, with no empty ones. With more than 10 categories, the preview shows more than 10. (Create them with `npx wp-env run cli wp term create category T{n}` and assign a post to each.)
7. Create an Editor-role user, log in as them, and add an Author filter. The preview lists authors (Network tab: `/wp/v2/users?…context=view…` returns 200).
8. Put a Post Type filter in an inherited loop (a template, e.g. Blog Home). Confirm the note shows and no `post` option is guessed.
9. Nested loop: a Query Loop inside another, each with a Category filter. There is no duplicate notice.

Record anything that doesn't match in the task report. Don't fix it silently.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/query-filter/edit.js src/blocks/sort/edit.js src/components/FilterInspectorControls.js
git commit -m "feat: Warn about duplicate and empty filters in the editor"
```

---

### Task 6: Docs, translations, and the PR

**Files:**

- Modify: `CHANGELOG.md`, `README.md`, `docs/hooks.md`, `CLAUDE.md`
- Modify: `languages/pikari-gutenberg-query-filter.pot`, `languages/pikari-gutenberg-query-filter-fr_CA.po`, plus the compiled `.mo` and `.json`

- [ ] **Step 1: `CHANGELOG.md`**, under `## [Unreleased]`

Add to `### Breaking changes`:

```markdown
- **The Sort block's `width` and `widthUnit` attributes are removed.** Nothing ever read them. Existing blocks that stored them still load and validate.
```

Add a `### Changed` section, if there isn't one, with:

```markdown
- **An empty Label now shows the default label**, in the editor and on the frontend, instead of an empty label or legend. Use Show Label to hide a label; it stays available to screen readers.
- **New filters no longer store a default label,** so the default follows the site's language and the chosen taxonomy. Existing blocks keep the label they saved.
```

Add to `### Added`:

```markdown
- **Editor notices** when two filters in one Query Loop use the same URL parameter (they act as one control), and when a filter will display nothing: no taxonomy chosen, no terms with posts, or no authors with published posts.
```

Add a `### Fixed` section, if there isn't one, with:

```markdown
- **The editor preview now lists what the frontend shows:** up to 100 terms without empty ones (it showed the first 10, including empty ones), and only authors with published posts.
- **The Author filter preview was empty for everyone below Administrator.**
```

- [ ] **Step 2: `README.md`**

In the Block Attributes table, change the `label` row's Values to `Label text. Empty uses the filter type's name; hide it with showLabel.`. The Sort line already lists only `label`, `showLabel` and `emptyLabel`; leave it.

- [ ] **Step 3: `docs/hooks.md`**

At the end of the paragraph at line 139 ("The radio group's `name` is the query parameter…"), add: `The editor warns on both blocks when this happens.`

- [ ] **Step 4: Plugin `CLAUDE.md`**

In "Tests for this area", add:

```markdown
- `tests/unit/utils/filter-notices.test.js` — Jest. Which editor notices apply, and duplicate detection per Query Loop (nested loops are separate).
- `tests/unit/utils/preview-queries.test.js` — Jest. The editor preview's REST queries match `FilterHelper` and `AuthorHelper`.
- `tests/unit/blocks/query-filter/variations.test.js` — Jest. Variations store no `label`.
```

In "Extension rules", add:

```markdown
- Label text is resolved by `FilterHelper::get_label()` in both `render.php` files, and mirrored by `label?.trim() ? label : default` in both `edit.js` files. Empty means default. Never store a default label as an attribute.
```

- [ ] **Step 5: Translations**

With wp-env running, follow the plugin `CLAUDE.md` workflow:

```bash
npm run i18n:pot
msgmerge --update languages/pikari-gutenberg-query-filter-fr_CA.po languages/pikari-gutenberg-query-filter.pot
```

Translate the new `msgid`s in the `.po`: the four notices, "Options come from the page's query." and "Leave empty to use the default label.". Remove the obsolete help text's `#~` entry if `msgmerge` leaves one. Then:

```bash
npm run i18n:mo && npm run i18n:json
```

- [ ] **Step 6: Full verification**

```bash
npm run lint:all && composer test && npm test && npm run build
npx wp-env start && PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm run test:e2e
```

Expected: everything green. Playwright should hold at **55** passing. Task 1 changes an empty label's output, and no fixture uses an empty label, so a failure there means the fixture assumption is wrong. Report it.

- [ ] **Step 7: Commit and open the PR**

```bash
git add CHANGELOG.md README.md docs/hooks.md CLAUDE.md languages/
git commit -m "docs: Document the editor UX changes and update translations"
git push -u origin feature/editor-ux
gh pr create --base main --title "feat: Editor UX for 1.0 (sub-project C)" --body-file <(cat <<'EOF'
Sub-project C of Query Filter 1.0. Spec: `docs/superpowers/specs/2026-09-23-query-filter-1.0-c-d-design.md` §4.

- Empty labels resolve to the default everywhere; new filters no longer store a translated default label.
- The Sort block drops its unused `width` / `widthUnit` attributes (breaking, before 1.0 or never).
- Editor notices for duplicate filters in one loop (the B3 radio-merge guard) and for filters that display nothing.
- The preview queries what the frontend renders; the Author preview works below Administrator.

Browser pass: see Task 5's checklist in `docs/superpowers/plans/2026-09-23-query-filter-c-editor-ux.md`.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)
```

Then wait for CI. Don't merge: Steve merges.
