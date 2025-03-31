<?php
/**
 * Set Tag Order
 *
 * A WordPress plugin that allows users to set a custom display order for post tags
 * in both the Block Editor and Classic Editor.
 *
 * @package    SetTagOrder
 * @author     Adam Greenwell
 * @copyright  2024 Adam Greenwell
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Set Tag Order
 * Description: Allows setting custom order for post tags in the block editor
 * Version:     1.0.5
 * Author:      Adam Greenwell
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: set-tag-order
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Debug function
 *
 * @since 1.0.3
 * @param string $message The message to log
 * @return void
 */
function tag_order_debug_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && get_option( 'tag_order_debug_mode', false ) ) {
		// Use WordPress's built-in logging function when available
		if ( function_exists( 'wp_privacy_anonymize_data' ) ) {
			error_log( wp_privacy_anonymize_data( 'message', '[Set Tag Order] ' . $message ) );
		} else {
			error_log( '[Set Tag Order] ' . $message );
		}
	}
}

/**
 * Hook to synchronize tag order when post is loaded in editor
 *
 * @since 1.0.4
 */
add_action('load-post.php', function() {
	// Verify nonce for post loading
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'load-post')) {
		return;
	}

	if (isset($_GET['post'])) {
		$post_id = intval($_GET['post']);
		synchronize_tag_order_on_load($post_id);
	}
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
		if ($request->get_param('context') === 'edit') {
			synchronize_tag_order_on_load($post->ID);
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
	    !in_array($post_type, get_post_types_with_tags())) {
		return $terms;
	}

	return order_tags($terms, $post_id);
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
	$ordered_tags = get_ordered_post_tags($post->ID);
	if (!$ordered_tags) {
		return $terms;
	}

	return $ordered_tags;
}, 10, 3);

/**
 * Apply custom CSS class to tag links
 *
 * @since 1.0.3
 * @param array $links Array of tag link HTML
 * @return array Modified array of tag link HTML
 */
add_filter('term_links-post_tag', function($links) {
	// Only apply in frontend
	if (is_admin()) {
		return $links;
	}

	$post_id = get_the_ID();
	if (!$post_id) {
		return $links;
	}

	// Check if this post type supports tags
	$post_type = get_post_type($post_id);
	if (!in_array($post_type, get_post_types_with_tags())) {
		return $links;
	}

	// Get the ordered tags
	$tags = get_ordered_post_tags($post_id);
	if (!$tags) {
		return $links;
	}

	// Get settings
	$separator = get_option('tag_order_separator', '');
	$custom_class = get_option('tag_order_class', 'tag');

	// Check if we need to apply our custom format
	if (empty($separator) && $custom_class === 'tag') {
		// Just maintain the order without changing format
		$ordered_links = array();
		foreach ($tags as $tag) {
			$link = get_term_link($tag, 'post_tag');
			if (!is_wp_error($link)) {
				$ordered_links[] = '<a href="' . esc_url($link) . '" rel="tag">' . esc_html($tag->name) . '</a>';
			}
		}
		return $ordered_links;
	}

	// Get the existing links to preserve classes and other attributes
	$existing_links_by_tag = array();
	foreach ($links as $link) {
		// Extract tag name from link
		if (preg_match('/>([^<]+)<\/a>/', $link, $matches)) {
			$tag_name = trim($matches[1]);
			$existing_links_by_tag[$tag_name] = $link;
		}
	}

	// Apply custom format with our separator and class, preserving other attributes
	$custom_links = array();
	foreach ($tags as $tag) {
		$tag_name = $tag->name;

		// Check if we have an existing link for this tag
		if (isset($existing_links_by_tag[$tag_name])) {
			$existing_link = $existing_links_by_tag[$tag_name];

			// If we need to add our custom class
			if ($custom_class !== 'tag') {
				// If link already has a class attribute, add our class to it
				if (preg_match('/class=(["\'])([^"\']+)\\1/', $existing_link, $class_matches)) {
					$existing_classes = $class_matches[2];
					$updated_classes = $existing_classes;

					// Only add our class if it's not already there
					if (strpos($existing_classes, $custom_class) === false) {
						$updated_classes = $existing_classes . ' ' . $custom_class;
					}

					$existing_link = str_replace(
						'class=' . $class_matches[1] . $existing_classes . $class_matches[1],
						'class=' . $class_matches[1] . $updated_classes . $class_matches[1],
						$existing_link
					);
				} else {
					// No existing class, add ours
					$existing_link = str_replace('<a ', '<a class="' . esc_attr($custom_class) . '" ', $existing_link);
				}

				$custom_links[] = $existing_link;
			}
		} else {
			// Create a new link with our class
			$link = get_term_link($tag, 'post_tag');
			if (!is_wp_error($link)) {
				$custom_links[] = '<a href="' . esc_url($link) . '" class="' . esc_attr($custom_class) . '">' . esc_html($tag->name) . '</a>';
			}
		}
	}

	return $custom_links;
}, 20, 1);

