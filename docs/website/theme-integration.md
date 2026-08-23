# Theme Integration

Set Tag Order controls the order tags are output in, and gives you three filters plus two settings over how that output looks.

## The core/post-terms Block

As of 1.2.0 the plugin no longer replaces the `core/post-terms` block renderer. Tag order and CSS classes are applied *before* the block renders, so the block keeps its own wrapper markup, alignment, link colour, block supports, and prefix and suffix text.

If you built around the previous behaviour, this is the change most likely to affect you — the markup is now core's, not the plugin's.

## Tag Separator

Set the separator under **Settings → Set Tag Order**. Leave the field empty to output no separator.

The separator is replaced only *between* tags. Earlier versions could also replace matching text inside tag names and URLs.

The separator stylesheet loads from a plugin-owned handle, so it still applies on themes that do not load the core block styles.

## Custom CSS Classes

Classes entered in settings are added to tag links so a theme can style them. Separate multiple classes with spaces.

Classes are applied without rebuilding the link, which preserves `rel="tag"` and any attributes added by other plugins.

## Filters

```php
apply_filters( 'settagord_ordered_tags', array $ordered_tags, int $post_id, array $tags );
apply_filters( 'settagord_separator', string $separator );
apply_filters( 'settagord_link_classes', array $classes );
```

### settagord_ordered_tags

Filter the resolved tag list for a post.

```php
add_filter( 'settagord_ordered_tags', function ( $ordered_tags, $post_id, $tags ) {
	// Return the tags in whatever order this site needs.
	return $ordered_tags;
}, 10, 3 );
```

As of 1.2.1 this filter runs exactly once per render, and it also runs on posts that have no saved order. Before that, callbacks never saw unordered posts and ran twice on ordered ones — so a callback that appended or counted could double up.

### settagord_separator

```php
add_filter( 'settagord_separator', function ( $separator ) {
	return ' · ';
} );
```

### settagord_link_classes

```php
add_filter( 'settagord_link_classes', function ( $classes ) {
	$classes[] = 'my-theme-tag';

	return $classes;
} );
```

## Translation

The plugin ships a translation template at `languages/set-tag-order.pot`. Settings, the tag box, and the editor panel are all translatable; before 1.2.0 they were hardcoded English despite the plugin declaring a text domain.

## Uninstalling

Deleting the plugin removes its options and tag order metadata, across all sites on multisite. Deactivating does not.

---

[← Usage](usage.md) | [Documentation Home](index.md) | [Troubleshooting →](troubleshooting.md)
