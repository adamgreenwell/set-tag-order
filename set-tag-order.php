<?php
/**
 * Plugin Name: Set Tag Order
 * Plugin URI: https://github.com/adamgreenwell/set-tag-order
 * Description: Allows setting custom order for post tags in the block editor
 * Version:     1.2.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Adam Greenwell
 * Author URI: https://adamgreenwell.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: set-tag-order
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin version, used to version enqueued assets.
 *
 * Asset versions were previously derived from filemtime(), which stats the
 * filesystem on every page load, emits a warning if a file is missing, and
 * produces a different cache-busting value on every install.
 *
 * @since 1.2.0
 */
if ( ! defined( 'SETTAGORD_VERSION' ) ) {
	define( 'SETTAGORD_VERSION', '1.2.0' );
}

/**
 * Plugin debugging function
 *
 * Logs debug messages when debug mode is enabled
 *
 * @since 1.0.4 - Renamed in 1.1.0
 * @param string $message The message to log
 * @return void
 */
function settagord_debug_log( $message ) {
	if ( settagord_debug_enabled() ) {
		error_log( '[Set Tag Order Debug] ' . $message );
	}
}

/**
 * Whether debug logging is enabled.
 *
 * Callers on hot paths should check this before building a log message, so
 * that string formatting only happens when the message will actually be
 * written. The option itself is autoloaded, so this is a cache lookup.
 *
 * @since 1.2.0
 * @return bool True when debug mode is on.
 */
function settagord_debug_enabled() {
	return (bool) get_option( 'settagord_debug_mode', false );
}

/**
 * Get saved tag order, migrating the legacy meta key when present.
 *
 * @since 1.1.3
 * @param int $post_id Post ID.
 * @return string Comma-separated tag order.
 */
function settagord_get_tag_order_meta( $post_id ) {
	$tag_order = get_post_meta( $post_id, '_settagord', true );

	if ( '' !== $tag_order ) {
		return $tag_order;
	}

	$legacy_tag_order = get_post_meta( $post_id, '_tag_order', true );

	if ( '' === $legacy_tag_order ) {
		return '';
	}

	update_post_meta( $post_id, '_settagord', $legacy_tag_order );
	delete_post_meta( $post_id, '_tag_order' );

	return $legacy_tag_order;
}

/**
 * Save tag order metadata and remove the pre-1.1 prefixed meta key.
 *
 * @since 1.1.3
 * @param int    $post_id   Post ID.
 * @param string $tag_order Comma-separated tag order.
 * @return void
 */
function settagord_update_tag_order_meta( $post_id, $tag_order ) {
	update_post_meta( $post_id, '_settagord', $tag_order );
	delete_post_meta( $post_id, '_tag_order' );
}

/**
 * Delete both current and legacy tag order metadata.
 *
 * @since 1.1.3
 * @param int $post_id Post ID.
 * @return void
 */
function settagord_delete_tag_order_meta( $post_id ) {
	delete_post_meta( $post_id, '_settagord' );
	delete_post_meta( $post_id, '_tag_order' );
}

/**
 * Hook to synchronize tag order when post is loaded in the Classic Editor
 *
 * This previously required a 'load-post' nonce, which WordPress core never
 * issues for post.php, so the synchronization never ran. A nonce is also the
 * wrong control here: this is not a user-submitted action but an idempotent
 * normalization of stored order against the post's current tags. The edit
 * capability is the meaningful check.
 *
 * @since 1.0.4
 */
add_action('load-post.php', function() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the post ID from the admin edit screen URL; access is gated on the edit_post capability below.
	if (!isset($_GET['post'])) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
	$post_id = absint($_GET['post']);

	if (!$post_id || !current_user_can('edit_post', $post_id)) {
		return;
	}

	settagord_synchronize_on_load($post_id);
});

/**
 * Hook to synchronize tag order when post is loaded in Block Editor via REST API
 *
 * @since 1.0.4
 * @param WP_REST_Response|null $response Current response
 * @param WP_Post              $post     Post object
 * @param WP_REST_Request      $request  Request object
 * @return WP_REST_Response|null
 */
add_filter('rest_prepare_post', function($response, $post, $request) {
	// Verify nonce for REST API requests
	if (!isset($_SERVER['HTTP_X_WP_NONCE']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])), 'wp_rest')) {
		return $response;
	}

	if (!empty($post->ID) && $request->get_method() === 'GET') {
		// Only process for individual post requests with an edit context
		if ($request->get_param('context') === 'edit' && current_user_can('edit_post', $post->ID)) {
			settagord_synchronize_on_load($post->ID);
		}
	}
	return $response;
}, 10, 3);

/**
 * Filter to modify tag output
 *
 * Affects get_the_tags() and the_tags() functions to respect custom order
 *
 * @since 1.0.0
 * @param array  $terms    Array of term objects
 * @param int    $post_id  Post ID
 * @param string $taxonomy Taxonomy name
 * @return array Modified array of term objects
 */
add_filter('get_the_terms', function($terms, $post_id, $taxonomy) {
	// Get post type
	$post_type = get_post_type($post_id);

	// Check if this post type supports tags and if we're dealing with tags
	if ($taxonomy !== 'post_tag' || !$terms || is_wp_error($terms) ||
	    !in_array($post_type, settagord_get_post_types_with_tags())) {
		return $terms;
	}

	return settagord_order_tags($terms, $post_id);
}, 10, 3);