/**
 * Filter Block Editor tag separator
 *
 * @since 1.0.5
 * @param string $separator The separator text
 * @return string Modified separator text
 */
function set_tag_order_filter_block_separator($separator) {
	$custom_separator = get_option('tag_order_separator', '');
	
	sto_debug_log("Filter wp_block_post_terms_separator called - Separator: '$separator', Custom: '$custom_separator'");
	
	if (!empty($custom_separator)) {
		return $custom_separator;
	}
	return $separator;
}
add_filter('wp_block_post_terms_separator', 'set_tag_order_filter_block_separator', 10, 1);

/**
 * Filter Block Editor post-terms block output
 *
 * @since 1.0.6
 * @param string $block_content The block content
 * @param array  $block         The full block, including name and attributes
 * @return string Modified block content
 */
function set_tag_order_filter_post_terms_block($block_content, $block) {
    // Our custom renderer is now handling the main tag output
    // This filter should not add additional classes as they're already added in the renderer
    
    // Only process post-terms blocks
    if (empty($block['blockName']) || $block['blockName'] !== 'core/post-terms') {
        return $block_content;
    }
    
    $custom_separator = get_option('tag_order_separator', '');
    $custom_class = get_option('tag_order_class', 'tag');
    
    // Log the taxonomy type from block attributes
    if (!empty($block['attrs']) && !empty($block['attrs']['term'])) {
        sto_debug_log("Filter render_block called for post-terms with taxonomy: '{$block['attrs']['term']}', Custom separator: '$custom_separator', Custom class: '$custom_class'");
    } else {
        sto_debug_log("Filter render_block called for post-terms - Custom separator: '$custom_separator', Custom class: '$custom_class'");
    }
    
    // Debugging: Log the complete block content
    sto_debug_log("Block content: " . $block_content);
    
    // We're no longer modifying classes here since they're added in the renderer
    return $block_content;
}
add_filter('render_block', 'set_tag_order_filter_post_terms_block', 10, 2);

/**
 * Custom renderer for post-terms block
 */
