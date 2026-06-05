# Classic Editor Tag Creation And Test Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Set Tag Order 1.1.3 with Classic Editor new-tag creation, core tag-order regression coverage, and a lean WordPress/PHP compatibility pipeline.

**Architecture:** Keep production changes inside the existing plugin files and add a focused WordPress PHPUnit harness around them. The test harness must work from this full local WordPress install and from a standalone plugin checkout in GitHub Actions. The behavior changes are narrow: enable the existing AJAX endpoint from Classic Editor JavaScript, harden tag-order synchronization, and fix rendering regressions that the tests expose.

**Tech Stack:** WordPress plugin PHP, jQuery admin JavaScript, Composer dev dependencies, PHPUnit 9, Yoast PHPUnit Polyfills, WordPress core test suite, GitHub Actions.

---

## File Map

- Create `composer.json`: development-only test dependencies and scripts.
- Create `phpunit.xml`: one integration suite backed by WordPress test bootstrap.
- Create `bin/install-wp-tests.sh`: WordPress core/test-suite installer adapted from Guard Dog.
- Create `bin/setup-tests.sh`: local setup helper that reads the root `.env`, starts `settagorder_mysql` when Docker is available, and prepares the test database.
- Create `bin/run-tests-from-wp-root.sh`: PHPUnit runner that works from a full WordPress tree or standalone plugin checkout.
- Create `tests/bootstrap.php`: loads the plugin into the WordPress test environment.
- Create `tests/integration/TagOrderIntegrationTest.php`: regression coverage for ordering, sync, rendering, AJAX creation, and Classic Editor save behavior.
- Create `.github/workflows/tests.yml`: small PHP/WordPress matrix.
- Create `.distignore`: exclude dev/test/CI artifacts from WordPress.org distribution.
- Modify `set-tag-order.php`: only where tests require sync, AJAX, rendering, and version metadata fixes.
- Modify `js/set-tag-order-admin.js`: enable the existing AJAX creation path and normalize hidden field updates.
- Modify `README.txt` and `README.md`: release metadata and changelog.

## Task 1: Add The Test Harness And CI Skeleton

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `bin/install-wp-tests.sh`
- Create: `bin/setup-tests.sh`
- Create: `bin/run-tests-from-wp-root.sh`
- Create: `tests/bootstrap.php`
- Create: `.github/workflows/tests.yml`
- Create: `.distignore`

- [ ] **Step 1: Add Composer test scripts**

Create `composer.json` with this shape:

```json
{
  "name": "adamgreenwell/set-tag-order",
  "description": "Set Tag Order is a WordPress plugin for custom post tag display ordering in the Block Editor and Classic Editor.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "authors": [
    {
      "name": "Adam Greenwell",
      "email": "me@adamgreenwell.com"
    }
  ],
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "yoast/phpunit-polyfills": "^2.0"
  },
  "scripts": {
    "test:setup": "bash bin/setup-tests.sh",
    "test": "bash bin/run-tests-from-wp-root.sh",
    "test:integration": "bash bin/run-tests-from-wp-root.sh --testsuite integration"
  },
  "config": {
    "allow-plugins": {}
  }
}
```

- [ ] **Step 2: Add PHPUnit config**

Create `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    stopOnFailure="false"
    beStrictAboutOutputDuringTests="false"
    failOnRisky="true"
    failOnWarning="true">
    <testsuites>
        <testsuite name="integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
    <php>
        <const name="WP_TESTS_PHPUNIT_POLYFILLS_PATH" value="vendor/yoast/phpunit-polyfills"/>
        <env name="WP_TESTS_DOMAIN" value="localhost"/>
        <env name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <env name="WP_TESTS_TITLE" value="Set Tag Order Tests"/>
    </php>
</phpunit>
```

- [ ] **Step 3: Add WordPress test installer**

Copy Guard Dog's `bin/install-wp-tests.sh`, update comments from Guard Dog to Set Tag Order, and keep its WordPress version resolver so `7.0`, `latest`, `nightly`, and old minors work.

- [ ] **Step 4: Add local setup helper**

Create `bin/setup-tests.sh` using these project-specific values:

```bash
WP_TEST_DB_NAME="${WP_TEST_DB_NAME:-wordpress_test}"
WP_TEST_DB_HOST="${WP_TEST_DB_HOST:-127.0.0.1:3309}"
MYSQL_CONTAINER_NAME="${MYSQL_CONTAINER_NAME:-settagorder_mysql}"
```

The script should read `${WP_ROOT}/.env`, use `DB_USER`/`DB_PASSWORD`, start `settagorder_mysql` with `docker compose up -d settagorder_mysql` when Docker is available, and call `bin/install-wp-tests.sh`.

- [ ] **Step 5: Add PHPUnit runner**

Create `bin/run-tests-from-wp-root.sh`. It must resolve `WP_TESTS_DIR`, then run:

```bash
WP_TESTS_DIR="${RESOLVED_WP_TESTS_DIR}" "${PLUGIN_DIR}/vendor/bin/phpunit" -c "${PLUGIN_DIR}/phpunit.xml" "${PHPUNIT_ARGS[@]}"
```

If `${WP_ROOT}/wp-includes/version.php` exists, run from `${WP_ROOT}`. Otherwise run from `${PLUGIN_DIR}` so GitHub Actions standalone checkouts work.

- [ ] **Step 6: Add bootstrap**

Create `tests/bootstrap.php` that defines `WP_TESTS_DIR`, requires `includes/functions.php`, registers `_manually_load_set_tag_order_plugin()` on `muplugins_loaded`, requires `set-tag-order.php`, then requires `includes/bootstrap.php` and the Yoast polyfills autoloader.

- [ ] **Step 7: Add GitHub Actions workflow**

Create `.github/workflows/tests.yml` with matrix rows:

```yaml
include:
  - php: '7.4'
    wordpress: '7.0'
  - php: '8.3'
    wordpress: '7.0'
  - php: '8.3'
    wordpress: 'latest'
```

Each job should install PHP, Composer, `subversion`, and `default-mysql-client`; run `composer install`; install the WordPress tests with `skip-database-creation=true`; then run `composer test`.

- [ ] **Step 8: Add `.distignore`**

Exclude `.git`, `.github`, `bin`, `tests`, `docs`, `vendor`, `composer.json`, `composer.lock`, `phpunit.xml`, `set-tag-order-svn`, local editor files, logs, `.env*`, and `.distignore`.

- [ ] **Step 9: Verify harness install**

Run:

```bash
composer install
composer test
```

Expected after Step 9: `composer install` succeeds and `composer test` reports "No tests executed" or an empty-suite PHPUnit message until Task 2 adds tests.

- [ ] **Step 10: Commit harness**

```bash
git add composer.json composer.lock phpunit.xml bin tests/bootstrap.php .github/workflows/tests.yml .distignore
git commit -m "Add test harness and CI workflow"
```

## Task 2: Write Failing Core Behavior Tests

**Files:**
- Create: `tests/integration/TagOrderIntegrationTest.php`

- [ ] **Step 1: Add integration test class**

Create `tests/integration/TagOrderIntegrationTest.php` with a class extending `WP_Ajax_UnitTestCase`.

Include helpers:

```php
private function create_post_with_tags(array $tag_names) {
    $post_id = self::factory()->post->create(['post_status' => 'publish']);
    $term_ids = [];
    foreach ($tag_names as $tag_name) {
        $term = wp_insert_term($tag_name, 'post_tag');
        $term_ids[] = (int) $term['term_id'];
    }
    wp_set_post_terms($post_id, $term_ids, 'post_tag', false);
    return [$post_id, $term_ids];
}

private function ordered_tag_names(array $tags) {
    return wp_list_pluck($tags, 'name');
}
```

- [ ] **Step 2: Test saved order is used**

Add:

```php
public function test_get_ordered_post_tags_uses_saved_order() {
    [$post_id, $term_ids] = $this->create_post_with_tags(['Alpha', 'Beta', 'Gamma']);
    update_post_meta($post_id, '_settagord', implode(',', [$term_ids[2], $term_ids[0], $term_ids[1]]));

    $tags = settagord_get_ordered_post_tags($post_id);

    $this->assertSame(['Gamma', 'Alpha', 'Beta'], $this->ordered_tag_names($tags));
}
```