/**
 * Filter tag cloud widget to use custom order
 *
 * @since 1.0.3
 * @param array  $terms      Array of term objects
 * @param array  $taxonomies Array of taxonomy names
 * @param array  $args       Query arguments
 * @return array Modified array of term objects
 */
add_filter('get_terms', function($terms, $taxonomies, $args) {
	global $post;

	// Only filter if this is a tag cloud for post_tag
	if (!is_array($taxonomies) || !in_array('post_tag', $taxonomies) || !isset($args['widget_id']) || $args['widget_id'] !== 'tag_cloud') {
		return $terms;
	}

	if (!$post) {
		return $terms;
	}

	// Get ordered tags
	$ordered_tags = settagord_get_ordered_post_tags($post->ID);
	if (!$ordered_tags) {
		return $terms;
	}

	return $ordered_tags;
}, 10, 3);

/**
 * Apply the custom CSS class to tag links.
 *
 * Ordering is deliberately not handled here. get_the_term_list() builds its
 * link array by iterating the terms returned from get_the_terms(), which this
 * plugin has already ordered, so the links arrive in the correct order. This
 * filter only needs to add the configured class, and it does so by editing the
 * existing anchor rather than rebuilding it - which preserves rel="tag" and any
 * attributes contributed by other plugins.
 *
 * @since 1.0.3
 * @param array $links Array of tag link HTML
 * @return array Modified array of tag link HTML
 */
add_filter('term_links-post_tag', function($links) {
	// Only apply in frontend
	if (is_admin() || empty($links) || !is_array($links)) {
		return $links;
	}

	$classes = settagord_get_custom_link_classes();
	if (empty($classes)) {
		return $links;
	}

	return array_map('settagord_add_classes_to_link', $links);
}, 20, 1);

/**
 * Get the configured tag link classes as an array.
 *
 * Returns an empty array for the historical default of 'tag', which core
 * already omits from the markup.
 *
 * @since 1.2.0
 * @return array List of class names to add.
 */
function settagord_get_custom_link_classes() {
	$custom_class = (string) get_option('settagord_class', 'tag');

	if ('' === trim($custom_class) || 'tag' === trim($custom_class)) {
		return array();
	}

	return array_values(array_filter(array_map('trim', explode(' ', $custom_class))));
}

/**
 * Add the configured CSS classes to a single anchor without rebuilding it.
 *
 * @since 1.2.0
 * @param string $link Anchor HTML.
 * @return string Anchor HTML with the custom classes applied.
 */
function settagord_add_classes_to_link($link) {
	$classes = settagord_get_custom_link_classes();

	if (empty($classes) || !is_string($link) || '' === $link) {
		return $link;
	}

	$processor = new WP_HTML_Tag_Processor($link);

	if (!$processor->next_tag('a')) {
		return $link;
	}

	foreach ($classes as $class) {
		$processor->add_class($class);
	}

	return $processor->get_updated_html();
}

/**
 * Apply the custom separator to the core/post-terms block.
 *
 * core/post-terms renders its separator inline from the block attribute and
 * exposes no filter for it, so rewriting the rendered separator spans is the
 * only way to honour the plugin setting.
 *
 * Everything else is left to core. Term order comes from the get_the_terms
 * filter and the custom class from term_links-post_tag, both of which run
 * before the block renders, so core's own renderer produces correctly ordered
 * and classed output. Leaving it in place keeps the wrapper that
 * get_block_wrapper_attributes() builds - block supports, alignment, link
 * colour, and the prefix and suffix spans - which a hand-rolled renderer
 * would silently discard.
 *
 * @since 1.0.6
 * @param string        $block_content The block content
 * @param array         $parsed_block  The full block, including name and attributes
 * @param WP_Block|null $instance      The block instance, unused
 * @return string Modified block content
 */
function settagord_filter_post_terms_block($block_content, $parsed_block, $instance = null) {
	// Only process post-terms blocks
	if (empty($parsed_block['blockName']) || $parsed_block['blockName'] !== 'core/post-terms') {
		return $block_content;
	}

	// Only process if this is for the post_tag taxonomy
	$term_type = isset($parsed_block['attrs']['term']) ? $parsed_block['attrs']['term'] : 'post_tag';
	if ($term_type !== 'post_tag' || !is_string($block_content) || '' === $block_content) {
		return $block_content;
	}

	$custom_separator = get_option('settagord_separator', '');
	if ('' === $custom_separator) {
		return $block_content;
	}

	settagord_debug_log('Applying custom separator to core/post-terms output');

	return preg_replace_callback(
		'#(<span class="wp-block-post-terms__separator">)(.*?)(</span>)#s',
		function ($matches) use ($custom_separator) {
			return $matches[1] . esc_html($custom_separator) . $matches[3];
		},
		$block_content
	);
}
add_filter('render_block', 'settagord_filter_post_terms_block', 10, 3);

/**
 * Log term ordering results when debug mode is enabled.
 *
 * This filter runs on every get_the_terms() call, so the log message is only
 * assembled once debug mode is confirmed on. Building it unconditionally cost
 * an array_map and an implode on every request for output nobody reads.
 *
 * @since 1.0.6
 * @param array|WP_Error $terms    Array of term objects
 * @param int            $post_id  Post ID
 * @param string         $taxonomy Taxonomy name
 * @return array|WP_Error Unmodified terms
 */
