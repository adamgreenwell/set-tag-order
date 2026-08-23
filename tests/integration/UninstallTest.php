<?php
/**
 * The uninstall routine.
 *
 * uninstall.php is loaded with SETTAGORD_LOAD_UNINSTALL_FOR_TESTS so it defines
 * its function without running the cleanup on include.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class UninstallTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();
		$this->require_uninstall_routine();
	}

	public function test_the_routine_is_defined_without_running_on_include() {
		$this->assertTrue( function_exists( 'settagord_uninstall_site' ) );
	}

	public function test_uninstall_removes_options_and_tag_order_metadata() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );

		$legacy_post_id = self::factory()->post->create();
		update_post_meta( $legacy_post_id, '_tag_order', '1,2' );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'settagord_detected_editor', 'classic' );

		update_option( 'settagord_separator', '/' );
		update_option( 'settagord_class', 'badge' );
		update_option( 'settagord_debug_mode', true );

		settagord_uninstall_site();

		$this->assertFalse( get_option( 'settagord_separator' ) );
		$this->assertFalse( get_option( 'settagord_class' ) );
		$this->assertFalse( get_option( 'settagord_debug_mode' ) );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( '', get_post_meta( $legacy_post_id, '_tag_order', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'settagord_detected_editor', true ) );
	}

	/**
	 * The tags themselves are WordPress data, not the plugin's, so uninstalling
	 * must not touch them.
	 */
	public function test_uninstall_leaves_the_tags_and_posts_alone() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );

		settagord_uninstall_site();

		$this->assertNotNull( get_post( $post_id ) );
		$this->assertSame(
			2,
			count( wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) ) )
		);
	}

	public function test_uninstall_is_safe_to_run_on_a_clean_install() {
		settagord_uninstall_site();
		settagord_uninstall_site();

		$this->assertFalse( get_option( 'settagord_separator' ) );
	}
}