function sto_render_post_terms_block($attributes, $content, $block) {
    $post_id = isset($block->context['postId']) ? $block->context['postId'] : 0;
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    $term_type = isset($attributes['term']) ? $attributes['term'] : 'post_tag';
    sto_debug_log("Custom post-terms renderer called for post $post_id with term type $term_type");
    
    if ($term_type !== 'post_tag') {
        // If not rendering tags, let WordPress handle it
        return $content;
    }
    
    // Get our ordered tags using our existing function
    $tags = get_ordered_post_tags($post_id);
    
    if (!$tags || empty($tags)) {
        sto_debug_log("No tags found for post $post_id in custom renderer");
        return $content; // Return original content if no tags
    }
    
    $tag_count = count($tags);
    sto_debug_log("Custom renderer found $tag_count tags for post $post_id");
    
    // Get separator and class settings
    $separator = get_option('tag_order_separator', '');
    if (empty($separator) && isset($attributes['separator'])) {
        $separator = $attributes['separator'];
    }
    
    // Get custom classes - parse into array to prevent duplication
    $custom_class = get_option('tag_order_class', 'tag');
    $custom_classes = explode(' ', $custom_class);
    $custom_classes = array_map('trim', $custom_classes);
    $custom_classes = array_filter($custom_classes);
    
    // Start building output
    $classes = 'taxonomy-post_tag wp-block-post-terms';
    if (!empty($attributes['className'])) {
        $classes .= ' ' . $attributes['className'];
    }
    
    $html = '<div class="' . esc_attr($classes) . '">';
    
    foreach ($tags as $index => $tag) {
        if ($index > 0 && !empty($separator)) {
            $html .= '<span class="wp-block-post-terms__separator">' . esc_html($separator) . '</span>';
        }
        
        $tag_link = get_term_link($tag, 'post_tag');
        if (!is_wp_error($tag_link)) {
            $html .= '<a href="' . esc_url($tag_link) . '" class="' . esc_attr(implode(' ', $custom_classes)) . '" rel="tag">' . 
                     esc_html($tag->name) . '</a>';
        }
    }
    
    $html .= '</div>';
    sto_debug_log("Custom renderer generated HTML for tags");
    
    return $html;
}

/**
 * Add debug filter to track get_the_terms
 * 
 * @since 1.0.6
 */
function sto_debug_get_the_terms($terms, $post_id, $taxonomy) {
	if ($taxonomy === 'post_tag') {
		sto_debug_log("get_the_terms filter for post $post_id with taxonomy $taxonomy returned " . 
			(is_array($terms) ? count($terms) : 'non-array') . " terms");
		
		if (is_array($terms) && !empty($terms)) {
			$term_names = array_map(function($term) { 
				return $term->name; 
			}, $terms);
			sto_debug_log("Terms: " . implode(', ', $term_names));
		} else {
			sto_debug_log("No terms found or terms is not an array");
		}
	}
	return $terms;
}
add_filter('get_the_terms', 'sto_debug_get_the_terms', 999, 3);

/**
 * Add debug filter for post-terms block attributes
 */
function sto_debug_pre_render_block($pre_render, $parsed_block) {
	if (!empty($parsed_block['blockName']) && $parsed_block['blockName'] === 'core/post-terms') {
		sto_debug_log("Pre-render for post-terms block: " . json_encode($parsed_block['attrs']));
	}
	return $pre_render;
}
add_filter('pre_render_block', 'sto_debug_pre_render_block', 10, 2);

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
function set_tag_order_filter_the_tags($output, $before, $sep, $after) {
	$custom_separator = get_option('tag_order_separator', '');

	sto_debug_log("Filter the_tags called - Default separator: '$sep', Custom: '$custom_separator'");

	// Only modify if we have a custom separator
	if (!empty($custom_separator) && !empty($output)) {
		// Store original for debugging
		$original_output = $output;
		
		// Classic Editor handling
		$first_tag_pos = strpos($output, '</a>') + 4;
		$next_tag_pos = strpos($output, '<a', $first_tag_pos);

		if ($next_tag_pos !== false) {
			// Find the actual separator used
			$actual_separator = substr($output, $first_tag_pos, $next_tag_pos - $first_tag_pos);
			$actual_separator = trim($actual_separator);
			
			sto_debug_log("Actual separator found: '$actual_separator'");
			
			// Replace the actual separator with our custom one
			$output = str_replace(
				$actual_separator,
				'<span class="tag-separator">' . esc_html($custom_separator) . '</span>',
				$output
			);
			
			// Log whether the replacement was successful
			if ($original_output !== $output) {
				sto_debug_log("Custom separator applied to the_tags output");
			} else {
				sto_debug_log("Warning: Failed to replace separator in the_tags output");
			}
		}
	}

	return $output;
}
add_filter('the_tags', 'set_tag_order_filter_the_tags', 10, 4);

/**
 * Add custom CSS for tag separators
 *
 * @since 1.0.5
 * @return void
 */