function settagord_debug_get_the_terms($terms, $post_id, $taxonomy) {
	if ($taxonomy !== 'post_tag' || !settagord_debug_enabled()) {
		return $terms;
	}

	settagord_debug_log("get_the_terms filter for post $post_id with taxonomy $taxonomy returned " .
		(is_array($terms) ? count($terms) : 'non-array') . " terms");

	if (is_array($terms) && !empty($terms)) {
		settagord_debug_log('Terms: ' . implode(', ', wp_list_pluck($terms, 'name')));
	} else {
		settagord_debug_log('No terms found or terms is not an array');
	}

	return $terms;
}
add_filter('get_the_terms', 'settagord_debug_get_the_terms', 999, 3);

/**
 * Log post-terms block attributes when debug mode is enabled.
 *
 * @since 1.0.6
 * @param string|null $pre_render   Pre-rendered content, always returned unchanged
 * @param array       $parsed_block The parsed block
 * @return string|null Unmodified pre-render value
 */
function settagord_debug_pre_render_block($pre_render, $parsed_block) {
	if (!settagord_debug_enabled()) {
		return $pre_render;
	}

	if (!empty($parsed_block['blockName']) && $parsed_block['blockName'] === 'core/post-terms') {
		settagord_debug_log('Pre-render for post-terms block: ' . wp_json_encode($parsed_block['attrs']));
	}

	return $pre_render;
}
add_filter('pre_render_block', 'settagord_debug_pre_render_block', 10, 2);

/**
 * Filter the_tags output to apply custom separator
 *
 * @since 1.0.3
 * @param string $output HTML output
 * @param string $before Text to display before
 * @param string $sep    Separator text
 * @param string $after  Text to display after
 * @return string Modified HTML output
 */
function settagord_filter_the_tags($output, $before, $sep, $after) {
	$custom_separator = get_option('settagord_separator', '');

	if ('' === $custom_separator || !is_string($output) || '' === $output) {
		return $output;
	}

	settagord_debug_log("Filter the_tags called - Default separator: '$sep', Custom: '$custom_separator'");

	$replacement = '<span class="settagord-tag-separator">' . esc_html($custom_separator) . '</span>';

	return settagord_replace_tag_separators($output, $replacement, $sep, $before, $after);
}
add_filter('the_tags', 'settagord_filter_the_tags', 10, 4);

/**
 * Replace the separators that sit between tag anchors in the_tags() output.
 *
 * the_tags() hands us finished HTML built by get_the_term_list() as
 * $before . implode( $sep, $links ) . $after. We need to swap the joining text
 * for the configured separator without touching anything inside the anchors
 * themselves - hrefs and tag names can contain the same characters the
 * separator is made of.
 *
 * Because the filter is given $sep, $before, and $after, none of that has to be
 * inferred from the markup. The previous implementation sampled the text
 * between the first two anchors and ran str_replace() for it across the whole
 * string, so a separator of ", " also rewrote a comma inside a tag name or URL.
 *
 * Two things keep the replacement contained:
 *
 * 1. $before and $after are sliced off first. They are caller-supplied markup
 *    that may legitimately contain both anchors and separator characters, and
 *    they are handed back untouched.
 * 2. The separator is only matched where implode() can have put it - directly
 *    between a closing and an opening anchor. A separator occurring anywhere
 *    else is, by construction, part of a tag name or an attribute.
 *
 * If the markup does not match - because another plugin filtering
 * term_links-post_tag wrapped each link in extra elements, for instance - the
 * output is returned unchanged. Leaving the theme separator in place is the
 * right failure mode; a looser match risks corrupting the tag text.
 *
 * @since 1.2.0
 * @param string $output      Rendered tag list HTML.
 * @param string $replacement Separator markup to insert between anchors.
 * @param string $sep         Separator get_the_term_list() joined the links with.
 * @param string $before      Markup prepended by the caller.
 * @param string $after       Markup appended by the caller.
 * @return string Output with separators replaced.
 */
function settagord_replace_tag_separators($output, $replacement, $sep = '', $before = '', $after = '') {
	$prefix = '';
	$suffix = '';

	if ('' !== $before && 0 === strpos($output, $before)) {
		$prefix = $before;
		$output = substr($output, strlen($before));
	}

	if ('' !== $after && strlen($output) >= strlen($after) && substr($output, -strlen($after)) === $after) {
		$suffix = $after;
		$output = substr($output, 0, -strlen($after));
	}

	// \b keeps <a> from matching <article> and friends.
	$pattern = '#</a>' . preg_quote($sep, '#') . '<a\b#';

	// A callback rather than a replacement string: the separator is arbitrary
	// user input, and esc_html() does not neutralise the "$" and backslash that
	// preg_replace() would read as backreferences.
	$replaced = preg_replace_callback(
		$pattern,
		function () use ($replacement) {
			return '</a>' . $replacement . '<a';
		},
		$output
	);

	if (null === $replaced) {
		settagord_debug_log('Separator replacement failed; returning the_tags output unchanged');

		return $prefix . $output . $suffix;
	}

	return $prefix . $replaced . $suffix;
}

/**
 * Add custom CSS for tag separators
 *
 * The style is attached to a stylesheet handle owned by this plugin rather
 * than to wp-block-library. Attaching to a core handle means the CSS is
 * silently dropped whenever that handle is not enqueued, which is the case on
 * classic themes and on any site that disables the core block styles.
 *
 * @since 1.0.5
 * @return void
 */
