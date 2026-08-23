# Using Set Tag Order

Set Tag Order adds ordering control to both WordPress editors. The stored order is a property of the post, so it survives switching editors.

## Block Editor

A **Tag Order** panel appears in the document sidebar, directly below the standard **Tags** panel, whenever the post has tags.

The panel lists the post's tags in their display order. Each tag has up and down arrow buttons that move it one position at a time. Moving a tag marks the post as having unsaved changes — the new order is written when you update the post, not before.

The panel does not manage which tags are applied; the standard Tags panel does that. Add and remove tags there, and the Tag Order panel follows along.

## Classic Editor

The standard **Tags** box in the sidebar is replaced with one that supports reordering. It keeps the familiar layout — a text field, an **Add** button, and the list of applied tags.

Drag any tag in the list to move it. The order is recorded as you drag and saved with the post.

To add a tag, type in the field and click **Add**. Suggestions from existing tags appear as you type. If the name does not match an existing tag, the tag is created and applied — this requires permission to manage tags, so contributors and authors will see an error. Tags are added one at a time; the comma-separated bulk entry of the core box is not supported.

To remove a tag, click the **×** beside it.

## Adding And Removing Tags

Tag order stays in step as tags change, rather than drifting out of the order you set:

- A newly added tag goes to the **end** of the order. Move it from there.
- Removing a tag removes it from the order and leaves the rest in place.
- Removing every tag clears the stored order.
- Changing tags outside the editor — Quick Edit, bulk edit, an import, another plugin — is also tracked, with new tags appended.

If a post's stored order has drifted out of date, opening it in either editor reconciles it: tags already in the order keep their relative positions, and anything new is appended. Nothing is ever hidden by a stale order.

## Ordering And The Front End

The order applies wherever WordPress renders the post's tags — `the_tags()`, the Post Terms block, and most theme code. See [Theme Integration](theme-integration.md) for how the output is built and the few paths that bypass it.

---

[← Getting Started](getting-started.md) | [Documentation Home](index.md) | [Theme Integration →](theme-integration.md)
