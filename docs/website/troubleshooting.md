# Troubleshooting Set Tag Order

## Tag Order Is Not Showing On The Front End

Work through these in order.

**Confirm an order is actually saved.** Open the post and check that the ordering controls show the order you expect. In the Block Editor, moving a tag only marks the post as changed — the order is not stored until you update the post.

**Confirm the theme renders tags through a path the plugin controls.** The plugin reorders tags at the point WordPress fetches them, which covers:

- `the_tags()`
- `get_the_tags()`
- `get_the_term_list()` and `the_terms()`
- the `core/post-terms` block
- the tag cloud widget, in a post context

It does not cover `wp_get_post_tags()`, `wp_get_post_terms()`, or `wp_get_object_terms()`. A theme using those gets WordPress's default order. See [Theme Integration](theme-integration.md).

**Clear any caching.** Page caches, object caches, and CDNs all serve the markup from before your change.

**Check for a conflicting plugin.** Another plugin sorting tags after this one wins. Deactivate other plugins to confirm.

## Order Resets After Editing A Post

Most often the order was never saved — see above. If it is genuinely being lost on save, enable debug mode and look for `Updated tag order for post <id>` lines around the save to see what was written.

Another plugin writing to the same post terms can also cause this. The plugin tracks external tag changes and appends new tags rather than discarding order, but a plugin that rewrites tags wholesale on every save will keep pushing them to the end.

## The Wrong Tag Box Appears

If the drag-and-drop box shows in the Block Editor, or the standard box shows in the Classic Editor, editor detection has gone wrong. Common causes are the Classic Editor plugin configured per user or per post, another plugin switching editors conditionally, or a stale cached detection value.

Enable debug mode and look for `Detected as Block Editor - skipping meta box replacement` to see which way detection went.

## Order Metadata From An Older Version

Order saved by older versions of the plugin is preserved and migrated automatically the first time each post is loaded. If an older post has no order, set it once and save.

## Conflict With The Core Post Terms Block

A conflict with the default `core/post-terms` block was fixed in 1.1.2. If you see duplicate or unordered tags in a block theme, confirm you are on 1.1.2 or newer.

If the block looks different after updating to 1.2.0, that is expected: the block is now rendered by WordPress rather than replaced by the plugin, so its markup matches core and its colour, spacing, and typography settings work. Two CSS-visible changes are covered in [Theme Integration](theme-integration.md).

## The Separator Is Not Applied

Check where the tags are rendered. `the_tags()`, the template helpers, and the Post Terms block are covered. A direct call to `get_the_term_list()` with a hard-coded separator uses the theme's separator, not the setting.

Note that the Post Terms block uses the block's own separator span, `wp-block-post-terms__separator`, rather than the plugin's. Style both.

## The CSS Class Is Not Applied

The default value `tag` means "no class", matching WordPress's own behaviour. Set the field to any other value to have classes added. This changed in 1.2.0; earlier versions added the literal class `tag` to every link.

## Debug Mode

Enable debug mode under **Settings → Set Tag Order** to log diagnostic information while you investigate, then turn it back off.

Messages are written to the PHP error log with the prefix `[Set Tag Order Debug]`. To capture them in `wp-content/debug.log`, enable WordPress debug logging in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

The log records term ordering, separator application, block rendering, and editor detection. It is verbose — leave it off on production sites.

---

[← Theme Integration](theme-integration.md) | [Documentation Home](index.md) | [FAQ →](faq.md)
