# Troubleshooting Set Tag Order

## Tag Order Is Not Showing On The Front End

Confirm the theme renders tags through a path the plugin controls. A theme that outputs tags with its own custom query, bypassing the plugin, will show WordPress's default order.

If you are using the core `post-terms` block, order is applied before the block renders as of 1.2.0.

## Order Did Not Load In The Classic Editor

Before 1.2.0, tag order synchronisation on load required a nonce WordPress does not issue, so it never ran. Update to 1.2.0 or newer.

## Separator Appears Inside A Tag Name Or URL

Fixed in 1.2.0. The separator is now replaced only between tags. Update, then reload the post.

## Separator Styling Missing On A Non-Block Theme

The separator stylesheet loads from a plugin-owned handle as of 1.2.0, so it applies even on themes that do not load core block styles.

## A Filter Callback Runs Twice, Or Never Runs

`settagord_ordered_tags` previously ran twice on posts with a saved order and never on posts without one. As of 1.2.1 it runs exactly once per render, including on unordered posts. If your callback appends or counts, review it against the corrected behaviour.

## Custom Classes Stripped Other Attributes

Fixed in 1.2.0. Classes are applied without rebuilding the link, preserving `rel="tag"` and attributes from other plugins.

## Order Metadata From An Older Version

Order metadata saved by older versions is preserved on upgrade. If an older post has no order, set it once and save.

## Debug Mode

Enable debug mode under **Settings → Set Tag Order** to log diagnostic information, then turn it back off. When debug mode is off the plugin no longer formats log messages at all, so leaving it disabled costs nothing on the front end.

---

[← Theme Integration](theme-integration.md) | [Documentation Home](index.md) | [FAQ →](faq.md)