function set_tag_order_custom_css() {
	$custom_separator = get_option('tag_order_separator', '');
	if (!empty($custom_separator)) {
		$custom_css = '
			.tag-separator {
				display: inline-block;
				margin: 0 0.25em;
				font-size: 1.2em;
				color: #999;
			}
		';
		wp_add_inline_style('wp-block-library', $custom_css);
	}
}
add_action('wp_enqueue_scripts', 'set_tag_order_custom_css');

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

	tag_order_debug_log("set_object_terms called for post $post_id with " . count($tt_ids) . " tags");

	// Get the current tag order
	$current_order = get_post_meta($post_id, '_tag_order', true);
	$current_order_array = $current_order ? explode(',', $current_order) : [];

	if (empty($current_order_array) && empty($tt_ids)) {
		// Nothing to do if both are empty
		return;
	}

	// Get term IDs from term_taxonomy IDs
	$term_ids = [];
	if (!empty($tt_ids)) {
		global $wpdb;
		$tt_ids_str = implode(',', array_map('intval', $tt_ids));
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->term_taxonomy WHERE term_taxonomy_id IN (%s) AND taxonomy = %s",
				$tt_ids_str,
				$taxonomy
			)
		);
	}

	// If we're replacing terms (not appending)
	if (!$append) {
		if (empty($term_ids)) {
			// All tags were removed, clear the order
			delete_post_meta($post_id, '_tag_order');
			tag_order_debug_log("All tags removed, cleared order for post $post_id");
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
		update_post_meta($post_id, '_tag_order', implode(',', $new_order));
		tag_order_debug_log("Updated tag order for post $post_id: " . implode(',', $new_order));
	} else {
		// Appending terms, add the new ones to the existing order
		$new_order = $current_order_array;

		foreach ($term_ids as $id) {
			if (!in_array($id, $new_order)) {
				$new_order[] = $id;
			}
		}

		update_post_meta($post_id, '_tag_order', implode(',', $new_order));
		tag_order_debug_log("Appended to tag order for post $post_id: " . implode(',', $new_order));
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
	if ($meta_key === '_tag_order') {
		tag_order_debug_log('Updated tag order for post ' . $post_id . ': ' . $meta_value);
	}
}, 10, 4);

// Include dependencies
require_once plugin_dir_path( __FILE__ ) . 'inc/admin/settings.php';

/**
 * Plugin debugging function
 *
 * Logs debug messages when debug mode is enabled
 *
 * @since 1.0.4
 * @param string $message The message to log
 * @return void
 */
function sto_debug_log( $message ) {
	if ( get_option( 'tag_order_debug_mode', false ) ) {
		error_log( '[Set Tag Order Debug] ' . $message );
	}
}

/**
 * Improved detection of Block Editor vs Classic Editor
 *
 * @since 1.0.4
 * @return bool True if using Block Editor, false if using Classic Editor
 */