function settagord_custom_css() {
	$custom_separator = get_option('settagord_separator', '');

	if ('' === $custom_separator) {
		return;
	}

	$custom_css = '
		.settagord-tag-separator {
			display: inline-block;
			margin: 0 0.25em;
			font-size: 1.2em;
			color: #999;
		}
	';

	// An empty registered stylesheet gives wp_add_inline_style() a handle that
	// is guaranteed to be present, without shipping an extra HTTP request.
	wp_register_style('settagord-inline', false, [], SETTAGORD_VERSION);
	wp_enqueue_style('settagord-inline');
	wp_add_inline_style('settagord-inline', $custom_css);
}
add_action('wp_enqueue_scripts', 'settagord_custom_css');

/**
 * Synchronize tag order metadata whenever post tags are updated
 *
 * This ensures tag order remains consistent even when directly modified via
 * the Block Editor's tag component rather than through our custom UI.
 *
 * @since 1.0.4
 * @param int    $post_id   Post ID
 * @param array  $terms     Array of terms being set
 * @param array  $tt_ids    Array of term taxonomy IDs
 * @param string $taxonomy  Taxonomy slug
 * @param bool   $append    Whether to append terms
 * @param array  $old_tt_ids Array of old term taxonomy IDs
 * @return void
 */
add_action('set_object_terms', function($post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
	// Only process post_tag taxonomy
	if ($taxonomy !== 'post_tag') {
		return;
	}

	// Skip processing during autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Don't process revisions
	if (wp_is_post_revision($post_id)) {
		return;
	}

	// Don't process auto-drafts
	if (get_post_status($post_id) === 'auto-draft') {
		return;
	}

	settagord_debug_log("set_object_terms called for post $post_id with " . count($tt_ids) . " tags");

	// Get the current tag order
	$current_order = settagord_get_tag_order_meta($post_id);
	$current_order_array = $current_order ? explode(',', $current_order) : [];

	if (empty($current_order_array) && empty($tt_ids)) {
		// Nothing to do if both are empty
		return;
	}

	// Get the currently assigned term IDs after WordPress has updated relationships.
	$term_ids = wp_get_object_terms($post_id, 'post_tag', ['fields' => 'ids']);
	if (is_wp_error($term_ids)) {
		settagord_debug_log("Unable to sync tag order for post $post_id: " . $term_ids->get_error_message());
		return;
	}
	$term_ids = array_map('intval', $term_ids);

	// If we're replacing terms (not appending)
	if (!$append) {
		if (empty($term_ids)) {
			// All tags were removed, clear the order
			settagord_delete_tag_order_meta($post_id);
			settagord_debug_log("All tags removed, cleared order for post $post_id");
			return;
		}

		// Keep existing order for tags that remain, add new tags at the end
		$new_order = [];

		// First, retain existing tag order for tags that remain
		foreach ($current_order_array as $id) {
			if (in_array($id, $term_ids)) {
				$new_order[] = $id;
			}
		}

		// Add any new tags that aren't in the current order
		foreach ($term_ids as $id) {
			if (!in_array($id, $new_order)) {
				$new_order[] = $id;
			}
		}

		// Update the meta
		settagord_update_tag_order_meta($post_id, implode(',', $new_order));
		settagord_debug_log("Updated tag order for post $post_id: " . implode(',', $new_order));
	} else {
		// Appending terms, add the new ones to the existing order
		$new_order = $current_order_array;

		foreach ($term_ids as $id) {
			if (!in_array($id, $new_order)) {
				$new_order[] = $id;
			}
		}

		settagord_update_tag_order_meta($post_id, implode(',', $new_order));
		settagord_debug_log("Appended to tag order for post $post_id: " . implode(',', $new_order));
	}
}, 10, 6);

/**
 * Update post meta logging for debugging
 *
 * @since 1.0.3
 * @param int    $meta_id    ID of updated metadata entry
 * @param int    $post_id    Post ID
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Metadata value
 * @return void
 */
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
	if ($meta_key === '_settagord') {
		settagord_debug_log('Updated tag order for post ' . $post_id . ': ' . $meta_value);
	}
}, 10, 4);

// Include dependencies
require_once plugin_dir_path( __FILE__ ) . 'inc/admin/settings.php';

/**
 * Improved detection of Block Editor vs Classic Editor
 *
 * @since 1.0.4
 * @return bool True if using Block Editor, false if using Classic Editor
 */
