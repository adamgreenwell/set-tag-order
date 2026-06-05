<?php
/**
 * Integration tests for Set Tag Order behavior.
 *
 * @package SetTagOrder\Tests
 */

/**
 * @coversNothing
 */
class TagOrderIntegrationTest extends WP_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();

		delete_option( 'settagord_separator' );
		delete_option( 'settagord_class' );
		delete_option( 'settagord_debug_mode' );
		unset( $_POST['settagord_meta_box_nonce'], $_POST['post_tags'], $_POST['settagord'], $_POST['_wpnonce'], $_POST['tag_name'] );
	}

	public function tear_down() {
		unset( $GLOBALS['post'] );
		wp_reset_postdata();

		parent::tear_down();
	}

	private function create_post_with_tags( array $tag_names ) {
		$post_id  = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term_ids = array();

		foreach ( $tag_names as $tag_name ) {
			$term       = wp_insert_term( $tag_name, 'post_tag' );
			$term_ids[] = (int) $term['term_id'];
		}

		wp_set_post_terms( $post_id, $term_ids, 'post_tag', false );

		return array( $post_id, $term_ids );
	}

	private function ordered_tag_names( array $tags ) {
		return wp_list_pluck( $tags, 'name' );
	}

	public function test_get_ordered_post_tags_uses_saved_order() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array( $term_ids[2], $term_ids[0], $term_ids[1] ) ) );

		$tags = settagord_get_ordered_post_tags( $post_id );

		$this->assertSame( array( 'Gamma', 'Alpha', 'Beta' ), $this->ordered_tag_names( $tags ) );
	}

	public function test_synchronize_on_load_removes_stale_ids_and_appends_new_tags() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$gamma                      = wp_insert_term( 'Gamma', 'post_tag' );

		wp_set_post_terms( $post_id, array( $term_ids[1], (int) $gamma['term_id'] ), 'post_tag', false );
		update_post_meta( $post_id, '_settagord', implode( ',', array( $term_ids[0], $term_ids[1] ) ) );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame( implode( ',', array( $term_ids[1], (int) $gamma['term_id'] ) ), get_post_meta( $post_id, '_settagord', true ) );
	}

	public function test_set_object_terms_sync_handles_multiple_term_taxonomy_ids() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		update_post_meta( $post_id, '_settagord', (string) $term_ids[0] );

		$beta  = wp_insert_term( 'Beta', 'post_tag' );
		$gamma = wp_insert_term( 'Gamma', 'post_tag' );

		wp_set_post_terms( $post_id, array( $term_ids[0], (int) $beta['term_id'], (int) $gamma['term_id'] ), 'post_tag', false );

		$this->assertSame(
			implode( ',', array( $term_ids[0], (int) $beta['term_id'], (int) $gamma['term_id'] ) ),
			get_post_meta( $post_id, '_settagord', true )
		);
	}

	public function test_removing_all_tags_clears_saved_order() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );

		wp_set_post_terms( $post_id, array(), 'post_tag', false );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
	}

	public function test_term_links_filter_keeps_links_when_custom_separator_uses_default_class() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array_reverse( $term_ids ) ) );
		update_option( 'settagord_separator', '|' );
		update_option( 'settagord_class', 'tag' );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_term_list( $post_id, 'post_tag', '', '<span>|</span>', '' );

		$this->assertStringContainsString( 'Beta', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}
}
