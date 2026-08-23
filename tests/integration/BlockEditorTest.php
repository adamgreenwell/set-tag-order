<?php
/**
 * The core/post-terms block.
 *
 * Since 1.2.0 the block is rendered by WordPress, not replaced by the plugin,
 * so these tests assert core's own markup survives alongside the plugin's
 * ordering, classes, and separator.
 *
 * @package SetTagOrder\Tests
 */
class BlockEditorTest extends WP_UnitTestCase {

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

	public function test_block_renders_tags_in_the_saved_order() {
		list( $post_id, $ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$this->set_tag_order( $post_id, array( $ids[1], $ids[0] ) );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	/**
	 * The block used to be re-rendered from scratch, which threw away the
	 * wrapper that get_block_wrapper_attributes() builds.
	 */
	public function test_block_keeps_core_wrapper_markup() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );

		$html = $this->render_post_terms_block(
			$post_id,
			array(
				'textAlign' => 'center',
				'prefix'    => 'Filed under:',
				'suffix'    => 'end',
				'className' => 'my-custom-class',
			)
		);

		$this->assertStringContainsString( 'wp-block-post-terms', $html );
		$this->assertStringContainsString( 'taxonomy-post_tag', $html );
		$this->assertStringContainsString( 'has-text-align-center', $html );
		$this->assertStringContainsString( 'my-custom-class', $html );
		$this->assertStringContainsString( 'wp-block-post-terms__prefix', $html );
		$this->assertStringContainsString( 'wp-block-post-terms__suffix', $html );
		$this->assertStringContainsString( 'rel="tag"', $html );
	}

	public function test_block_applies_the_custom_separator() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_option( 'settagord_separator', '//' );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertStringContainsString(
			'<span class="wp-block-post-terms__separator">//</span>',
			$html
		);
	}

	public function test_block_separator_is_escaped() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_option( 'settagord_separator', '<b>&</b>' );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '&amp;', $html );
	}

	public function test_block_keeps_the_theme_separator_when_none_is_configured() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );

		$html = $this->render_post_terms_block( $post_id, array( 'separator' => ' ~ ' ) );

		$this->assertStringContainsString(
			'<span class="wp-block-post-terms__separator"> ~ </span>',
			$html
		);
	}

	public function test_block_applies_the_custom_class() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_option( 'settagord_class', 'badge' );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertSame( 2, substr_count( $html, 'badge' ) );
		$this->assertSame( 2, substr_count( $html, 'rel="tag"' ) );
	}

	public function test_filter_ignores_other_taxonomies() {
		$content = '<div><span class="wp-block-post-terms__separator">, </span></div>';
		update_option( 'settagord_separator', '//' );

		$this->assertSame(
			$content,
			settagord_filter_post_terms_block(
				$content,
				array(
					'blockName' => 'core/post-terms',
					'attrs'     => array( 'term' => 'category' ),
				)
			)
		);
	}

	public function test_filter_ignores_other_blocks() {
		$content = '<div><span class="wp-block-post-terms__separator">, </span></div>';
		update_option( 'settagord_separator', '//' );

		$this->assertSame(
			$content,
			settagord_filter_post_terms_block(
				$content,
				array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(),
				)
			)
		);
	}

	public function test_filter_tolerates_empty_content_and_missing_attributes() {
		update_option( 'settagord_separator', '//' );

		$this->assertSame( '', settagord_filter_post_terms_block( '', array( 'blockName' => 'core/post-terms' ) ) );
		$this->assertSame( 'x', settagord_filter_post_terms_block( 'x', array() ) );
	}
}