function settagord_is_using_block_editor() {
	// Check if we're in a REST API request - that's a strong indicator of Block Editor
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return true;
	}

	// Check if this is an AJAX request for the heartbeat API
	if (defined('DOING_AJAX') && DOING_AJAX) {
		// Heartbeat is used by both editors, so we need additional checks
		$action = isset($_POST['action']) ? sanitize_key($_POST['action']) : ''; // Sanitize action

		// Check for heartbeat action AND verify its nonce
		if ($action === 'heartbeat') { 
		    // Verify the heartbeat nonce before accessing $_POST['data']
		    if (isset($_POST['_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce'])), 'heartbeat-nonce')) {
			    // Note: We are only checking isset() here, not using the value directly.
			    if (isset($_POST['data']) && isset($_POST['data']['wp-refresh-post-lock'])) { 
				    return true; // Detected Block Editor via heartbeat data
			    }
            } else {
                // Nonce failed or missing for heartbeat, assume not Block Editor context.
                return false;
            }
		}
		// If AJAX action is not heartbeat, it's likely Classic Editor or other non-heartbeat AJAX
		return false; 
	}

	// Check if we're loading post.php or post-new.php
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen) {
		// Can't determine, default to false
		return false;
	}

	// If we have a valid screen object and it has is_block_editor method
	if (method_exists($screen, 'is_block_editor')) {
		return $screen->is_block_editor();
	}

	// Last resort - check global WordPress version
	global $wp_version;
	if (version_compare($wp_version, '5.0', '>=')) {
		// Check for Classic Editor plugin
		if (function_exists('classic_editor_init')) {
			// Classic Editor plugin is active
			$editor_option = get_option('classic-editor-replace');

			// Option is 'block' means "default" is Block Editor
			if ($editor_option === 'block') {
				// Check post-specific setting
				global $post;
				if ($post) {
					$post_option = get_post_meta($post->ID, 'classic-editor-remember', true);
					return $post_option !== 'classic-editor';
				}
				return true; // Default to Block Editor
			}

			// Option is 'classic' or not set means "default" is Classic Editor
			return false;
		}

		// No Classic Editor plugin, assume Block Editor
		return true;
	}

	// Older WordPress version
	return false;
}

/**
 * Register meta field for tag order for all post types that support tags
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'init', function () {
	$post_types = settagord_get_post_types_with_tags();

	foreach ( $post_types as $post_type ) {
		register_post_meta( $post_type, '_settagord', [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'default'       => ''
		] );
	}
} );

/**
 * Get post types that support tags
 *
 * Returns an array of post type names that have tag support enabled.
 *
 * @since 1.0.0
 * @return array Array of post type names
 */
function settagord_get_post_types_with_tags( $refresh = false ) {
	static $supported_types = null;

	if ( $refresh || null === $supported_types ) {
		$post_types      = get_post_types( [ 'public' => true ], 'objects' );
		$supported_types = [];

		foreach ( $post_types as $post_type ) {
			if ( is_object_in_taxonomy( $post_type->name, 'post_tag' ) ) {
				$supported_types[] = $post_type->name;
			}
		}
	}

	return $supported_types;
}

/**
 * Rebuild the cached post type list.
 *
 * settagord_get_post_types_with_tags() is called from the get_the_terms
 * filter, which runs many times per request, so its result is memoized. Post
 * type and taxonomy registration can change after the first call, so the cache
 * is rebuilt whenever either is touched.
 *
 * @since 1.2.0
 * @return void
 */
function settagord_flush_post_type_cache() {
	settagord_get_post_types_with_tags( true );
}
add_action( 'registered_post_type', 'settagord_flush_post_type_cache' );
add_action( 'unregistered_post_type', 'settagord_flush_post_type_cache' );
add_action( 'registered_taxonomy', 'settagord_flush_post_type_cache' );
add_action( 'unregistered_taxonomy', 'settagord_flush_post_type_cache' );
add_action( 'registered_taxonomy_for_object_type', 'settagord_flush_post_type_cache' );
add_action( 'unregistered_taxonomy_for_object_type', 'settagord_flush_post_type_cache' );


/**
 * Order tags based on saved meta
 *
 * @since 1.0.0
 * @param array  $tags    Array of tag term objects
 * @param int    $post_id Post ID
 * @return array Ordered array of tag term objects
 */
function settagord_order_tags( $tags, $post_id ) {
	if ( ! $tags || ! $post_id ) {
		return $tags;
	}

	$tag_order = settagord_get_tag_order_meta( $post_id );
	settagord_debug_log( 'Tag Order for post ' . $post_id . ': ' . $tag_order );

	if ( empty( $tag_order ) ) {
		return $tags;
	}

	// Create an associative array of tags indexed by term_id for faster lookup
	$tags_by_id = array();
	foreach ( $tags as $tag ) {
		$tags_by_id[$tag->term_id] = $tag;
	}

	$order = array_map( 'intval', explode( ',', $tag_order ) );
	$ordered_tags = array();

	// First add all tags that are in the saved order
	foreach ( $order as $tag_id ) {
		if ( isset( $tags_by_id[$tag_id] ) ) {
			$ordered_tags[] = $tags_by_id[$tag_id];
			// Remove from the associative array to mark as processed
			unset( $tags_by_id[$tag_id] );
		}
	}

	// Add any remaining unordered tags
	foreach ( $tags_by_id as $tag ) {
		$ordered_tags[] = $tag;
	}

	settagord_debug_log( 'Ordered ' . count( $ordered_tags ) . ' tags for post ' . $post_id );
	return $ordered_tags;
}

/**
 * Get ordered post tags
 *
 * Retrieves tags for the specified post in the custom order
 * defined by the user.
 *
 * @since 1.0.0
 * @param int|null $post_id Post ID or null for current post
 * @return array|false Array of tag objects or false if no tags or unsupported post type
 */
function settagord_get_ordered_post_tags( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	// Check if this post type supports tags
	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, settagord_get_post_types_with_tags() ) ) {
		return false;
	}

	$tags = get_the_tags( $post_id );
	if ( ! $tags ) {
		return false;
	}

	return settagord_order_tags( $tags, $post_id );
}

