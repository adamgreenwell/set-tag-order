<?php
/**
 * Core ordering behaviour and the tag order metadata.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class OrderingTest extends WP_UnitTestCase {

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

	public function test_tags_are_returned_in_the_saved_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$this->set_tag_order( $post_id, array( $ids[2], $ids[0], $ids[1] ) );

		$this->assertSame(
			array( 'Gamma', 'Alpha', 'Beta' ),
			$this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) )
		);
	}

	public function test_saved_order_without_any_order_meta_falls_back_to_wordpress_order() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		delete_post_meta( $post_id, '_settagord' );

		$names = $this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) );

		sort( $names );
		$this->assertSame( array( 'Alpha', 'Beta' ), $names );
	}

	/**
	 * A stale order must never hide a tag - the worst case is that unknown
	 * tags fall to the end.
	 */
	public function test_ids_in_the_order_that_are_no_longer_assigned_are_skipped() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( 999999, $ids[1], $ids[0] ) );

		$this->assertSame(
			array( 'Beta', 'Alpha' ),
			$this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) )
		);
	}

	public function test_tags_missing_from_the_order_are_appended() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$this->set_tag_order( $post_id, array( $ids[2] ) );

		$names = $this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) );

		$this->assertSame( 'Gamma', $names[0] );
		$this->assertCount( 3, $names );
		$this->assertContains( 'Alpha', $names );
		$this->assertContains( 'Beta', $names );
	}

	public function test_order_meta_is_read_as_integers() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );

		// Whitespace and string IDs are what older versions and hand-edited
		// meta can leave behind.
		update_post_meta( $post_id, '_settagord', ' ' . $ids[1] . ' , ' . $ids[0] . ' ' );

		$this->assertSame(
			array( 'Beta', 'Alpha' ),
			$this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) )
		);
	}

	public function test_returns_false_when_the_post_has_no_tags() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( settagord_get_ordered_post_tags( $post_id ) );
	}

	public function test_returns_false_for_a_post_type_without_tag_support() {
		register_post_type( 'settagord_notags', array( 'public' => true ) );
		settagord_get_post_types_with_tags( true );

		$post_id = self::factory()->post->create( array( 'post_type' => 'settagord_notags' ) );

		$this->assertFalse( settagord_get_ordered_post_tags( $post_id ) );

		unregister_post_type( 'settagord_notags' );
	}

	public function test_order_tags_returns_input_unchanged_when_there_is_no_order() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha' ) );
		delete_post_meta( $post_id, '_settagord' );

		$tags = get_the_tags( $post_id );

		$this->assertSame( $tags, settagord_order_tags( $tags, $post_id ) );
	}

	public function test_post_type_list_is_memoized_and_rebuilt_on_registration() {
		$before = settagord_get_post_types_with_tags();
		$this->assertContains( 'post', $before );

		register_post_type(
			'settagord_cached',
			array(
				'public'     => true,
				'taxonomies' => array( 'post_tag' ),
			)
		);

		// registered_post_type fires the cache rebuild, so no manual refresh.
		$this->assertContains( 'settagord_cached', settagord_get_post_types_with_tags() );

		unregister_post_type( 'settagord_cached' );

		$this->assertNotContains( 'settagord_cached', settagord_get_post_types_with_tags() );
	}
}
