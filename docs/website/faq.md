# Set Tag Order FAQ

## Does this work with the Classic Editor?

Yes. Both the Block Editor and the Classic Editor support setting a custom tag order, and as of 1.1.3 the Classic Editor can also create new tags from the custom tag box.

## Does the order survive switching between editors?

Yes. Both editors read and write the same stored order, and it is reconciled whenever a post is opened.

## Is the order per post or site-wide?

Per post. Each post has its own order. The three settings — separator, CSS class, and debug mode — are site-wide.

## Can I remove the separator between tags?

Yes. Leave the separator field empty under **Settings → Set Tag Order** and the plugin does not change whatever separator your theme already uses. To output nothing at all between tags, pass an empty separator in your theme's own tag output.

## Can I style tags with my own CSS?

Yes. Add one or more classes in settings, separated by spaces, and they are applied to tag links. Leaving the field as `tag` means no class is added, which matches WordPress's own behaviour.

## Where does a newly added tag go in the order?

To the end. This is true whether the tag is added in the editor, in Quick Edit, or by another plugin. Move it from there.

## Does it work with custom post types?

Yes, for any public post type registered with tag support. It handles the post tag taxonomy only — custom taxonomies and categories are not ordered.

## Will I lose my tag order when I update the plugin?

No. Order metadata saved by older versions is preserved and migrated automatically on upgrade.

## Does it change the order everywhere tags appear?

It controls the order wherever WordPress fetches the post's tags, which covers `the_tags()`, the Post Terms block, and most theme code. A theme that outputs tags through its own custom query will not be affected. See [Theme Integration](theme-integration.md).

## Does it slow my site down?

No meaningfully. The ordering is an in-memory sort of a post's tags on data WordPress has already fetched, and it adds no database queries to the front end. Leave debug mode off in production.

## Can I order the tag archive page?

No. A tag archive lists posts for a single tag, so there is no per-post order to apply.

## Is there a debug mode?

Yes. Enable it under **Settings → Set Tag Order** to log diagnostic information, then disable it once you are done. See [Troubleshooting](troubleshooting.md) for where the log is written.

---

[← Troubleshooting](troubleshooting.md) | [Documentation Home](index.md)