if ( ! function_exists( 'get_ordered_post_tags' ) ) {
	/**
	 * Legacy template helper for retrieving ordered post tags.
	 *
	 * @since 1.1.3
	 * @param int|null $post_id Post ID or null for current post.
	 * @return array|false Array of tag objects or false if no tags or unsupported post type.
	 */
	function get_ordered_post_tags( $post_id = null ) {
		return settagord_get_ordered_post_tags( $post_id );
	}
}

/**
 * Display post tags in custom order with specified formatting
 *
 * This is a template tag that can be used in theme files to display
 * the post tags in the order specified by the user.
 *
 * @since 1.0.0
 * @param string  $before HTML to display before the list of tags
 * @param string  $sep    HTML to display between tags (overridden by plugin settings if set)
 * @param string  $after  HTML to display after the list of tags
 * @param int     $post_id Post ID, defaults to current post
 * @return void
 */
function settagord_the_ordered_post_tags($before = '', $sep = '', $after = '', $post_id = 0) {
	if (!$post_id) {
		$post_id = get_the_ID();
	}

	$tags = settagord_get_ordered_post_tags($post_id);
	if (!$tags) {
		return;
	}

	// Get separator from settings or use provided parameter
	$separator = get_option('settagord_separator');
	if ($separator === '' && $sep !== '') {
		$separator = $sep;
	}

	// Get class
	$class = get_option('settagord_class', 'tag');

	$html = $before;

	foreach ($tags as $index => $tag) {
		if ($index > 0 && !empty($separator)) {
			$html .= '<span class="settagord-tag-separator">' . esc_html($separator) . '</span>';
		} elseif ($index > 0) {
			$html .= $sep; // Use default separator if custom is empty
		}

		$html .= sprintf(
			'<a href="%s" class="%s">%s</a>',
			get_tag_link($tag->term_id),
			esc_attr($class),
			esc_html($tag->name)
		);
	}

	$html .= $after;

	echo wp_kses_post($html);
}

if ( ! function_exists( 'the_ordered_post_tags' ) ) {
	/**
	 * Legacy template helper for displaying ordered post tags.
	 *
	 * @since 1.1.3
	 * @param string $before  HTML to display before the list of tags.
	 * @param string $sep     HTML to display between tags.
	 * @param string $after   HTML to display after the list of tags.
	 * @param int    $post_id Post ID, defaults to current post.
	 * @return void
	 */
	function the_ordered_post_tags( $before = '', $sep = '', $after = '', $post_id = 0 ) {
		settagord_the_ordered_post_tags( $before, $sep, $after, $post_id );
	}
}

/**
 * AJAX handler for setting editor mode
 *
 * @since 1.0.4
 * @return void
 */
function settagord_ajax_set_editor_mode() {
	$user_id = get_current_user_id();
	
	// Verify nonce with proper sanitization
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'settagord_editor_mode')) {
		wp_send_json_error('Invalid nonce');
	}

	if (isset($_POST['mode']) && $_POST['mode'] === 'classic') {
		// Store in user meta
		update_user_meta($user_id, 'settagord_detected_editor', 'classic');
		settagord_debug_log('JavaScript detection confirmed Classic Editor is active');
	} else {
		update_user_meta($user_id, 'settagord_detected_editor', 'block');
		settagord_debug_log('JavaScript detection confirmed Block Editor is active');
	}

	wp_die();
}
add_action('wp_ajax_settagord_editor_mode', 'settagord_ajax_set_editor_mode');

/**
 * Render custom tag box for Classic Editor
 *
 * Displays a drag-and-drop interface for ordering tags in the Classic Editor
 *
 * @since 1.0.3
 * @param WP_Post $post Post object
 * @return void
 */
function settagord_render_custom_tag_box($post) {
	$all_tags = get_tags(['hide_empty' => false]);
	$post_tags = get_the_tags($post->ID) ?: [];
	$tag_order = settagord_get_tag_order_meta($post->ID);
	$ordered_ids = $tag_order ? explode(',', $tag_order) : [];

    // Sort post tags according to the saved order
    if (!empty($ordered_ids) && !empty($post_tags)) {
        $ordered_tags_map = array_flip($ordered_ids);
        usort($post_tags, function ($a, $b) use ($ordered_tags_map) {
            $pos_a = isset($ordered_tags_map[$a->term_id]) ? $ordered_tags_map[$a->term_id] : PHP_INT_MAX;
            $pos_b = isset($ordered_tags_map[$b->term_id]) ? $ordered_tags_map[$b->term_id] : PHP_INT_MAX;
            return $pos_a <=> $pos_b;
        });
    }

	// Include the partial template file
	// Pass necessary variables to the partial's scope
	include plugin_dir_path(__FILE__) . 'partials/custom-tag-box-partial.php';
}

/**
 * Add custom meta box only for Classic Editor
 *
 * @since 1.0.4
 * @return void
 */
