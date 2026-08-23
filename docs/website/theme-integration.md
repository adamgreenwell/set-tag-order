# Theme Integration

Set Tag Order controls the order tags are output in, and gives you two levers over how that output looks.

For most themes nothing is required. The plugin reorders tags at the point WordPress fetches them, so `the_tags()`, `get_the_tags()`, `get_the_term_list()`, and the Post Terms block all inherit the order.

## Tag Separator

Set the separator under **Settings → Set Tag Order**. Leave the field empty to keep whatever separator the theme, block, or template function already uses.

The separator text is escaped on output, so characters like `&` and `<` are safe to use.

Note that the field is sanitised on save: leading and trailing whitespace is stripped, and runs of spaces are collapsed to one. Entering `" / "` stores `"/"`. Space around the separator comes from CSS rather than the setting — the separator is wrapped in a span with a small horizontal margin, which you can adjust.

Where the separator appears depends on the output path:

- `the_tags()` and the plugin's template helpers wrap it in `<span class="settagord-tag-separator">`.
- The Post Terms block uses the block's own `<span class="wp-block-post-terms__separator">`.

Style both if you want a consistent result:

```css
.settagord-tag-separator,
.wp-block-post-terms__separator {
    color: inherit;
    margin: 0 0.4em;
}
```

## Custom CSS Classes

Classes entered in settings are added to tag links so a theme can style them. Separate multiple classes with spaces.

The default value `tag` means "no class", matching WordPress's own behaviour of not adding one. Set it to anything else and the classes are added to the existing link — the `href`, `rel="tag"`, and any attributes contributed by other plugins are preserved.

```css
.badge {
    display: inline-block;
    padding: 0.2em 0.6em;
    border-radius: 999px;
    background: var(--wp--preset--color--tertiary, #eee);
}
```

## Template Helpers

Two helpers are available for themes that build their own markup.

### settagord_get_ordered_post_tags()

```php
settagord_get_ordered_post_tags( int|null $post_id = null ): array|false
```

Returns the post's tags as an array of `WP_Term` objects in the saved order, or `false` if the post has no tags or its post type does not support tags. Defaults to the current post.

```php
$tags = settagord_get_ordered_post_tags();

if ( $tags ) {
    foreach ( $tags as $tag ) {
        printf(
            '<a href="%s">%s</a>',
            esc_url( get_tag_link( $tag->term_id ) ),
            esc_html( $tag->name )
        );
    }
}
```

### settagord_the_ordered_post_tags()

```php
settagord_the_ordered_post_tags( string $before = '', string $sep = '', string $after = '', int $post_id = 0 ): void
```

Echoes the tags as links, in order, and outputs nothing when the post has no tags. The `$sep` argument is ignored when the Tag Separator setting is set — the setting wins.

```php
settagord_the_ordered_post_tags( '<p class="tags">Tagged: ', ', ', '</p>' );
```

Unprefixed aliases `get_ordered_post_tags()` and `the_ordered_post_tags()` remain available for themes written against pre-1.1 versions. They are defined only if nothing else has claimed the name. Prefer the prefixed names in new code.

## Filters

Three filters let you adjust behaviour without editing the plugin.

```php
// Show at most five tags, keeping the chosen order.
add_filter( 'settagord_ordered_tags', function ( $tags ) {
    return array_slice( $tags, 0, 5 );
} );

// Use a different separator on one post type.
add_filter( 'settagord_separator', function ( $separator ) {
    return is_singular( 'product' ) ? ' / ' : $separator;
} );

// Add a class on top of whatever is configured.
add_filter( 'settagord_link_classes', function ( $classes ) {
    $classes[] = 'is-style-pill';
    return $classes;
} );
```

`settagord_ordered_tags` receives the ordered term objects, the post ID, and the
unordered originals. It runs for every path that renders tags, so one filter
covers `the_tags()`, the Post Terms block, and theme code alike.

## Block Themes

Add a **Post Terms** block to your template and set it to **Tags**. Order, CSS class, and separator all apply.

Since 1.2.0 the block is rendered by WordPress itself. The plugin applies order and classes before the block runs and then adjusts only the separator, so the block keeps its own wrapper markup, colour and typography settings, alignment, and prefix and suffix text.

Earlier versions replaced the block's output with markup of their own, which discarded those settings. If you have CSS targeting this block, two things changed in 1.2.0:

- The wrapper's class list and its order changed, because it now comes from WordPress. Target `.wp-block-post-terms` rather than matching an exact class string.
- Tag links no longer carry a class by default, since `tag` now means "no class". Target `.wp-block-post-terms a`, or set an explicit class in settings.

## Paths That Are Not Ordered

The plugin reorders at `get_the_terms`. Functions that bypass it return WordPress's default order:

| Function | Ordered? |
| --- | --- |
| `the_tags()`, `get_the_tags()`, `get_the_term_list()` | Yes |
| `wp_get_post_tags()`, `wp_get_post_terms()`, `wp_get_object_terms()` | No |

If your theme uses `wp_get_post_tags()`, switch to `get_the_tags()` or call `settagord_get_ordered_post_tags()` directly.

---

[← Usage](usage.md) | [Documentation Home](index.md) | [Troubleshooting →](troubleshooting.md)
