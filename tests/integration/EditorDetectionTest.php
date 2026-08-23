<?php
/**
 * Editor detection and the Classic Editor meta box swap.
 *
 * @package SetTagOrder\Tests
 */
class EditorDetectionTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();

		// The detection function short-circuits on REST_REQUEST and DOING_AJAX
		// before it ever looks at the screen. Neither can be undefined once
		// set, so skip rather than assert something misleading.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$this->markTestSkipped( 'REST_REQUEST is defined for this process.' );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$this->markTestSkipped( 'DOING_AJAX is defined for this process.' );
		}
	}

	public function tear_down() {
		unset( $GLOBALS['post'], $GLOBALS['wp_meta_boxes'] );
		parent::tear_down();
	}

	private function given_screen( $screen_id, $is_block_editor ) {
		set_current_screen( $screen_id );
		get_current_screen()->is_block_editor( $is_block_editor );
	}

	public function test_detects_the_block_editor_from_the_current_screen() {
		$this->given_screen( 'post', true );

		$this->assertTrue( settagord_is_using_block_editor() );
	}

	public function test_detects_the_classic_editor_from_the_current_screen() {
		$this->given_screen( 'post', false );

		$this->assertFalse( settagord_is_using_block_editor() );
	}

	public function test_meta_box_replaces_the_core_tags_box_in_the_classic_editor() {
		global $post, $wp_meta_boxes;

		$this->given_screen( 'post', false );
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$post            = get_post( $post_id );

		add_meta_box( 'tagsdiv-post_tag', 'Tags', '__return_null', 'post', 'side' );
		settagord_add_meta_box();

		$side = $wp_meta_boxes['post']['side'];
		$ids  = array();

		foreach ( $side as $boxes ) {
			$ids = array_merge( $ids, array_keys( array_filter( $boxes ) ) );
		}

		$this->assertContains( 'settagord_meta_box', $ids );
		$this->assertNotContains( 'tagsdiv-post_tag', $ids );
	}

	public function test_meta_box_is_not_added_in_the_block_editor() {
		global $post, $wp_meta_boxes;

		$this->given_screen( 'post', true );
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$post            = get_post( $post_id );

		settagord_add_meta_box();

		$ids = array();

		foreach ( (array) ( $wp_meta_boxes['post']['side'] ?? array() ) as $boxes ) {
			$ids = array_merge( $ids, array_keys( array_filter( (array) $boxes ) ) );
		}

		$this->assertNotContains( 'settagord_meta_box', $ids );
	}

	public function test_meta_box_does_nothing_without_a_post() {
		global $post;

		$this->given_screen( 'post', false );
		$post = null;

		settagord_add_meta_box();

		$this->assertTrue( true, 'Returned without a fatal when no post is set.' );
	}
}