function settagord_add_meta_box() {
	global $post;
	if (!$post) {
		settagord_debug_log('No $post object available - skipping meta box replacement');
		return;
	}

	// Only proceed if we're in Classic Editor
	if (settagord_is_using_block_editor()) {
		settagord_debug_log('Detected as Block Editor - skipping meta box replacement');
		return;
	}

	$post_types = settagord_get_post_types_with_tags();
	settagord_debug_log('Found post types with tags: ' . implode(', ', $post_types));

	foreach ($post_types as $post_type) {
		if ($post->post_type !== $post_type) {
			continue;
		}

		// First remove the default tags meta box
		remove_meta_box('tagsdiv-post_tag', $post_type, 'side');
		settagord_debug_log("Removed default tagsdiv-post_tag for $post_type");

		// Then add the custom one
		add_meta_box(
			'settagord_meta_box',
			'Tags', // Use standard name for familiarity
			'settagord_render_custom_tag_box',
			$post_type,
			'side',
			'high' // Use high priority to ensure it appears in a good position
		);
		settagord_debug_log("Added custom settagord_meta_box for $post_type");
	}
}
add_action('add_meta_boxes', 'settagord_add_meta_box', 100);

/**
 * Tag order synchronization for Block Editor (REST API) saves.
 *
 * This used to run on rest_pre_insert_{$post_type} and read
 * $prepared_post->tags. WP_REST_Posts_Controller::prepare_item_for_database()
 * never puts taxonomy terms on the prepared post - they are applied separately
 * by handle_terms() after the post is inserted - so that filter could not see
 * tags and never did anything.
 *
 * handle_terms() calls wp_set_object_terms(), which fires set_object_terms.
 * The set_object_terms handler above already covers REST saves, so no
 * additional REST hook is needed.
 *
 * @since 1.2.0
 */

/**
 * Save meta box data
 *
 * Processes and saves the tag order when a post is saved
 *
 * @since 1.0.3
 * @param int $post_id Post ID being saved
 * @return void
 */
add_action('save_post', function($post_id) {
	// Verify nonce with proper sanitization
	if (!isset($_POST['settagord_meta_box_nonce']) ||
	    !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['settagord_meta_box_nonce'])), 'settagord_meta_box')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	if (isset($_POST['post_tags'])) {
		// Make sure we're getting numeric IDs only
		$tag_ids = array_filter(
			explode(',', sanitize_text_field(wp_unslash($_POST['post_tags']))),
			'is_numeric'
		);

		// Convert to integers to ensure proper comparison
		$tag_ids = array_map('intval', $tag_ids);

		// Get existing tags to verify IDs are valid
		$all_tags = get_tags(['hide_empty' => false]);
		$valid_tag_ids = wp_list_pluck($all_tags, 'term_id');

		// Filter out any invalid IDs
		$valid_tags = array_intersect($tag_ids, $valid_tag_ids);

		// Update post tags
		wp_set_post_tags($post_id, $valid_tags, false);

		settagord_debug_log('Saving post_tags: ' . implode(',', $valid_tags));
	}

	if (isset($_POST['settagord'])) {
		$tag_order = sanitize_text_field(wp_unslash($_POST['settagord']));
		settagord_update_tag_order_meta( $post_id, $tag_order );
		settagord_debug_log('Saving settagord: ' . $tag_order);
	}
}, 10, 1);

/**
 * AJAX handler for creating new tags
 *
 * @since 1.0.3
 * @return void
 */
add_action('wp_ajax_settagord_add_tag', function() {
	// Verify nonce with proper sanitization
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'settagord_add_tag_nonce')) {
		wp_send_json_error('Invalid nonce');
	}

	$taxonomy = get_taxonomy('post_tag');
	if (!$taxonomy || !current_user_can($taxonomy->cap->edit_terms)) {
		wp_send_json_error('You are not allowed to create tags.');
	}

	// Validate and sanitize tag name
	if (!isset($_POST['tag_name']) || empty($_POST['tag_name'])) {
		wp_send_json_error('Tag name is required');
	}

	$tag_name = sanitize_text_field(wp_unslash($_POST['tag_name']));
	$tag = wp_insert_term($tag_name, 'post_tag');

	if (is_wp_error($tag)) {
		wp_send_json_error($tag->get_error_message());
	} else {
		// Get the complete term object to ensure we have the correct data
		$term = get_term($tag['term_id'], 'post_tag');
		if (is_wp_error($term)) {
			wp_send_json_error('Error retrieving newly created tag.');
		} else {
			wp_send_json_success([
				'term_id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug
			]);
		}
	}
});

/**
 * Register and enqueue scripts and styles for the plugin
 *
 * @since 1.0.5
 */
function settagord_register_assets() {
	// Register the main script
	wp_register_script(
		'settagord-script',
		plugins_url('/assets/js/set-tag-order.js', __FILE__),
		['wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'],
		SETTAGORD_VERSION,
		[
			'in_footer' => true,
			'strategy' => 'defer'
		]
	);

	// Register the main styles
	wp_register_style(
		'settagord-panel-styles',
		plugins_url('/assets/css/tag-order-panels.css', __FILE__),
		[],
		SETTAGORD_VERSION
	);
}
add_action('init', 'settagord_register_assets');

/**
 * Enqueue Block Editor assets
 *
 * @since 1.0.5
 */
add_action('enqueue_block_editor_assets', function() {
	global $post_type;
	
	if (!$post_type || !in_array($post_type, settagord_get_post_types_with_tags())) {
		return;
	}

	// Only load Block Editor assets when Block Editor is detected
	if (settagord_is_using_block_editor()) {
		settagord_debug_log("Loading Block Editor assets for post type: {$post_type}");
		
		wp_enqueue_script('settagord-script');
		wp_enqueue_style('settagord-panel-styles');
	}
});

/**
 * Load jQuery UI for Classic Editor
 *
 * @since 1.0.5
 * @param string $hook Current admin page
 * @return void
 */
