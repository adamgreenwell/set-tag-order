<?php
/**
 * Front-end tag output: separators, CSS classes, and the separator stylesheet.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class OutputTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();
		$this->given_front_end_request();
	}

	public function tear_down() {
		unset( $GLOBALS['post'] );
		wp_reset_postdata();
		parent::tear_down();
	}

	private function tag_list( $before = '', $sep = ', ', $after = '' ) {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );
		$this->given_current_post( $post_id );

		return get_the_tag_list( $before, $sep, $after, $post_id );
	}

	// --- Separator --------------------------------------------------------

	public function test_separator_replaces_only_the_join_between_tags() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Rock, Paper', 'Scissors' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_separator', '/' );
		$this->given_current_post( $post_id );

		$html = get_the_tag_list( '', ', ', '', $post_id );

		$this->assertStringContainsString( 'Rock, Paper', $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
	}

	public function test_separator_does_not_touch_before_and_after_markup() {
		update_option( 'settagord_separator', '/' );

		// $before ends with an anchor followed by the same separator the links
		// are joined with - the one place a naive match would overreach.
		$before = '<p><a href="/tags">All tags</a>, ';
		$html   = $this->tag_list( $before, ', ', '</p>' );

		$this->assertStringContainsString( $before, $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
		$this->assertStringEndsWith( '</p>', $html );
	}

	public function test_separator_handles_regex_and_backreference_characters() {
		// "$1" would be read as a backreference by preg_replace().
		update_option( 'settagord_separator', '$1\\.' );

		$html = $this->tag_list();

		$this->assertStringContainsString( '>$1\\.<', $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
	}

	public function test_separator_is_escaped_on_output() {
		update_option( 'settagord_separator', ' & ' );

		$html = $this->tag_list( '', '', '' );

		$this->assertStringContainsString( '&amp;', $html );
		$this->assertStringNotContainsString( '> & <', $html );
	}

	public function test_separator_is_inserted_when_the_theme_passes_none() {
		update_option( 'settagord_separator', '/' );

		$this->assertSame( 1, substr_count( $this->tag_list( '', '', '' ), 'settagord-tag-separator' ) );
	}

	public function test_output_is_untouched_when_no_separator_is_configured() {
		$html = $this->tag_list( '', ' :: ', '' );

		$this->assertStringContainsString( ' :: ', $html );
		$this->assertStringNotContainsString( 'settagord-tag-separator', $html );
	}

	// --- CSS classes ------------------------------------------------------

	public function test_configured_classes_are_added_to_each_link() {
		update_option( 'settagord_class', 'badge highlight' );

		$html = $this->tag_list();

		$this->assertSame( 2, substr_count( $html, 'badge' ) );
		$this->assertStringContainsString( 'highlight', $html );
	}

	/**
	 * The class used to be applied by rebuilding the anchor from the tag name,
	 * which dropped rel="tag" and anything another plugin had added.
	 */
	public function test_adding_classes_preserves_the_rest_of_the_anchor() {
		update_option( 'settagord_class', 'badge' );

		$decorate = function ( $links ) {
			return array_map(
				function ( $link ) {
					return str_replace( '<a ', '<a data-from-other-plugin="1" ', $link );
				},
				$links
			);
		};

		add_filter( 'term_links-post_tag', $decorate, 5 );
		$html = $this->tag_list();
		remove_filter( 'term_links-post_tag', $decorate, 5 );

		$this->assertSame( 2, substr_count( $html, 'rel="tag"' ) );
		$this->assertSame( 2, substr_count( $html, 'data-from-other-plugin="1"' ) );
		$this->assertSame( 2, substr_count( $html, 'badge' ) );
	}

	public function test_the_default_class_value_adds_no_class_attribute() {
		update_option( 'settagord_class', 'tag' );

		$this->assertStringNotContainsString( 'class=', $this->tag_list() );
	}

	public function test_ordering_still_applies_when_no_class_is_configured() {
		$html = $this->tag_list();

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	public function test_classes_are_not_applied_in_the_admin() {
		update_option( 'settagord_class', 'badge' );
		set_current_screen( 'edit-post' );

		$this->assertTrue( is_admin() );
		$this->assertStringNotContainsString( 'badge', $this->tag_list() );
	}

	public function test_add_classes_to_link_leaves_non_anchor_markup_alone() {
		update_option( 'settagord_class', 'badge' );

		$this->assertSame( '<span>Alpha</span>', settagord_add_classes_to_link( '<span>Alpha</span>' ) );
		$this->assertSame( '', settagord_add_classes_to_link( '' ) );
	}

	// --- Separator stylesheet --------------------------------------------

	public function test_separator_stylesheet_is_only_registered_when_needed() {
		settagord_custom_css();
		$this->assertFalse( wp_style_is( 'settagord-inline', 'enqueued' ) );

		update_option( 'settagord_separator', '/' );
		settagord_custom_css();

		$this->assertTrue( wp_style_is( 'settagord-inline', 'enqueued' ) );

		// Attached to a handle the plugin owns, so it cannot be dropped when a
		// theme does not load the core block styles.
		$inline = wp_styles()->get_data( 'settagord-inline', 'after' );
		$this->assertNotEmpty( $inline );
		$this->assertStringContainsString( 'settagord-tag-separator', implode( '', (array) $inline ) );
	}
}
