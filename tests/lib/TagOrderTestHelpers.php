<?php
/**
 * Shared helpers for Set Tag Order integration tests.
 *
 * A trait rather than a base class because the suite needs two different
 * WordPress test cases: WP_Ajax_UnitTestCase for the admin endpoints, and
 * WP_UnitTestCase for everything else.
 *
 * @package SetTagOrder\Tests
 */

trait TagOrderTestHelpers {

	/**
	 * Reset plugin state that outlives a single test.
	 *
	 * Call from set_up(). Options are real rows and the post type list is
	 * memoized for the request, so neither resets on its own between tests.
	 */
	protected function reset_plugin_state() {
		delete_option( 'settagord_separator' );
		delete_option( 'settagord_class' );
		delete_option( 'settagord_debug_mode' );

		settagord_get_post_types_with_tags( true );

		unset(
			$_POST['settagord_meta_box_nonce'],
			$_POST['post_tags'],
			$_POST['settagord'],
			$_POST['_wpnonce'],
			$_POST['tag_name'],
			$_POST['mode']
		);
	}

	/**
	 * Create a published post with the given tags, in the given order.
	 *
	 * @param array $tag_names Tag names to create and assign.
	 * @return array{0:int,1:array} Post ID and the created term IDs.
	 */
	protected function create_post_with_tags( array $tag_names ) {
		$post_id  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term_ids = array();

		foreach ( $tag_names as $tag_name ) {
			$term       = wp_insert_term( $tag_name, 'post_tag' );
			$term_ids[] = (int) $term['term_id'];
		}

		if ( $term_ids ) {
			wp_set_post_terms( $post_id, $term_ids, 'post_tag', false );
		}

		return array( $post_id, $term_ids );
	}

	/**
	 * Extract term names, for readable order assertions.
	 *
	 * @param array $tags Term objects.
	 * @return array Names in order.
	 */
	protected function ordered_tag_names( $tags ) {
		return $tags ? wp_list_pluck( $tags, 'name' ) : array();
	}

	/**
	 * Save a tag order directly, bypassing the editors.
	 *
	 * @param int   $post_id Post to write to.
	 * @param array $ids     Term IDs in the wanted order.
	 */
	protected function set_tag_order( $post_id, array $ids ) {
		update_post_meta( $post_id, '_settagord', implode( ',', $ids ) );
	}

	/**
	 * Read the stored order back as an array of ints.
	 *
	 * @param int $post_id Post to read.
	 * @return array Term IDs in stored order.
	 */
	protected function get_tag_order( $post_id ) {
		$order = get_post_meta( $post_id, '_settagord', true );

		return '' === $order ? array() : array_map( 'intval', explode( ',', $order ) );
	}

	/**
	 * Put WordPress into front-end context.
	 *
	 * WP_Ajax_UnitTestCase::set_up() calls set_current_screen( 'ajax' ), which
	 * makes is_admin() true for the whole test. Filters that only run on the
	 * front end are skipped under that default, so any test asserting rendered
	 * output has to switch context explicitly.
	 */
	protected function given_front_end_request() {
		set_current_screen( 'front' );
		$this->assertFalse( is_admin(), 'Expected front-end context for this assertion.' );
	}

	/**
	 * Put a post into the global loop state, as a front-end request would.
	 *
	 * @param int $post_id Post to set up.
	 */
	protected function given_current_post( $post_id ) {
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Render a real core/post-terms block for a post.
	 *
	 * @param int   $post_id Post to render for.
	 * @param array $attrs   Block attributes to merge over the defaults.
	 * @return string Rendered block HTML.
	 */
	protected function render_post_terms_block( $post_id, array $attrs = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'core/post-terms',
				'attrs'     => array_merge( array( 'term' => 'post_tag' ), $attrs ),
				'innerHTML' => '',
			),
			array(
				'postId'   => $post_id,
				'postType' => 'post',
			)
		);

		return $block->render();
	}

	/**
	 * Load the uninstall routine without letting it run on include.
	 */
	protected function require_uninstall_routine() {
		if ( ! defined( 'SETTAGORD_LOAD_UNINSTALL_FOR_TESTS' ) ) {
			define( 'SETTAGORD_LOAD_UNINSTALL_FOR_TESTS', true );
		}

		require_once dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
