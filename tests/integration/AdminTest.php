<?php
/**
 * Admin surface: the ajax endpoints and the Classic Editor save handler.
 *
 * Extends WP_Ajax_UnitTestCase for the ajax helpers, which also means
 * is_admin() is true throughout - correct for everything asserted here.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class AdminTest extends WP_Ajax_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();
	}

	public function tear_down() {
		unset( $GLOBALS['post'] );
		wp_reset_postdata();
		parent::tear_down();
	}

	private function dispatch( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		} catch ( WPAjaxDieStopException $exception ) {
			unset( $exception );
		}

		return json_decode( $this->_last_response, true );
	}

	// --- Tag creation endpoint -------------------------------------------

	public function test_add_tag_creates_a_term_for_an_authorized_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_add_tag_nonce' );
		$_POST['tag_name'] = 'Fresh Tag';

		$response = $this->dispatch( 'settagord_add_tag' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Fresh Tag', $response['data']['name'] );
		$this->assertArrayHasKey( 'term_id', $response['data'] );
		$this->assertArrayHasKey( 'slug', $response['data'] );
		$this->assertNotNull( term_exists( 'Fresh Tag', 'post_tag' ) );
	}

	public function test_add_tag_requires_the_term_creation_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_add_tag_nonce' );
		$_POST['tag_name'] = 'Nope Tag';

		$response = $this->dispatch( 'settagord_add_tag' );

		$this->assertFalse( $response['success'] );
		$this->assertNull( term_exists( 'Nope Tag', 'post_tag' ) );
	}

	public function test_add_tag_rejects_a_bad_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_wpnonce'] = 'not-a-real-nonce';
		$_POST['tag_name'] = 'Bad Nonce Tag';

		$response = $this->dispatch( 'settagord_add_tag' );

		$this->assertFalse( $response['success'] );
		$this->assertNull( term_exists( 'Bad Nonce Tag', 'post_tag' ) );
	}

	public function test_add_tag_requires_a_name() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_add_tag_nonce' );
		$_POST['tag_name'] = '';

		$response = $this->dispatch( 'settagord_add_tag' );

		$this->assertFalse( $response['success'] );
	}

	// --- Editor mode endpoint --------------------------------------------

	public function test_editor_mode_endpoint_records_the_detected_editor() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_editor_mode' );
		$_POST['mode']     = 'classic';
		$this->dispatch( 'settagord_editor_mode' );

		$this->assertSame( 'classic', get_user_meta( $user_id, 'settagord_detected_editor', true ) );
	}

	public function test_editor_mode_endpoint_rejects_a_bad_nonce() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['_wpnonce'] = 'nope';
		$_POST['mode']     = 'classic';
		$this->dispatch( 'settagord_editor_mode' );

		$this->assertSame( '', get_user_meta( $user_id, 'settagord_detected_editor', true ) );
	}

	// --- Classic Editor save handler -------------------------------------

	public function test_save_handler_persists_tags_and_order() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		list( , $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['settagord_meta_box_nonce'] = wp_create_nonce( 'settagord_meta_box' );
		$_POST['post_tags']                = implode( ',', array( $ids[1], $ids[0] ) );
		$_POST['settagord']                = implode( ',', array( $ids[1], $ids[0] ) );

		do_action( 'save_post', $post_id, get_post( $post_id ), true );

		$assigned = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		sort( $assigned );
		$expected = array( $ids[0], $ids[1] );
		sort( $expected );

		$this->assertSame( array( $ids[1], $ids[0] ), $this->get_tag_order( $post_id ) );
		$this->assertSame( $expected, $assigned );
	}

	public function test_save_handler_does_nothing_without_a_valid_nonce() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['post_tags'] = (string) $ids[1];
		$_POST['settagord'] = (string) $ids[1];

		do_action( 'save_post', $post_id, get_post( $post_id ), true );

		$this->assertSame( $ids, $this->get_tag_order( $post_id ) );
	}

	public function test_save_handler_discards_ids_that_are_not_real_tags() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		list( , $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['settagord_meta_box_nonce'] = wp_create_nonce( 'settagord_meta_box' );
		$_POST['post_tags']                = $ids[0] . ',999999,not-a-number';
		$_POST['settagord']                = (string) $ids[0];

		do_action( 'save_post', $post_id, get_post( $post_id ), true );

		$this->assertSame(
			array( $ids[0] ),
			wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) )
		);
	}

	public function test_save_handler_requires_the_edit_capability() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST['settagord_meta_box_nonce'] = wp_create_nonce( 'settagord_meta_box' );
		$_POST['settagord']                = implode( ',', array_reverse( $ids ) );

		do_action( 'save_post', $post_id, get_post( $post_id ), true );

		$this->assertSame( $ids, $this->get_tag_order( $post_id ) );
	}

	// --- Meta box ---------------------------------------------------------

	public function test_tag_box_renders_tags_in_order_with_keyboard_controls() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		ob_start();
		settagord_render_custom_tag_box( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );

		// Reordering was drag-only before 1.2.0, which left no keyboard route.
		$this->assertSame( 2, substr_count( $html, 'data-direction="up"' ) );
		$this->assertSame( 2, substr_count( $html, 'data-direction="down"' ) );
		$this->assertStringContainsString( 'settagord-sort-alpha', $html );
		$this->assertStringContainsString( 'settagord_meta_box_nonce', $html );
		$this->assertStringContainsString( 'value="' . $ids[1] . ',' . $ids[0] . '"', $html );
	}
}
