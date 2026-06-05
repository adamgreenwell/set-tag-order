# Classic Editor Tag Creation And Test Pipeline Design

## Summary

Set Tag Order 1.1.3 will pair one small user-facing improvement with a lightweight test and CI foundation. The Classic Editor tag box will actually create new tags when an editor types a tag name that does not already exist, matching the UI copy and the existing PHP AJAX endpoint. The release will also add focused PHPUnit coverage and a small GitHub Actions workflow so future WordPress compatibility bumps are backed by repeatable evidence.

## Goals

- Enable new tag creation from the Classic Editor custom tag box.
- Preserve current tag ordering behavior when tags are added, removed, reordered, or rendered on the front end.
- Add lean WordPress PHPUnit coverage for the plugin's core ordering, synchronization, AJAX, and rendering behavior.
- Add a small GitHub Actions pipeline modeled after Guard Dog's test shape without copying its larger suite split, coverage upload, or release machinery.
- Update release metadata and changelog for WordPress 7.0 compatibility only after tests and local verification pass.

## Non-Goals

- Rebuild the Block Editor sidebar UI.
- Add npm, bundled JavaScript transpilation, or a block build process.
- Add Guard Dog's full coverage, Codecov, multi-suite, SVN deploy, or vendor-scoping workflow.
- Change the stored `_settagord` meta format.
- Expand the plugin beyond `post_tag` ordering.

## User-Facing Behavior

In the Classic Editor, typing an existing tag name and clicking Add continues to add that tag to the sortable list. Typing a new tag name now calls the existing `settagord_add_tag` AJAX action, creates the term, adds it to the autocomplete/datalist, appends it to the sortable list, and updates the hidden `post_tags` and `settagord` fields immediately.

Duplicate tag handling remains quiet and non-disruptive. If the tag is already assigned to the current post, the input is cleared and the existing list is left alone. If WordPress rejects the term creation, the UI leaves the list unchanged and shows a concise error using the normal browser alert pattern already implied by the commented implementation.

## Implementation Shape

### Classic Editor JavaScript

`js/set-tag-order-admin.js` will wire the commented AJAX creation flow back into the Add button path. The localized script data will include both `ajaxurl` and `addTagNonce` so the script does not rely on a global admin variable. Newly created tags will be pushed into the local `allTags` array and datalist before being appended to the sortable list.

The hidden-field update helper will normalize numeric tag IDs from jQuery data attributes before writing CSV values. This keeps the save handler compatible with the existing PHP path.

### PHP AJAX And Sync

The existing `wp_ajax_settagord_add_tag` handler will remain the creation endpoint. Any nearby fixes should be narrow: permission checks, response shape consistency, and sanitization/escaping only where needed for the new JS path.

The tag-order synchronization logic should be covered by tests. If the existing SQL lookup in the `set_object_terms` hook fails to handle multiple term taxonomy IDs reliably, replace it with prepared SQL parameterization or WordPress term APIs while preserving current behavior.

### Rendering

The release should preserve ordered output for:

- `settagord_get_ordered_post_tags()`.
- `settagord_the_ordered_post_tags()`.
- `get_the_terms` filtering.
- `core/post-terms` block rendering for `post_tag`.

Any rendering changes should be regression fixes only. The block renderer should keep honoring ordered tags, configured separator, configured link classes, and non-tag taxonomy passthrough.

## Test And Pipeline Design

Add Composer only for development dependencies:

- `phpunit/phpunit` compatible with the plugin's PHP support.
- `yoast/phpunit-polyfills` for stable WordPress test assertions across PHPUnit versions.

Add a compact WordPress test harness:

- `bin/install-wp-tests.sh`, adapted from Guard Dog's current bootstrap and able to resolve WordPress 7.0/latest.
- `bin/setup-tests.sh`, using the local Docker database when available and local MySQL when available.
- `bin/run-tests-from-wp-root.sh`, so tests run from the WordPress root while using the plugin's PHPUnit config.
- `tests/bootstrap.php`, loading Set Tag Order during the WordPress test bootstrap.
- `phpunit.xml`, with one focused integration suite.

Initial tests should cover:

- Ordering saved tags by `_settagord`.
- Appending newly assigned tags while preserving existing order.
- Removing deleted tags from saved order.
- Clearing `_settagord` when all tags are removed.
- AJAX tag creation success and validation failure.
- Classic save handler updating post tags and order fields.
- Frontend ordered tag rendering with custom separator and class.
- `core/post-terms` rendering preserving order for tag blocks.

Add `.github/workflows/tests.yml` with a small matrix:

- PHP 7.4 with WordPress 7.0.
- Current stable PHP with WordPress 7.0.
- Current stable PHP with WordPress latest.

The workflow should install Composer dev dependencies, install the WordPress test suite, then run `composer test`. No coverage upload is required for this release.

## Release Updates

After implementation and verification, update:

- Plugin header `Version`.
- `README.txt` stable tag, tested up to, and changelog.
- `README.md` only if it contains mirrored release information.

The expected changelog should mention Classic Editor new-tag creation, ordering/sync reliability, and the new test pipeline.

## Verification

Before release preparation is considered complete:

- Run PHP syntax checks for plugin PHP files.
- Run the new PHPUnit suite locally.
- If Docker is available, smoke the plugin in the local WordPress environment.
- Confirm the WordPress 7.0 compatibility target using the WordPress core version API or installed local runtime.
- Confirm generated development artifacts are excluded from the WordPress.org distribution through `.distignore`.

## Risks

- WordPress 7.0/latest test installation depends on upstream WordPress.org and SVN availability.
- The Classic Editor UI still uses jQuery and datalist behavior, so the improvement should stay modest rather than trying to modernize the whole experience.
- The plugin's main PHP file is large; this release should avoid broad refactoring unless tests expose behavior that cannot be fixed cleanly in place.
