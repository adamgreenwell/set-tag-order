<?php
/**
 * Keeping the stored order in step with the post's actual tags.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class SyncTest extends WP_UnitTestCase {

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

	// --- set_object_terms -------------------------------------------------

	public function test_adding_tags_appends_them_and_keeps_existing_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, array( $ids[0] ) );

		$beta  = (int) wp_insert_term( 'Beta', 'post_tag' )['term_id'];
		$gamma = (int) wp_insert_term( 'Gamma', 'post_tag' )['term_id'];

		wp_set_post_terms( $post_id, array( $ids[0], $beta, $gamma ), 'post_tag', false );

		$this->assertSame( array( $ids[0], $beta, $gamma ), $this->get_tag_order( $post_id ) );
	}

	public function test_removing_a_tag_leaves_the_rest_in_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$this->set_tag_order( $post_id, array( $ids[2], $ids[1], $ids[0] ) );

		wp_set_post_terms( $post_id, array( $ids[2], $ids[0] ), 'post_tag', false );

		$this->assertSame( array( $ids[2], $ids[0] ), $this->get_tag_order( $post_id ) );
	}

	public function test_removing_all_tags_clears_the_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );

		wp_set_post_terms( $post_id, array(), 'post_tag', false );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
	}

	public function test_removing_all_tags_clears_legacy_order_too() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		delete_post_meta( $post_id, '_settagord' );
		update_post_meta( $post_id, '_tag_order', implode( ',', $ids ) );

		wp_set_post_terms( $post_id, array(), 'post_tag', false );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_tag_order', true ) );
	}

	public function test_appending_tags_preserves_the_existing_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		$gamma = (int) wp_insert_term( 'Gamma', 'post_tag' )['term_id'];
		wp_set_post_terms( $post_id, array( $gamma ), 'post_tag', true );

		$this->assertSame( array( $ids[1], $ids[0], $gamma ), $this->get_tag_order( $post_id ) );
	}

	public function test_revisions_do_not_get_their_own_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );

		$revision_id = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);

		wp_set_object_terms( $revision_id, $ids, 'post_tag', false );

		$this->assertSame( '', get_post_meta( $revision_id, '_settagord', true ) );
	}

	/**
	 * DOING_AUTOSAVE cannot be undefined once set, and a leaked definition
	 * would silently disable the sync handler for every later test. Isolating
	 * the process is the only way to assert this without that side effect.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_autosaves_are_skipped() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		define( 'DOING_AUTOSAVE', true );
		wp_set_post_terms( $post_id, array( $ids[0] ), 'post_tag', false );

		// The order is untouched because the handler bails during an autosave.
		$this->assertSame( array( $ids[1], $ids[0] ), $this->get_tag_order( $post_id ) );
	}

	// --- synchronize_on_load ----------------------------------------------

	public function test_synchronize_drops_stale_ids_and_appends_new_tags() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$gamma                 = (int) wp_insert_term( 'Gamma', 'post_tag' )['term_id'];

		wp_set_post_terms( $post_id, array( $ids[1], $gamma ), 'post_tag', false );
		$this->set_tag_order( $post_id, array( $ids[0], $ids[1] ) );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame( array( $ids[1], $gamma ), $this->get_tag_order( $post_id ) );
	}

	public function test_synchronize_clears_the_order_when_no_tags_remain() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );
		wp_delete_object_term_relationships( $post_id, 'post_tag' );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
	}

	public function test_synchronize_leaves_a_correct_order_alone() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame( array( $ids[1], $ids[0] ), $this->get_tag_order( $post_id ) );
	}

	public function test_synchronize_ignores_revisions_and_missing_posts() {
		$this->assertNull( settagord_synchronize_on_load( 0 ) );

		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$revision_id           = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);

		settagord_synchronize_on_load( $revision_id );

		$this->assertSame( '', get_post_meta( $revision_id, '_settagord', true ) );
	}

	public function test_synchronize_runs_for_a_user_who_can_edit_the_post() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$gamma                 = (int) wp_insert_term( 'Gamma', 'post_tag' )['term_id'];

		wp_set_post_terms( $post_id, array( $ids[0], $gamma ), 'post_tag', false );
		$this->set_tag_order( $post_id, $ids );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame( array( $ids[0], $gamma ), $this->get_tag_order( $post_id ) );
	}
}