function sto_is_using_block_editor() {
	// Check if we're in a REST API request - that's a strong indicator of Block Editor
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return true;
	}

	// Check if this is an AJAX request for the heartbeat API
	if (defined('DOING_AJAX') && DOING_AJAX) {
		// Heartbeat is used by both editors, so we need additional checks
		if (isset($_POST['action']) && $_POST['action'] === 'heartbeat') {
			// If we have a specific Block Editor field in the data, it's Block Editor
			if (isset($_POST['data']) && isset($_POST['data']['wp-refresh-post-lock'])) {
				return true;
			}
		}
		return false; // Other AJAX is likely from Classic Editor
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
	$post_types = get_post_types_with_tags();

	foreach ( $post_types as $post_type ) {
		register_post_meta( $post_type, '_tag_order', [
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
function get_post_types_with_tags() {
	$post_types      = get_post_types( [ 'public' => true ], 'objects' );
	$supported_types = [];

	foreach ( $post_types as $post_type ) {
		if ( is_object_in_taxonomy( $post_type->name, 'post_tag' ) ) {
			$supported_types[] = $post_type->name;
		}
	}

	return $supported_types;
}

/**
 * Order tags based on saved meta
 *
 * @since 1.0.0
 * @param array  $tags    Array of tag term objects
 * @param int    $post_id Post ID
 * @return array Ordered array of tag term objects
 */
function order_tags( $tags, $post_id ) {
	if ( ! $tags || ! $post_id ) {
		return $tags;
	}

	$tag_order = get_post_meta( $post_id, '_tag_order', true );
	tag_order_debug_log( 'Tag Order for post ' . $post_id . ': ' . $tag_order );

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

	tag_order_debug_log( 'Ordered ' . count( $ordered_tags ) . ' tags for post ' . $post_id );
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
function get_ordered_post_tags( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	// Check if this post type supports tags
	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, get_post_types_with_tags() ) ) {
		return false;
	}

	$tags = get_the_tags( $post_id );
	if ( ! $tags ) {
		return false;
	}

	return order_tags( $tags, $post_id );
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
function the_ordered_post_tags($before = '', $sep = '', $after = '', $post_id = 0) {
	if (!$post_id) {
		$post_id = get_the_ID();
	}

	$tags = get_ordered_post_tags($post_id);
	if (!$tags) {
		return;
	}

	// Get separator from settings or use provided parameter
	$separator = get_option('tag_order_separator');
	if ($separator === '' && $sep !== '') {
		$separator = $sep;
	}

	// Get class
	$class = get_option('tag_order_class', 'tag');

	$html = $before;

	foreach ($tags as $index => $tag) {
		if ($index > 0 && !empty($separator)) {
			$html .= '<span class="tag-separator">' . esc_html($separator) . '</span>';
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

/**
 * AJAX handler for setting editor mode
 *
 * @since 1.0.4
 * @return void
 */
function sto_ajax_set_editor_mode() {
	$user_id = get_current_user_id();
	
	// Verify nonce with proper sanitization
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'set_tag_order_editor_mode')) {
		wp_send_json_error('Invalid nonce');
	}

	if (isset($_POST['mode']) && $_POST['mode'] === 'classic') {
		// Store in user meta
		update_user_meta($user_id, 'set_tag_order_detected_editor', 'classic');
		sto_debug_log('JavaScript detection confirmed Classic Editor is active');
	} else {
		update_user_meta($user_id, 'set_tag_order_detected_editor', 'block');
		sto_debug_log('JavaScript detection confirmed Block Editor is active');
	}

	wp_die();
}
add_action('wp_ajax_set_tag_order_editor_mode', 'sto_ajax_set_editor_mode');

/**
 * Better JavaScript detection of editor mode
 *
 * @since 1.0.4
 * @return void
 */
function sto_improved_editor_detection_js() {
	// Only run on post edit screens
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->base, ['post', 'post-new'])) {
		return;
	}

	?>
    <script type="text/javascript">
        (function() {
            // Most reliable way to detect Block Editor - check for wp.blocks
            var isBlockEditor = typeof wp !== 'undefined' && wp.blocks && wp.blocks.registerBlockType;

            // If wp.blocks exists, we're definitely in Block Editor
            if (isBlockEditor) {
                console.log('[Set Tag Order] Detected Block Editor via JavaScript API check');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('action=set_tag_order_editor_mode&mode=block&_wpnonce=<?php echo esc_js(wp_create_nonce('set_tag_order_editor_mode')); ?>');

                // When using Block Editor, make sure our debug logging knows
                wp.domReady(function() {
                    console.log('[Set Tag Order] Block Editor fully loaded');
                });
            } else {
                // Check DOM for Classic Editor elements
                var isClassicEditor = document.getElementById('postdivrich') !== null ||
                    document.getElementById('wp-content-editor-container') !== null;

                if (isClassicEditor) {
                    console.log('[Set Tag Order] Detected Classic Editor via DOM check');

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('action=set_tag_order_editor_mode&mode=classic&_wpnonce=<?php echo esc_js(wp_create_nonce('set_tag_order_editor_mode')); ?>');
                }
            }
        })();
    </script>
	<?php
}
add_action('admin_footer', 'sto_improved_editor_detection_js');

/**
 * Render custom tag box for Classic Editor
 *
 * Displays a drag-and-drop interface for ordering tags in the Classic Editor
 *
 * @since 1.0.3
 * @param WP_Post $post Post object
 * @return void
 */
function render_custom_tag_box($post) {
	$all_tags = get_tags(['hide_empty' => false]);
	$post_tags = get_the_tags($post->ID) ?: [];
	$tag_order = get_post_meta($post->ID, '_tag_order', true);
	$ordered_ids = $tag_order ? explode(',', $tag_order) : [];

	wp_nonce_field('tag_order_meta_box', 'tag_order_meta_box_nonce');
	?>
    <div class="tagsdiv" id="custom-tags">
        <div class="jaxtag">
            <input type="text"
                   id="new-tag-input"
                   class="newtag form-input-tip"
                   size="16"
                   autocomplete="off"
                   value="" />
            <input type="button"
                   class="button tagadd"
                   value="Add" />
        </div>

        <div class="tagchecklist">
            <ul id="sortable-tags" class="tag-list">
				<?php foreach ($post_tags as $tag): ?>
                    <li data-tag-id="<?php echo esc_attr($tag->term_id); ?>"
                        data-tag-name="<?php echo esc_attr($tag->name); ?>">
						<?php echo esc_html($tag->name); ?>
                        <button type="button"
                                class="ntdelbutton"
                                data-tag-id="<?php echo esc_attr($tag->term_id); ?>">
                            <span class="remove-tag-icon" aria-hidden="true"></span>
                        </button>
                    </li>
				<?php endforeach; ?>
            </ul>
        </div>

        <input type="hidden"
               name="tag_order"
               id="tag-order-input"
               value="<?php echo esc_attr($tag_order); ?>" />
        <input type="hidden"
               name="post_tags"
               id="post-tags-input"
               value="<?php echo esc_attr(implode(',', wp_list_pluck($post_tags, 'term_id'))); ?>" />

        <div class="ajaxtag hide-if-no-js">
            <p><?php esc_html_e('Start typing to search existing tags, or add new ones.'); ?></p>
        </div>
    </div>

    <style>
        .jaxtag {
            margin-top: 15px;
        }
        .tagchecklist {
            margin-left: 0;
            margin-top: 15px;
        }
        .tag-list {
            margin: 0;
            padding: 0;
        }
        .tag-list li {
            padding: 8px 8px 8px 30px;
            margin: 4px 0;
            background: #f0f0f0;
            border: 1px solid #ddd;
            cursor: move;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tag-list li:hover {
            background: #e5e5e5;
        }
        .ntdelbutton {
            left: 36px;
            border: none;
            background: none;
            color: #a00;
            cursor: pointer;
            padding: 0 4px;
        }
        .ntdelbutton:hover {
            color: #dc3232;
        }
        .remove-tag-icon::before {
            content: "×";
        }
        #new-tag-input {
            width: 180px;
            max-width: 100%;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            var tagInput = $('#new-tag-input');
            var existingTags = <?php echo json_encode(array_map(function($tag) {
				return ['id' => $tag->term_id, 'text' => $tag->name];
			}, $all_tags)); ?>;

            // Initialize sortable
            $('#sortable-tags').sortable({
                update: function(event, ui) {
                    updateTagOrder();
                }
            });

            // Initialize tag autocomplete
            tagInput.autocomplete({
                source: existingTags.map(tag => tag.text),
                minLength: 2,
                select: function(event, ui) {
                    event.preventDefault();
                    var selectedTag = existingTags.find(tag => tag.text === ui.item.value);
                    if (selectedTag) {
                        addTag(selectedTag.id, selectedTag.text);
                    }
                    tagInput.val('');
                }
            });

            // Add tag button click
            $('.tagadd').click(function() {
                var tagName = tagInput.val().trim();
                if (!tagName) return;

                // Check if tag exists
                var existingTag = existingTags.find(tag =>
                    tag.text.toLowerCase() === tagName.toLowerCase()
                );

                if (existingTag) {
                    addTag(existingTag.id, existingTag.text);
                    tagInput.val('');
                } else {
                    // Create new tag
                    wp.ajax.post('add-tag', {
                        tag_name: tagName,
                        _wpnonce: '<?php echo wp_create_nonce('add_tag_nonce'); ?>'
                    }).done(function(response) {
                        addTag(response.term_id, response.name);
                        existingTags.push({
                            id: response.term_id,
                            text: response.name
                        });
                        tagInput.val('');
                    });
                }
            });

            // Remove tag button click
            $(document).on('click', '.ntdelbutton', function() {
                $(this).parent().remove();
                updateTagOrder();
            });

            function addTag(id, name) {
                // Check if tag already exists
                if ($(`#sortable-tags li[data-tag-id="${id}"]`).length) {
                    return;
                }

                $('#sortable-tags').append(`
                <li data-tag-id="${id}" data-tag-name="${name}">
                    ${name}
                    <button type="button" class="ntdelbutton" data-tag-id="${id}">
                        <span class="remove-tag-icon" aria-hidden="true"></span>
                    </button>
                </li>
            `);
                updateTagOrder();
            }

            function updateTagOrder() {
                var order = $('#sortable-tags').sortable('toArray', {
                    attribute: 'data-tag-id'
                });
                $('#tag-order-input').val(order.join(','));
                $('#post-tags-input').val(order.join(','));
            }
        });
    </script>
	<?php
}

/**
 * Add custom meta box only for Classic Editor
 *
 * @since 1.0.4
 * @return void
 */
function set_tag_order_add_meta_box() {
	global $post;
	if (!$post) {
		sto_debug_log('No $post object available - skipping meta box replacement');
		return;
	}

	// Only proceed if we're in Classic Editor
	if (sto_is_using_block_editor()) {
		sto_debug_log('Detected as Block Editor - skipping meta box replacement');
		return;
	}

	$post_types = get_post_types_with_tags();
	sto_debug_log('Found post types with tags: ' . implode(', ', $post_types));

	foreach ($post_types as $post_type) {
		if ($post->post_type !== $post_type) {
			continue;
		}

		// First remove the default tags meta box
		remove_meta_box('tagsdiv-post_tag', $post_type, 'side');
		sto_debug_log("Removed default tagsdiv-post_tag for $post_type");

		// Then add the custom one
		add_meta_box(
			'tag-order-meta-box',
			'Tags', // Use standard name for familiarity
			'render_custom_tag_box',
			$post_type,
			'side',
			'high' // Use high priority to ensure it appears in a good position
		);
		sto_debug_log("Added custom tag-order-meta-box for $post_type");
	}
}
add_action('add_meta_boxes', 'set_tag_order_add_meta_box', 100);

/**
 * Sync tag operations between Block Editor and custom tag order
 *
 * @since 1.0.4
 * @return void
 */
function add_tag_order_rest_filter() {
	// Only hook into post types that support tags
	$post_types = get_post_types_with_tags();
	foreach ($post_types as $post_type) {
		add_filter("rest_pre_insert_{$post_type}", 'sto_sync_tag_order_on_rest_update', 10, 2);
	}
}
add_action('rest_api_init', 'add_tag_order_rest_filter');

/**
 * Synchronize tag order when post is updated via REST API (Block Editor)
 *
 * @since 1.0.4
 * @param stdClass        $prepared_post The prepared post data for updating
 * @param WP_REST_Request $request       The current request object
 * @return stdClass The modified post data
 */
function sto_sync_tag_order_on_rest_update($prepared_post, $request) {
	// Check if we have post ID and tags in the request
	if (!isset($prepared_post->ID) || !isset($prepared_post->tags)) {
		return $prepared_post;
	}

	$post_id = $prepared_post->ID;
	$new_tags = $prepared_post->tags;
	sto_debug_log("REST API update for post {$post_id} with tags: " . implode(',', $new_tags));

	// Get current tag order
	$current_order = get_post_meta($post_id, '_tag_order', true);
	$ordered_ids = !empty($current_order) ? explode(',', $current_order) : [];

	// Nothing to do if no new tags and no existing order
	if (empty($new_tags) && empty($ordered_ids)) {
		return $prepared_post;
	}

	// If removing all tags, clear the order
	if (empty($new_tags)) {
		delete_post_meta($post_id, '_tag_order');
		sto_debug_log("REST API: Cleared tag order for post {$post_id} (all tags removed)");
		return $prepared_post;
	}

	// Create new order preserving the sequence of existing order
	$new_order = [];

	// First add tags that are in the existing order
	if (!empty($ordered_ids)) {
		foreach ($ordered_ids as $id) {
			if (in_array($id, $new_tags)) {
				$new_order[] = $id;
			}
		}
	}

	// Then add any new tags that weren't in the existing order
	foreach ($new_tags as $id) {
		if (!in_array($id, $new_order)) {
			$new_order[] = $id;
		}
	}

	// Update the tag order meta
	update_post_meta($post_id, '_tag_order', implode(',', $new_order));
	sto_debug_log("REST API: Updated tag order for post {$post_id}: " . implode(',', $new_order));

	return $prepared_post;
}

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
	if (!isset($_POST['tag_order_meta_box_nonce']) ||
	    !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tag_order_meta_box_nonce'])), 'tag_order_meta_box')) {
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

		sto_debug_log('Saving post_tags: ' . implode(',', $valid_tags));
	}

	if (isset($_POST['tag_order'])) {
		$tag_order = sanitize_text_field(wp_unslash($_POST['tag_order']));
		update_post_meta(
			$post_id,
			'_tag_order',
			$tag_order
		);
		sto_debug_log('Saving tag_order: ' . $tag_order);
	}
}, 10, 1);

