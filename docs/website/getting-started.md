# Getting Started With Set Tag Order

## Requirements

- WordPress 6.3 or newer.
- PHP 7.4 or newer.

## Installation

1. In your admin panel, go to **Plugins → Add New**.
2. Click **Upload Plugin**, choose the plugin `.zip`, then click **Install Now**.
3. Click **Activate**.

Set Tag Order is also available from the [WordPress.org plugin directory](https://wordpress.org/plugins/set-tag-order/).

## First Configuration Pass

1. Go to **Settings → Set Tag Order**.
2. Set the tag separator you want between tags on the front end. Leave it empty to keep whatever separator your theme already uses.
3. Add any CSS classes your theme should apply to tag links. Separate multiple classes with spaces. Leaving this as `tag` means no class is added, which matches WordPress's own behaviour.
4. Save.

## Setting Your First Tag Order

1. Edit a post that already has several tags.
2. Find the Set Tag Order controls in the editor. In the Block Editor this is the **Tag Order** panel in the document sidebar, below the standard Tags panel. In the Classic Editor the standard **Tags** box is replaced with one that supports reordering.
3. Put the tags in the order you want — arrow buttons in the Block Editor, drag and drop in the Classic Editor.
4. Update the post and view it on the front end to confirm the order.

If the post has no tags yet, add them first. New tags appear in the ordering controls immediately, at the end of the order.

See [Usage](usage.md) for the differences between the two editors.

## Upgrading From An Earlier Version

Nothing is required. Order saved by earlier versions is read and migrated automatically the first time each post is loaded.

One change in 1.2.0 is worth knowing about if you have custom CSS: the `core/post-terms` block is now rendered by WordPress itself rather than replaced by the plugin, so its markup matches core. See [Theme Integration](theme-integration.md).

---

[Documentation Home](index.md) | [Usage →](usage.md)
