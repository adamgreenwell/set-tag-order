<?php
/**
 * The filters the plugin exposes for theme and plugin authors.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class FiltersTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();
		$this->given_front_end_request();
	}

	public function tear_down() {
		remove_all_filters( 'settagord_ordered_tags' );
		remove_all_filters( 'settagord_separator' );
		remove_all_filters( 'settagord_link_classes' );
		unset( $GLOBALS['post'] );
		wp_reset_postdata();
		parent::tear_down();
	}

	public function test_ordered_tags_filter_can_reorder_and_limit() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$this->set_tag_order( $post_id, $ids );

		add_filter(
			'settagord_ordered_tags',
			function ( $tags ) {
				return array_slice( array_reverse( $tags ), 0, 2 );
			}
		);

		$this->assertSame(
			array( 'Gamma', 'Beta' ),
			$this->ordered_tag_names( settagord_get_ordered_post_tags( $post_id ) )
		);
	}

	public function test_ordered_tags_filter_receives_the_post_id_and_original_tags() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		$seen = array();

		add_filter(
			'settagord_ordered_tags',
			function ( $ordered, $id, $original ) use ( &$seen ) {
				$seen = array(
					'post_id'  => $id,
					'ordered'  => wp_list_pluck( $ordered, 'name' ),
					'original' => count( $original ),
				);

				return $ordered;
			},
			10,
			3
		);

		settagord_get_ordered_post_tags( $post_id );

		$this->assertSame( $post_id, $seen['post_id'] );
		$this->assertSame( array( 'Beta', 'Alpha' ), $seen['ordered'] );
		$this->assertSame( 2, $seen['original'] );
	}

	public function test_ordered_tags_filter_applies_to_rendered_output() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$this->set_tag_order( $post_id, $ids );
		$this->given_current_post( $post_id );

		add_filter(
			'settagord_ordered_tags',
			function ( $tags ) {
				return array_slice( $tags, 0, 1 );
			}
		);

		$html = get_the_tag_list( '', ', ', '', $post_id );

		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringNotContainsString( 'Beta', $html );
	}

	public function test_separator_filter_overrides_the_saved_option() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_separator', '/' );
		$this->given_current_post( $post_id );

		add_filter( 'settagord_separator', fn() => '|' );

		$html = get_the_tag_list( '', ', ', '', $post_id );

		$this->assertStringContainsString( '>|<', $html );
		$this->assertStringNotContainsString( '>/<', $html );
	}

	public function test_separator_filter_can_disable_separator_handling() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_separator', '/' );
		$this->given_current_post( $post_id );

		add_filter( 'settagord_separator', fn() => '' );

		$html = get_the_tag_list( '', ' :: ', '', $post_id );

		$this->assertStringContainsString( ' :: ', $html );
		$this->assertStringNotContainsString( 'settagord-tag-separator', $html );
	}

	public function test_link_classes_filter_overrides_the_saved_option() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_class', 'tag' );
		$this->given_current_post( $post_id );

		add_filter( 'settagord_link_classes', fn() => array( 'from-filter' ) );

		$this->assertStringContainsString( 'from-filter', get_the_tag_list( '', ', ', '', $post_id ) );
	}

	public function test_link_classes_filter_can_append_to_the_configured_value() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_class', 'badge' );
		$this->given_current_post( $post_id );

		add_filter(
			'settagord_link_classes',
			function ( $classes ) {
				$classes[] = 'is-style-pill';

				return $classes;
			}
		);

		$html = get_the_tag_list( '', ', ', '', $post_id );

		$this->assertStringContainsString( 'badge', $html );
		$this->assertStringContainsString( 'is-style-pill', $html );
	}
}
