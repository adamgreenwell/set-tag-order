<?php
/**
 * The theme-facing template functions and their legacy aliases.
 *
 * @package SetTagOrder\Tests
 * @coversNothing
 */
class TemplateTagsTest extends WP_UnitTestCase {

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

	private function render( $before = '', $sep = '', $after = '', $post_id = 0 ) {
		ob_start();
		settagord_the_ordered_post_tags( $before, $sep, $after, $post_id );

		return ob_get_clean();
	}

	public function test_outputs_tags_in_the_saved_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		$html = $this->render( '', ', ', '', $post_id );

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	public function test_wraps_output_in_before_and_after() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );

		$html = $this->render( '<p class="tags">', ', ', '</p>', $post_id );

		$this->assertStringStartsWith( '<p class="tags">', $html );
		$this->assertStringEndsWith( '</p>', $html );
	}

	public function test_uses_the_passed_separator_when_no_setting_exists() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );

		$html = $this->render( '', ' | ', '', $post_id );

		$this->assertStringContainsString( '|', $html );
		$this->assertStringNotContainsString( 'settagord-tag-separator', $html );
	}

	/**
	 * Themes commonly pass markup as the separator. Escaping it would turn the
	 * tags into one run of visible angle brackets.
	 */
	public function test_a_markup_separator_from_the_theme_is_not_escaped() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );

		$html = $this->render( '<ul><li>', '</li><li>', '</li></ul>', $post_id );

		$this->assertStringContainsString( '</li><li>', $html );
		$this->assertStringNotContainsString( '&lt;/li&gt;', $html );
	}

	public function test_the_setting_overrides_the_passed_separator() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_separator', '/' );

		$html = $this->render( '', ' | ', '', $post_id );

		$this->assertStringContainsString( 'settagord-tag-separator', $html );
		$this->assertStringNotContainsString( '|', $html );
	}

	public function test_applies_the_configured_class() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );
		update_option( 'settagord_class', 'badge' );

		$this->assertStringContainsString( 'class="badge"', $this->render( '', '', '', $post_id ) );
	}

	public function test_outputs_nothing_for_a_post_without_tags() {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', $this->render( '<p>', ', ', '</p>', $post_id ) );
	}

	public function test_falls_back_to_the_current_post() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$this->set_tag_order( $post_id, $ids );
		$this->given_current_post( $post_id );

		$this->assertStringContainsString( 'Alpha', $this->render() );
	}

	public function test_legacy_aliases_exist_and_delegate() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		$this->assertTrue( function_exists( 'get_ordered_post_tags' ) );
		$this->assertTrue( function_exists( 'the_ordered_post_tags' ) );

		$this->assertSame(
			array( 'Beta', 'Alpha' ),
			$this->ordered_tag_names( get_ordered_post_tags( $post_id ) )
		);

		ob_start();
		the_ordered_post_tags( '', ', ', '', $post_id );
		$html = ob_get_clean();

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}
}
