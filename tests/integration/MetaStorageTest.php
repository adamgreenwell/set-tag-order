<?php
/**
 * Tag order metadata: accessors, registration, and legacy migration.
 *
 * @package SetTagOrder\Tests
 */
class MetaStorageTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();

		// WP_UnitTestCase::tear_down() unregisters every meta key, so the
		// plugin's own init-time registration is gone after the first test in
		// the class. Re-run it rather than asserting against a stale global.
		settagord_register_post_meta();
	}

	public function test_accessors_round_trip_the_order() {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', settagord_get_tag_order_meta( $post_id ) );

		settagord_update_tag_order_meta( $post_id, '3,1,2' );
		$this->assertSame( '3,1,2', settagord_get_tag_order_meta( $post_id ) );

		settagord_delete_tag_order_meta( $post_id );
		$this->assertSame( '', settagord_get_tag_order_meta( $post_id ) );
	}

	public function test_legacy_meta_is_read_and_migrated_on_first_access() {
		$post_id = self::factory()->post->create();
		delete_post_meta( $post_id, '_settagord' );
		update_post_meta( $post_id, '_tag_order', '5,9' );

		$this->assertSame( '5,9', settagord_get_tag_order_meta( $post_id ) );

		// Migration is a side effect of the read: the value moves across and
		// the old key is dropped, so it only happens once per post.
		$this->assertSame( '5,9', get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_tag_order', true ) );
	}

	public function test_current_meta_wins_over_legacy_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_settagord', '1,2' );
		update_post_meta( $post_id, '_tag_order', '9,9' );

		$this->assertSame( '1,2', settagord_get_tag_order_meta( $post_id ) );
	}

	public function test_updating_clears_the_legacy_key() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_tag_order', '9,9' );

		settagord_update_tag_order_meta( $post_id, '1,2' );

		$this->assertSame( '', get_post_meta( $post_id, '_tag_order', true ) );
	}

	public function test_deleting_removes_both_keys() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_settagord', '1' );
		update_post_meta( $post_id, '_tag_order', '2' );

		settagord_delete_tag_order_meta( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_tag_order', true ) );
	}

	public function test_meta_is_registered_for_rest_on_tag_supporting_post_types() {
		$registered = get_registered_meta_keys( 'post', 'post' );

		$this->assertArrayHasKey( '_settagord', $registered );
		$this->assertTrue( $registered['_settagord']['show_in_rest'] );
		$this->assertTrue( $registered['_settagord']['single'] );
		$this->assertSame( 'string', $registered['_settagord']['type'] );
	}

	public function test_meta_write_access_requires_the_edit_posts_capability() {
		$registered = get_registered_meta_keys( 'post', 'post' );
		$auth       = $registered['_settagord']['auth_callback'];

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( (bool) call_user_func( $auth ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( (bool) call_user_func( $auth ) );
	}
}