- [ ] **Step 3: Test stale IDs are removed and new IDs appended**

Add:

```php
public function test_synchronize_on_load_removes_stale_ids_and_appends_new_tags() {
    [$post_id, $term_ids] = $this->create_post_with_tags(['Alpha', 'Beta']);
    $gamma = wp_insert_term('Gamma', 'post_tag');
    wp_set_post_terms($post_id, [$term_ids[1], (int) $gamma['term_id']], 'post_tag', false);
    update_post_meta($post_id, '_settagord', implode(',', [$term_ids[0], $term_ids[1]]));

    settagord_synchronize_on_load($post_id);

    $this->assertSame(implode(',', [$term_ids[1], (int) $gamma['term_id']]), get_post_meta($post_id, '_settagord', true));
}
```

- [ ] **Step 4: Test set_object_terms keeps all assigned tag IDs**

Add:

```php
public function test_set_object_terms_sync_handles_multiple_term_taxonomy_ids() {
    [$post_id, $term_ids] = $this->create_post_with_tags(['Alpha']);
    update_post_meta($post_id, '_settagord', (string) $term_ids[0]);
    $beta = wp_insert_term('Beta', 'post_tag');
    $gamma = wp_insert_term('Gamma', 'post_tag');

    wp_set_post_terms($post_id, [$term_ids[0], (int) $beta['term_id'], (int) $gamma['term_id']], 'post_tag', false);

    $this->assertSame(
        implode(',', [$term_ids[0], (int) $beta['term_id'], (int) $gamma['term_id']]),
        get_post_meta($post_id, '_settagord', true)
    );
}
```

- [ ] **Step 5: Test all tags removed clears order**

Add:

```php
public function test_removing_all_tags_clears_saved_order() {
    [$post_id, $term_ids] = $this->create_post_with_tags(['Alpha', 'Beta']);
    update_post_meta($post_id, '_settagord', implode(',', $term_ids));

    wp_set_post_terms($post_id, [], 'post_tag', false);

    $this->assertSame('', get_post_meta($post_id, '_settagord', true));
}
```

- [ ] **Step 6: Test frontend term links remain present with separator and default class**

Add:

```php
public function test_term_links_filter_keeps_links_when_custom_separator_uses_default_class() {
    [$post_id, $term_ids] = $this->create_post_with_tags(['Alpha', 'Beta']);
    update_post_meta($post_id, '_settagord', implode(',', array_reverse($term_ids)));
    update_option('settagord_separator', '|');
    update_option('settagord_class', 'tag');
    $GLOBALS['post'] = get_post($post_id);
    setup_postdata($GLOBALS['post']);

    $html = get_the_term_list($post_id, 'post_tag', '', '<span>|</span>', '');

    $this->assertStringContainsString('Beta', $html);
    $this->assertStringContainsString('Alpha', $html);
    $this->assertLessThan(strpos($html, 'Alpha'), strpos($html, 'Beta'));
}
```

- [ ] **Step 7: Run tests and verify failures**

Run:

```bash
composer test:integration
```

Expected: at least `test_set_object_terms_sync_handles_multiple_term_taxonomy_ids` or `test_term_links_filter_keeps_links_when_custom_separator_uses_default_class` fails against current production code.

- [ ] **Step 8: Commit failing tests**

```bash
git add tests/integration/TagOrderIntegrationTest.php
git commit -m "Add failing tag order regression tests"
```

## Task 3: Implement PHP Sync And Rendering Fixes

**Files:**
- Modify: `set-tag-order.php`
- Test: `tests/integration/TagOrderIntegrationTest.php`

- [ ] **Step 1: Replace term taxonomy SQL lookup**

In the `set_object_terms` hook, replace the manual SQL lookup with:

```php
$term_ids = wp_get_object_terms($post_id, 'post_tag', ['fields' => 'ids']);
if (is_wp_error($term_ids)) {
    settagord_debug_log("Unable to sync tag order for post $post_id: " . $term_ids->get_error_message());
    return;
}
$term_ids = array_map('intval', $term_ids);
```

- [ ] **Step 2: Keep existing links when custom class is default**

In the `term_links-post_tag` filter, always append `$existing_link` after any optional class mutation. The branch should end with:

```php
if ($custom_class !== 'tag') {
    // existing class mutation logic.
}

$custom_links[] = $existing_link;
```

- [ ] **Step 3: Run core behavior tests**

Run:

```bash
composer test:integration
```

Expected: Task 2 tests pass.

- [ ] **Step 4: Commit PHP fixes**

```bash
git add set-tag-order.php tests/integration/TagOrderIntegrationTest.php
git commit -m "Fix tag order sync and link rendering"
```

## Task 4: Write Failing Classic Editor AJAX Tests

**Files:**
- Modify: `tests/integration/TagOrderIntegrationTest.php`

- [ ] **Step 1: Test AJAX creates a tag for authorized users**

Add:

```php
public function test_ajax_add_tag_creates_term_for_authorized_user() {
    wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    $_POST['_wpnonce'] = wp_create_nonce('settagord_add_tag_nonce');
    $_POST['tag_name'] = 'Fresh Tag';

    try {
        $this->_handleAjax('settagord_add_tag');
    } catch (WPAjaxDieContinueException $exception) {
    }

    $response = json_decode($this->_last_response, true);
    $this->assertTrue($response['success']);
    $this->assertSame('Fresh Tag', $response['data']['name']);
    $this->assertNotNull(term_exists('Fresh Tag', 'post_tag'));
}
```

- [ ] **Step 2: Test AJAX rejects users who cannot create tags**

Add:

```php
public function test_ajax_add_tag_requires_term_creation_capability() {
    wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
    $_POST['_wpnonce'] = wp_create_nonce('settagord_add_tag_nonce');
    $_POST['tag_name'] = 'Nope Tag';

    try {
        $this->_handleAjax('settagord_add_tag');
    } catch (WPAjaxDieContinueException $exception) {
    }

    $response = json_decode($this->_last_response, true);
    $this->assertFalse($response['success']);
    $this->assertNull(term_exists('Nope Tag', 'post_tag'));
}
```

- [ ] **Step 3: Test Classic save persists tags and order**

Add:

```php
public function test_classic_save_handler_persists_post_tags_and_order() {
    $post_id = self::factory()->post->create(['post_status' => 'draft']);
    [$unused_post_id, $term_ids] = $this->create_post_with_tags(['Alpha', 'Beta']);
    wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

    $_POST['settagord_meta_box_nonce'] = wp_create_nonce('settagord_meta_box');
    $_POST['post_tags'] = implode(',', [$term_ids[1], $term_ids[0]]);
    $_POST['settagord'] = implode(',', [$term_ids[1], $term_ids[0]]);

    do_action('save_post', $post_id, get_post($post_id), true);

    $this->assertSame(implode(',', [$term_ids[1], $term_ids[0]]), get_post_meta($post_id, '_settagord', true));
    $this->assertSame([$term_ids[1], $term_ids[0]], wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']));
}
```

- [ ] **Step 4: Run tests and verify failure**

Run:

```bash
composer test:integration
```

Expected: capability test fails until the AJAX handler adds a permission check.

- [ ] **Step 5: Commit failing AJAX tests**

```bash
git add tests/integration/TagOrderIntegrationTest.php
git commit -m "Add failing Classic Editor AJAX tests"
```

## Task 5: Implement Classic Editor New-Tag Creation

**Files:**
- Modify: `set-tag-order.php`
- Modify: `js/set-tag-order-admin.js`
- Test: `tests/integration/TagOrderIntegrationTest.php`

- [ ] **Step 1: Add AJAX capability check**

In `wp_ajax_settagord_add_tag`, after nonce validation, add:

```php
$taxonomy = get_taxonomy('post_tag');
if (!$taxonomy || !current_user_can($taxonomy->cap->edit_terms)) {
    wp_send_json_error('You are not allowed to create tags.');
}
```

- [ ] **Step 2: Localize `ajaxurl` for Classic Editor JS**

In the `wp_localize_script('settagord-admin-js', ...)` call, add:

```php
'ajaxurl' => admin_url('admin-ajax.php'),
```

- [ ] **Step 3: Enable new-tag AJAX in JS**

In `js/set-tag-order-admin.js`, replace the commented new-tag creation block with a real `$.ajax()` call that posts:

```js
{
    action: 'settagord_add_tag',
    tag_name: tagName,
    _wpnonce: settagordAdminData.addTagNonce
}
```

On success, push `{ id: response.data.term_id, text: response.data.name }` into `allTags`, append a datalist option, call `addTagToList(response.data.term_id, response.data.name)`, call `updateTagOrder()`, and clear the input.

- [ ] **Step 4: Normalize hidden field IDs**

In `updateTagOrder()`, cast jQuery data values with:

```js
var numericTagId = parseInt(tagId, 10);
if (!isNaN(numericTagId)) {
    orderedIds.push(numericTagId);
    currentTagIds.push(numericTagId);
}
```

- [ ] **Step 5: Run tests**

Run:

```bash
composer test:integration
```

Expected: all integration tests pass.

- [ ] **Step 6: Commit Classic Editor implementation**

```bash
git add set-tag-order.php js/set-tag-order-admin.js tests/integration/TagOrderIntegrationTest.php
git commit -m "Enable Classic Editor tag creation"
```

## Task 6: Update Release Metadata

**Files:**
- Modify: `set-tag-order.php`
- Modify: `README.txt`
- Modify: `README.md`

- [ ] **Step 1: Bump plugin version**

Change the plugin header:

```php
 * Version:     1.1.3
```

- [ ] **Step 2: Update WordPress.org metadata**

In `README.txt`, change:

```text
Tested up to: 7.0
Stable tag: 1.1.3
```

Add changelog:

```text
= 1.1.3 =
* Add Classic Editor support for creating new tags from the custom tag box.
* Improve tag order synchronization when tags are added or removed.
* Add PHPUnit and GitHub Actions coverage for WordPress 7.0 compatibility.
```

- [ ] **Step 3: Update Markdown changelog**

In `README.md`, add:

```markdown
### 1.1.3
* Add Classic Editor support for creating new tags from the custom tag box.
* Improve tag order synchronization when tags are added or removed.
* Add PHPUnit and GitHub Actions coverage for WordPress 7.0 compatibility.
```

- [ ] **Step 4: Commit release metadata**

```bash
git add set-tag-order.php README.txt README.md
git commit -m "Bump version to 1.1.3"
```

## Task 7: Final Verification

**Files:**
- Read/verify only unless fixes are needed.

- [ ] **Step 1: Syntax check plugin PHP**

Run:

```bash
find . -path './set-tag-order-svn' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: every tracked PHP file reports `No syntax errors detected`.

- [ ] **Step 2: Run full test suite**

Run:

```bash
composer test
```

Expected: all tests pass.

- [ ] **Step 3: Check working tree**

Run:

```bash
git status --short --branch
```

Expected: clean except intentionally ignored generated artifacts.

- [ ] **Step 4: If Docker is available, run local smoke**

Run:

```bash
docker compose ps
curl -I http://localhost:9080
```

Expected: if Docker is running, WordPress responds. If Docker is not running, record that local browser smoke was skipped because Docker daemon was unavailable.

- [ ] **Step 5: Confirm WordPress 7.0 target**

Run:

```bash
curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | php -r '$json=json_decode(stream_get_contents(STDIN), true); echo $json["offers"][0]["version"] . PHP_EOL;'
```

Expected: `7.0`.

- [ ] **Step 6: Final commit if verification fixes were needed**

If verification required edits, inspect the changed files first:

```bash
git status --short
git diff -- README.md README.txt set-tag-order.php js/set-tag-order-admin.js tests/integration/TagOrderIntegrationTest.php composer.json phpunit.xml .distignore .github/workflows/tests.yml bin/install-wp-tests.sh bin/setup-tests.sh bin/run-tests-from-wp-root.sh tests/bootstrap.php
```

Then stage only the files shown by `git status --short` that belong to this release slice and commit:

```bash
git add README.md README.txt set-tag-order.php js/set-tag-order-admin.js tests/integration/TagOrderIntegrationTest.php composer.json composer.lock phpunit.xml .distignore .github/workflows/tests.yml bin/install-wp-tests.sh bin/setup-tests.sh bin/run-tests-from-wp-root.sh tests/bootstrap.php
git commit -m "Fix release verification issues"
```
