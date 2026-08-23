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

		settagord_get_post_types_with_tags( true );
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

	/**
	 * Put WordPress into front-end context.
	 *
	 * WP_Ajax_UnitTestCase::set_up() calls set_current_screen( 'ajax' ), which
	 * makes is_admin() true for the whole test. Filters that only run on the
	 * front end are skipped under that default, so any test asserting rendered
	 * output has to switch context explicitly.
	 */
	private function given_front_end_request() {
		set_current_screen( 'front' );
		$this->assertFalse( is_admin() );
	}

	public function test_get_ordered_post_tags_uses_saved_order() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array( $term_ids[2], $term_ids[0], $term_ids[1] ) ) );

		$tags = settagord_get_ordered_post_tags( $post_id );

		$this->assertSame( array( 'Gamma', 'Alpha', 'Beta' ), $this->ordered_tag_names( $tags ) );
	}

	public function test_get_ordered_post_tags_reads_legacy_tag_order_meta_and_migrates_it() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta', 'Gamma' ) );
		$legacy_order               = implode( ',', array( $term_ids[2], $term_ids[0], $term_ids[1] ) );
		delete_post_meta( $post_id, '_settagord' );
		update_post_meta( $post_id, '_tag_order', $legacy_order );

		$tags = settagord_get_ordered_post_tags( $post_id );

		$this->assertSame( array( 'Gamma', 'Alpha', 'Beta' ), $this->ordered_tag_names( $tags ) );
		$this->assertSame( $legacy_order, get_post_meta( $post_id, '_settagord', true ) );
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

	public function test_removing_all_tags_clears_legacy_saved_order() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		delete_post_meta( $post_id, '_settagord' );
		update_post_meta( $post_id, '_tag_order', implode( ',', $term_ids ) );

		wp_set_post_terms( $post_id, array(), 'post_tag', false );

		$this->assertSame( '', get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_tag_order', true ) );
	}

	public function test_term_links_filter_keeps_links_when_custom_separator_uses_default_class() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array_reverse( $term_ids ) ) );
		update_option( 'settagord_separator', '|' );
		update_option( 'settagord_class', 'tag' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_term_list( $post_id, 'post_tag', '', '<span>|</span>', '' );

		$this->assertStringContainsString( 'Beta', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	/**
	 * Render a real core/post-terms block for a post.
	 */
	private function render_post_terms_block( $post_id, array $attrs = array() ) {
		$attrs = array_merge( array( 'term' => 'post_tag' ), $attrs );

		$block = new WP_Block(
			array(
				'blockName' => 'core/post-terms',
				'attrs'     => $attrs,
				'innerHTML' => '',
			),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		return $block->render();
	}

	public function test_post_terms_block_renders_tags_in_saved_order() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array_reverse( $term_ids ) ) );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	/**
	 * The block used to be re-rendered from scratch, which threw away the
	 * wrapper that get_block_wrapper_attributes() builds.
	 */
	public function test_post_terms_block_keeps_core_wrapper_markup() {
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

	public function test_post_terms_block_applies_custom_separator() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_option( 'settagord_separator', '//' );

		$html = $this->render_post_terms_block( $post_id );

		$this->assertStringContainsString(
			'<span class="wp-block-post-terms__separator">//</span>',
			$html
		);
	}

	public function test_post_terms_block_filter_ignores_other_taxonomies_and_blocks() {
		$content = '<div><span class="wp-block-post-terms__separator">, </span></div>';
		update_option( 'settagord_separator', '//' );

		$this->assertSame(
			$content,
			settagord_filter_post_terms_block(
				$content,
				array( 'blockName' => 'core/post-terms', 'attrs' => array( 'term' => 'category' ) )
			)
		);

		$this->assertSame(
			$content,
			settagord_filter_post_terms_block(
				$content,
				array( 'blockName' => 'core/paragraph', 'attrs' => array() )
			)
		);
	}

	/**
	 * The class used to be applied by rebuilding the anchor from the tag name,
	 * which dropped rel="tag" and anything another plugin had added.
	 */
	public function test_custom_class_is_added_without_rebuilding_the_link() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array_reverse( $term_ids ) ) );
		update_option( 'settagord_class', 'badge highlight' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_term_list( $post_id, 'post_tag', '', ' | ', '' );

		$this->assertStringContainsString( 'badge', $html );
		$this->assertStringContainsString( 'highlight', $html );
		$this->assertStringContainsString( 'rel="tag"', $html );
		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	public function test_custom_class_default_leaves_links_untouched() {
		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha' ) );
		update_option( 'settagord_class', 'tag' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_term_list( $post_id, 'post_tag', '', ' | ', '' );

		$this->assertStringNotContainsString( 'class=', $html );
	}

	/**
	 * Tag names and URLs can contain the same characters as the separator, so
	 * the replacement must only touch the text joining the anchors.
	 */
	public function test_the_tags_separator_does_not_corrupt_tag_names() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Rock, Paper', 'Scissors' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );
		update_option( 'settagord_separator', '/' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		ob_start();
		the_tags( '', ', ', '' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Rock, Paper', $html );
		$this->assertStringContainsString( 'settagord-tag-separator', $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
	}

	public function test_the_tags_separator_is_not_applied_to_before_and_after_markup() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );
		update_option( 'settagord_separator', '/' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		// $before ends with an anchor followed by the same separator the links
		// are joined with - the one place a naive match would overreach.
		$before = '<p><a href="/tags">All tags</a>, ';
		$html   = get_the_tag_list( $before, ', ', '</p>', $post_id );

		$this->assertStringContainsString( $before, $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
		$this->assertStringEndsWith( '</p>', $html );
	}

	public function test_the_tags_separator_handles_regex_and_backreference_characters() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );

		// "$1" would be read as a backreference by preg_replace(); the
		// backslash and the dot are regex-significant in the pattern.
		update_option( 'settagord_separator', '$1\\.' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_tag_list( '', ', ', '', $post_id );

		$this->assertStringContainsString( '>$1\\.<', $html );
		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
	}

	public function test_the_tags_separator_is_inserted_when_theme_passes_no_separator() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );
		update_option( 'settagord_separator', '/' );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_tag_list( '', '', '', $post_id );

		$this->assertSame( 1, substr_count( $html, 'settagord-tag-separator' ) );
	}

	public function test_the_tags_output_is_unchanged_when_no_separator_is_configured() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );

		$this->given_front_end_request();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$html = get_the_tag_list( '', ' :: ', '', $post_id );

		$this->assertStringContainsString( ' :: ', $html );
		$this->assertStringNotContainsString( 'settagord-tag-separator', $html );
	}

	public function test_synchronize_on_load_runs_for_users_who_can_edit_the_post() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$gamma                      = wp_insert_term( 'Gamma', 'post_tag' );

		wp_set_post_terms( $post_id, array( $term_ids[0], (int) $gamma['term_id'] ), 'post_tag', false );
		update_post_meta( $post_id, '_settagord', implode( ',', $term_ids ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		settagord_synchronize_on_load( $post_id );

		$this->assertSame(
			implode( ',', array( $term_ids[0], (int) $gamma['term_id'] ) ),
			get_post_meta( $post_id, '_settagord', true )
		);
	}

	public function test_legacy_template_helpers_remain_available() {
		list( $post_id, $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		update_post_meta( $post_id, '_settagord', implode( ',', array_reverse( $term_ids ) ) );

		$this->assertTrue( function_exists( 'get_ordered_post_tags' ) );
		$this->assertTrue( function_exists( 'the_ordered_post_tags' ) );
		$this->assertSame(
			array( 'Beta', 'Alpha' ),
			$this->ordered_tag_names( get_ordered_post_tags( $post_id ) )
		);

		ob_start();
		the_ordered_post_tags( '', ', ', '', $post_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Beta', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ) );
	}

	public function test_ajax_add_tag_creates_term_for_authorized_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_add_tag_nonce' );
		$_POST['tag_name'] = 'Fresh Tag';

		try {
			$this->_handleAjax( 'settagord_add_tag' );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Fresh Tag', $response['data']['name'] );
		$this->assertNotNull( term_exists( 'Fresh Tag', 'post_tag' ) );
	}

	public function test_ajax_add_tag_requires_term_creation_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'settagord_add_tag_nonce' );
		$_POST['tag_name'] = 'Nope Tag';

		try {
			$this->_handleAjax( 'settagord_add_tag' );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertNull( term_exists( 'Nope Tag', 'post_tag' ) );
	}

	public function test_classic_save_handler_persists_post_tags_and_order() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		list( , $term_ids ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['settagord_meta_box_nonce'] = wp_create_nonce( 'settagord_meta_box' );
		$_POST['post_tags']               = implode( ',', array( $term_ids[1], $term_ids[0] ) );
		$_POST['settagord']                = implode( ',', array( $term_ids[1], $term_ids[0] ) );

		do_action( 'save_post', $post_id, get_post( $post_id ), true );

		$assigned_ids = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		sort( $assigned_ids );
		$expected_ids = array( $term_ids[0], $term_ids[1] );
		sort( $expected_ids );

		$this->assertSame( implode( ',', array( $term_ids[1], $term_ids[0] ) ), get_post_meta( $post_id, '_settagord', true ) );
		$this->assertSame( $expected_ids, $assigned_ids );
	}
}
