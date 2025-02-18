<?php
/*
* Plugin Name: Set Tag Order
* Description: Allows setting custom order for post tags in the block editor
* Version: 1.0.3
* Author: Adam Greenwell
*
* File Name: set-tag-order.php
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'inc/admin/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'update/github-updater.php';

$updater = new Set_Tag_Order_GitHub_Updater( __FILE__ );
$updater->set_github_info( 'adamgreenwell', 'set-tag-order' );

// Register meta field for tag order for all post types that support tags
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

// Helper function to get post types that support tags
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

// Helper function to order tags
// Add debug logging to verify order_tags function is working
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

// Enqueue jQuery UI for Classic Editor
add_action('admin_enqueue_scripts', function($hook) {
	if (!in_array($hook, ['post.php', 'post-new.php'])) {
		return;
	}

	// Check if Block Editor is active
	if (use_block_editor_for_post_type(get_post_type())) {
		return;
	}

	wp_enqueue_script('jquery-ui-sortable');
});

// Add meta box for Classic Editor
add_action('add_meta_boxes', function() {
	$post_types = get_post_types_with_tags();

	foreach ($post_types as $post_type) {
		add_meta_box(
			'tag-order-meta-box',
			'Tags',
			'render_custom_tag_box',
			$post_type,
			'side',
			'core'
		);
	}
});

// Remove default tags meta box from Classic Editor
add_action('admin_menu', function() {
	$post_types = get_post_types_with_tags();
	foreach ($post_types as $post_type) {
		remove_meta_box('tagsdiv-post_tag', $post_type, 'side');
	}
});

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
					<li data-tag-id="<?php echo esc_attr($tag->term_id); ?>">
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
                var tagName = tagInput.val();
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
                        tag_name: tagName
                    }).done(function(response) {
                        addTag(response.term_id, tagName);
                        existingTags.push({
                            id: response.term_id,
                            text: tagName
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
                <li data-tag-id="${id}">
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

// Save meta box data
add_action('save_post', function($post_id) {
	if (!isset($_POST['tag_order_meta_box_nonce']) ||
	    !wp_verify_nonce($_POST['tag_order_meta_box_nonce'], 'tag_order_meta_box')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	if (isset($_POST['post_tags'])) {
		$tag_ids = array_filter(
			explode(',', sanitize_text_field($_POST['post_tags']))
		);
		wp_set_post_tags($post_id, $tag_ids, false);
	}

	if (isset($_POST['tag_order'])) {
		update_post_meta(
			$post_id,
			'_tag_order',
			sanitize_text_field($_POST['tag_order'])
		);
	}
});

// Add AJAX handler for creating new tags
add_action('wp_ajax_add-tag', function() {
	$tag_name = sanitize_text_field($_POST['tag_name']);
	$tag = wp_insert_term($tag_name, 'post_tag');

	if (is_wp_error($tag)) {
		wp_send_json_error($tag->get_error_message());
	} else {
		wp_send_json_success([
			'term_id' => $tag['term_id'],
			'name' => $tag_name
		]);
	}
});

// Add debug action to verify meta is being saved
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key === '_tag_order' ) {
		tag_order_debug_log( 'Updated tag order for post ' . $post_id . ': ' . $meta_value );
	}
}, 10, 4 );

// Filter to modify tag output - this affects get_the_tags() and the_tags()
add_filter( 'get_the_terms', function ( $terms, $post_id, $taxonomy ) {
	// Get post type
	$post_type = get_post_type( $post_id );

	// Check if this post type supports tags and if we're dealing with tags
	if ( $taxonomy !== 'post_tag' || ! $terms || is_wp_error( $terms ) ||
	     ! in_array( $post_type, get_post_types_with_tags() ) ) {
		return $terms;
	}

	return order_tags( $terms, $post_id );
}, 10, 3 );

// Filter tag cloud widget to use our order
add_filter('get_terms', function ($terms, $taxonomies, $args) {
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

// Force the use of our class in tag links
add_filter('term_links-post_tag', function ($links) {
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
			}

			$custom_links[] = $existing_link;
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

// Add a filter for the_tags output
add_filter('the_tags', function ($output, $before, $sep, $after) {
	$custom_separator = get_option('tag_order_separator', '');

	// Only modify if we have a custom separator
	if (!empty($custom_separator) && !empty($output)) {
		// Replace default separator with our custom one
		// First find what separator was actually used (it might not be $sep)
		$first_tag_pos = strpos($output, '</a>') + 4;
		$next_tag_pos = strpos($output, '<a', $first_tag_pos);

		if ($first_tag_pos !== false && $next_tag_pos !== false) {
			$actual_sep = substr($output, $first_tag_pos, $next_tag_pos - $first_tag_pos);
			// Replace all instances of this separator with our custom one
			$output = str_replace($actual_sep, '<span class="tag-separator">' . esc_html($custom_separator) . '</span>', $output);
		}
	}

	return $output;
}, 20, 4);

// Helper function for template use
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

// Update the existing the_ordered_post_tags() function
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

	echo $html;
}

// Enqueue block editor JavaScript if block editor is enabled
add_action('admin_enqueue_scripts', function($hook) {
	if (!in_array($hook, ['post.php', 'post-new.php'])) {
		return;
	}

	$post_type = get_post_type();
	if (!$post_type || !in_array($post_type, get_post_types_with_tags())) {
		return;
	}

	// Load Block Editor assets
	if (use_block_editor_for_post_type($post_type)) {
		wp_enqueue_script(
			'tag-order-script',
			plugins_url('/assets/js/set-tag-order.js', __FILE__),
			['wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data'],
			'1.0.3',
			true
		);
	}
});