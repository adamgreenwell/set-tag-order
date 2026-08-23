<?php
/**
 * Uninstall Set Tag Order.
 *
 * Removes the plugin's options and the per-post tag order metadata. Runs only
 * when the plugin is deleted from the Plugins screen, not on deactivation, so
 * deactivating and reactivating never loses a saved order.
 *
 * @package SetTagOrder
 * @since   1.2.0
 */

// Exit if this was not called by WordPress deleting the plugin. The second
// constant lets the test suite load this file to exercise the cleanup routine
// without the file running it on include; nothing else sets it.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) && ! defined( 'SETTAGORD_LOAD_UNINSTALL_FOR_TESTS' ) ) {
	exit;
}

/**
 * Remove every option and meta key the plugin creates for one site.
 *
 * @since 1.2.0
 * @return void
 */
if ( ! function_exists( 'settagord_uninstall_site' ) ) :
function settagord_uninstall_site() {
	delete_option( 'settagord_separator' );
	delete_option( 'settagord_class' );
	delete_option( 'settagord_debug_mode' );

	// '_settagord' is the current key; '_tag_order' is the pre-1.1 key that
	// migrates lazily, so installs upgraded from an old version can still have
	// rows under it on posts that were never opened.
	delete_post_meta_by_key( '_settagord' );
	delete_post_meta_by_key( '_tag_order' );

	// Set by the editor detection script, per user.
	delete_metadata( 'user', 0, 'settagord_detected_editor', '', true );
}
endif;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// Loaded by the test suite; the caller invokes settagord_uninstall_site().
	return;
}

if ( is_multisite() ) {
	$settagord_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $settagord_site_ids as $settagord_site_id ) {
		switch_to_blog( $settagord_site_id );
		settagord_uninstall_site();
		restore_current_blog();
	}

	unset( $settagord_site_ids, $settagord_site_id );
} else {
	settagord_uninstall_site();
}