add_action('admin_enqueue_scripts', function($hook) {
    global $pagenow, $post;
    
    // Check if we are on a post edit screen
    if (!in_array($hook, ['post.php', 'post-new.php'])) {
        return;
    }

    // Enqueue Editor Detection Script (runs on both editors)
    $editor_detect_handle = 'settagord-editor-detect';
    wp_enqueue_script(
        $editor_detect_handle,
        plugin_dir_url(__FILE__) . 'js/admin-editor-detect.js',
        [], // No dependencies needed for this simple script
        SETTAGORD_VERSION,
        true // Load in footer
    );

    // Localize script with necessary data
    wp_localize_script($editor_detect_handle, 'settagordEditorData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('settagord_editor_mode')
    ]);

    // Get the current screen and post type
    $screen = get_current_screen();
    $post_type = null;
    if ($screen && isset($screen->post_type)) {
        $post_type = $screen->post_type;
    } else {
        // Fallback: Try to determine post type from post object (less reliable here)
        $current_post_id = null;
        if ($post) {
            $current_post_id = $post->ID;
        } elseif (isset($_GET['post'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET for post ID context for enqueuing scripts.
            $current_post_id = absint($_GET['post']);
        } elseif (isset($_POST['post_ID'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST for post ID context for enqueuing scripts.
            $current_post_id = absint($_POST['post_ID']);
        }

        if ($current_post_id) {
            $post_type = get_post_type($current_post_id);
        } else {
             return; // Cannot determine post type
        }
    }
    
    if (!$post_type) {
        return;
    }

    // Removed problematic check: !post_type_supports($post_type, 'post-tag')
    // Rationale: If we reach this point, the meta box is supposed to be added,
    // which already implies tag support for the post type.
    // This check seems to fail intermittently due to hook timing.

    // --- Classic Editor Specific Assets ---
    // Only load the rest for Classic Editor
    if (function_exists('settagord_is_using_block_editor') && settagord_is_using_block_editor()) {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'settagord-admin-css', 
        plugin_dir_url(__FILE__) . 'css/set-tag-order-admin.css', 
        [], 
        SETTAGORD_VERSION // Versioning
    );

    // Enqueue JS
    wp_enqueue_script(
        'settagord-admin-js', 
        plugin_dir_url(__FILE__) . 'js/set-tag-order-admin.js', 
        ['jquery', 'jquery-ui-sortable', 'wp-util'], // Add dependencies
        SETTAGORD_VERSION, // Versioning
        true // Load in footer
    );

    // Prepare data for JavaScript
    $all_tags = get_tags(['hide_empty' => false]);
    $tags_for_js = array_map(function($tag) {
        return ['id' => $tag->term_id, 'text' => $tag->name];
    }, $all_tags);

	// Pass data to the script
	wp_localize_script('settagord-admin-js', 'settagordAdminData', [
		'allTags' => $tags_for_js,
		'ajaxurl' => admin_url('admin-ajax.php'),
		'addTagNonce' => wp_create_nonce('settagord_add_tag_nonce') // Added nonce for add tag AJAX
	]);

}, 20); // Change priority from default 10 to 20

/**
 * Synchronize tag order on post load
 *
 * This function ensures tag order is maintained when switching between editors
 * by checking and updating the tag order metadata when a post is loaded.
 *
 * @since 1.0.4
 * @param int $post_id Post ID being edited
 * @return void
 */
function settagord_synchronize_on_load($post_id) {
	// Skip for new posts, revisions, or auto-drafts
	if (
		!$post_id ||
		wp_is_post_revision($post_id) ||
		get_post_status($post_id) === 'auto-draft'
	) {
		return;
	}

	// Get current tags for the post
	$post_tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
	if (empty($post_tags)) {
		// No tags, make sure order is empty too
		settagord_delete_tag_order_meta($post_id);
		return;
	}

	// Get saved tag order
	$tag_order = settagord_get_tag_order_meta($post_id);
	$ordered_ids = $tag_order ? explode(',', $tag_order) : [];

	// Check if we need to synchronize
	$needs_sync = false;

	// Case 1: Order exists but doesn't match current tags
	if (!empty($ordered_ids)) {
		// Check if all current tags are in the order
		foreach ($post_tags as $tag_id) {
			if (!in_array($tag_id, $ordered_ids)) {
				$needs_sync = true;
				break;
			}
		}

		// Check if order contains tags that aren't assigned to the post
		if (!$needs_sync) {
			foreach ($ordered_ids as $order_id) {
				if (!in_array($order_id, $post_tags)) {
					$needs_sync = true;
					break;
				}
			}
		}
	}
	// Case 2: No order exists but we have tags
	else if (!empty($post_tags)) {
		$needs_sync = true;
	}

	if ($needs_sync) {
		// Create new order preserving the sequence of existing order
		$new_order = [];

		// First add tags that are in the existing order
		foreach ($ordered_ids as $id) {
			if (in_array($id, $post_tags)) {
				$new_order[] = $id;
			}
		}

		// Then add any remaining tags
		foreach ($post_tags as $id) {
			if (!in_array($id, $new_order)) {
				$new_order[] = $id;
			}
		}

		settagord_update_tag_order_meta($post_id, implode(',', $new_order));
		settagord_debug_log("Synchronized tag order on load for post $post_id: " . implode(',', $new_order));
	}
}
