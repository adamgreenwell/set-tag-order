# Using Set Tag Order

Set Tag Order adds the same ordering control to both WordPress editors. The order is stored on the post, so it survives switching editors.

## Reordering Tags

Tags can be reordered by dragging, or from the keyboard. Keyboard reordering was added in 1.2.0; before that, ordering was drag-only, which left no way to change the order without a mouse.

Both editors also have a **Sort A-Z** control, for when alphabetical is what you actually wanted.

Reordering, adding, and removing tags are announced to screen readers, and the reorder buttons carry accessible labels.

## Block Editor

The ordering panel appears alongside the post's other tag controls. It follows the editor colour scheme rather than using fixed greys, so it does not look out of place in dark mode.

Set Tag Order supports the always-iframed editor introduced in WordPress 7.1.

## Classic Editor

The custom tag box supports creating new tags directly, as well as reordering existing ones.

If you used the Classic Editor before 1.2.0, note that tag order synchronisation on load never actually ran: it required a nonce WordPress does not issue. That is fixed, so an existing order now loads correctly.

## Adding And Removing Tags

Tag order stays in step as tags are added and removed, rather than drifting out of the order you set. Order metadata saved by older versions of the plugin is preserved on upgrade.

## Where The Order Applies

The order applies wherever the plugin renders tags, including the core `post-terms` block. See [Theme Integration](theme-integration.md) for how output is built and how to hook into it.

---

[← Getting Started](getting-started.md) | [Documentation Home](index.md) | [Theme Integration →](theme-integration.md)
