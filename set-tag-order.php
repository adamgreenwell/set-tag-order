<?php
/*
* Plugin Name: Set Tag Order
* Description: Allows setting custom order for post tags in the block editor
* Version: 1.0.2
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

	// Debug log
	error_log( 'Tag Order for post ' . $post_id . ': ' . $tag_order );

	if ( ! $tag_order ) {
		return $tags;
	}

	$order        = explode( ',', $tag_order );
	$ordered_tags = [];

	// Reorder tags based on saved order
	foreach ( $order as $tag_id ) {
		foreach ( $tags as $tag ) {
			if ( $tag->term_id == $tag_id ) {
				$ordered_tags[] = $tag;
				break;
			}
		}
	}

	// Add any remaining tags
	foreach ( $tags as $tag ) {
		if ( ! in_array( $tag, $ordered_tags ) ) {
			$ordered_tags[] = $tag;
		}
	}

	return $ordered_tags;
}

// Add debug action to verify meta is being saved
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key === '_tag_order' ) {
		error_log( 'Updated tag order for post ' . $post_id . ': ' . $meta_value );
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
function the_ordered_post_tags() {
	$tags = get_ordered_post_tags();
	if ( ! $tags ) {
		return;
	}

	$separator = get_option( 'tag_order_separator', '' );
	$class     = get_option( 'tag_order_class', 'tag' );

	$html = '<div class="post-tags">';

	foreach ( $tags as $index => $tag ) {
		if ( $index > 0 && ! empty( $separator ) ) {
			$html .= '<span class="tag-separator">' . esc_html( $separator ) . '</span>';
		}

		$html .= sprintf(
			'<a href="%s" class="%s">%s</a>',
			get_tag_link( $tag->term_id ),
			esc_attr( $class ),
			esc_html( $tag->name )
		);
	}

	$html .= '</div>';

	echo $html;
}

// Enqueue block editor JavaScript
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'tag-order-script',
		plugins_url( '/assets/js/set-tag-order.js', __FILE__ ),
		[ 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data' ],
		'1.0.2',
		true
	);
} );