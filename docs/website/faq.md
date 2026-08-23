# Set Tag Order FAQ

## Does this work with the Classic Editor?

Yes. Both editors support ordering, creating tags, keyboard reordering, and Sort A-Z.

## Can I reorder tags without a mouse?

Yes. Keyboard reordering was added in 1.2.0. Reordering, adding, and removing are also announced to screen readers.

## Can I just sort tags alphabetically?

Yes. Both editors have a **Sort A-Z** control.

## Can I remove the separator between tags?

Yes. Leave the separator field empty under **Settings → Set Tag Order** and no separator is output.

## Can I style tags with my own CSS?

Yes. Add one or more classes in settings, separated by spaces. They are applied without rebuilding the link, so `rel="tag"` and attributes from other plugins survive.

## Does it work with the core post-terms block?

Yes. As of 1.2.0 the plugin applies order and classes before the block renders rather than replacing the renderer, so the block keeps its own markup, alignment, link colour, and prefix and suffix text.

## Is the plugin translatable?

Yes. A translation template ships at `languages/set-tag-order.pot`.

## What happens if I delete the plugin?

Uninstalling removes the plugin's options and tag order metadata, across all sites on multisite. Deactivating leaves your data in place.

## Are there filters for developers?

Yes: `settagord_ordered_tags`, `settagord_separator`, and `settagord_link_classes`. See [Theme Integration](theme-integration.md).

## Will I lose my tag order when I update?

No. Order metadata saved by older versions is preserved on upgrade.

---

[← Troubleshooting](troubleshooting.md) | [Documentation Home](index.md)