/**
 * AJAX handler for creating new tags
 *
 * @since 1.0.3
 * @return void
 */
add_action('wp_ajax_add-tag', function() {
	// Verify nonce with proper sanitization
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'add_tag_nonce')) {
		wp_send_json_error('Invalid nonce');
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
function sto_register_assets() {
	// Register the main script
	wp_register_script(
		'tag-order-script',
		plugins_url('/assets/js/set-tag-order.js', __FILE__),
		['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'],
		filemtime(plugin_dir_path(__FILE__) . 'assets/js/set-tag-order.js'),
		[
			'in_footer' => true,
			'strategy' => 'defer'
		]
	);

	// Register the main styles
	wp_register_style(
		'tag-order-panel-styles',
		plugins_url('/assets/css/tag-order-panels.css', __FILE__),
		[],
		filemtime(plugin_dir_path(__FILE__) . 'assets/css/tag-order-panels.css')
	);
}
add_action('init', 'sto_register_assets');

/**
 * Enqueue Block Editor assets
 *
 * @since 1.0.5
 */
add_action('enqueue_block_editor_assets', function() {
	global $post_type;
	
	if (!$post_type || !in_array($post_type, get_post_types_with_tags())) {
		return;
	}

	// Only load Block Editor assets when Block Editor is detected
	if (sto_is_using_block_editor()) {
		sto_debug_log("Loading Block Editor assets for post type: {$post_type}");
		
		wp_enqueue_script('tag-order-script');
		wp_enqueue_style('tag-order-panel-styles');
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
	if (!in_array($hook, ['post.php', 'post-new.php'])) {
		return;
	}

	// Only load Classic Editor assets when Classic Editor is detected
	if (!sto_is_using_block_editor()) {
		sto_debug_log('Loading jQuery UI for Classic Editor');
		
		// Register and enqueue jQuery UI scripts with proper dependencies
		wp_enqueue_script('jquery-ui-sortable', ['jquery-ui-core', 'jquery-ui-mouse']);
		wp_enqueue_script('jquery-ui-autocomplete', ['jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position']);
	}
}, 20);

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
function synchronize_tag_order_on_load($post_id) {
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
		delete_post_meta($post_id, '_tag_order');
		return;
	}

	// Get saved tag order
	$tag_order = get_post_meta($post_id, '_tag_order', true);
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

		update_post_meta($post_id, '_tag_order', implode(',', $new_order));
		sto_debug_log("Synchronized tag order on load for post $post_id: " . implode(',', $new_order));
	}
}