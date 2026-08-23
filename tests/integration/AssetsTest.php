<?php
/**
 * Script and style registration, and the debug logging guards.
 *
 * @package SetTagOrder\Tests
 */
class AssetsTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();
		settagord_register_assets();
	}

	public function test_block_editor_script_is_registered_with_the_right_dependencies() {
		$this->assertTrue( wp_script_is( 'settagord-script', 'registered' ) );

		$script = wp_scripts()->registered['settagord-script'];

		// wp-editor, not the deprecated wp-edit-post slot package.
		$this->assertContains( 'wp-editor', $script->deps );
		$this->assertNotContains( 'wp-edit-post', $script->deps );

		// wp-a11y powers the reorder announcements.
		$this->assertContains( 'wp-a11y', $script->deps );

		$this->assertSame( SETTAGORD_VERSION, $script->ver );
	}

	public function test_block_editor_script_is_deferred_and_in_the_footer() {
		$script = wp_scripts()->registered['settagord-script'];

		$this->assertSame( 'defer', $script->extra['strategy'] ?? null );
		$this->assertSame( 1, $script->extra['group'] ?? null );
	}

	public function test_panel_styles_are_registered() {
		$this->assertTrue( wp_style_is( 'settagord-panel-styles', 'registered' ) );
		$this->assertSame( SETTAGORD_VERSION, wp_styles()->registered['settagord-panel-styles']->ver );
	}

	/**
	 * Asset versions used to come from filemtime(), which differs per install
	 * and stats the filesystem on every page load.
	 */
	public function test_asset_versions_use_the_plugin_version_constant() {
		$header = get_file_data( SETTAGORD_PLUGIN_FILE, array( 'Version' => 'Version' ) );

		$this->assertSame( $header['Version'], SETTAGORD_VERSION );
		$this->assertSame( SETTAGORD_VERSION, wp_scripts()->registered['settagord-script']->ver );
	}

	// --- Debug logging ----------------------------------------------------

	/**
	 * Point error_log() at a file so the assertions can read what was written,
	 * instead of the messages spraying into the test output.
	 *
	 * @return string Path to the capture file.
	 */
	private function capture_error_log() {
		$path = wp_tempnam( 'settagord-log' );

		$this->previous_error_log = ini_get( 'error_log' );
		ini_set( 'error_log', $path );

		return $path;
	}

	private $previous_error_log = null;

	private function restore_error_log() {
		if ( null !== $this->previous_error_log ) {
			ini_set( 'error_log', $this->previous_error_log );
			$this->previous_error_log = null;
		}
	}

	public function test_nothing_is_logged_while_debug_mode_is_off() {
		delete_option( 'settagord_debug_mode' );
		$log = $this->capture_error_log();

		settagord_debug_log( 'should not appear' );

		$this->restore_error_log();
		$this->assertStringNotContainsString( 'should not appear', (string) file_get_contents( $log ) );
	}

	public function test_messages_are_logged_while_debug_mode_is_on() {
		update_option( 'settagord_debug_mode', true );
		$log = $this->capture_error_log();

		settagord_debug_log( 'a distinctive message' );

		$this->restore_error_log();
		$contents = (string) file_get_contents( $log );

		$this->assertStringContainsString( '[Set Tag Order Debug]', $contents );
		$this->assertStringContainsString( 'a distinctive message', $contents );
	}

	public function test_debug_filters_return_their_input_unchanged_when_logging_is_off() {
		delete_option( 'settagord_debug_mode' );

		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha' ) );
		$terms           = get_the_tags( $post_id );

		$this->assertSame( $terms, settagord_debug_get_the_terms( $terms, $post_id, 'post_tag' ) );
		$this->assertNull( settagord_debug_pre_render_block( null, array( 'blockName' => 'core/post-terms', 'attrs' => array() ) ) );
	}

	public function test_debug_filters_return_their_input_unchanged_when_logging_is_on() {
		update_option( 'settagord_debug_mode', true );
		$this->capture_error_log();
		$this->assertTrue( settagord_debug_enabled() );

		list( $post_id ) = $this->create_post_with_tags( array( 'Alpha', 'Beta' ) );
		$terms           = get_the_tags( $post_id );

		$this->assertSame( $terms, settagord_debug_get_the_terms( $terms, $post_id, 'post_tag' ) );
		$this->assertSame( $terms, settagord_debug_get_the_terms( $terms, $post_id, 'category' ) );
		$this->assertSame( 'x', settagord_debug_pre_render_block( 'x', array( 'blockName' => 'core/post-terms', 'attrs' => array( 'term' => 'post_tag' ) ) ) );
		$this->assertSame( 'x', settagord_debug_pre_render_block( 'x', array( 'blockName' => 'core/paragraph' ) ) );

		$this->restore_error_log();
	}

	public function test_debug_filters_tolerate_a_wp_error_and_a_non_array() {
		update_option( 'settagord_debug_mode', true );
		$this->capture_error_log();

		$error = new WP_Error( 'nope', 'No terms' );

		$this->assertSame( $error, settagord_debug_get_the_terms( $error, 1, 'post_tag' ) );
		$this->assertFalse( settagord_debug_get_the_terms( false, 1, 'post_tag' ) );

		$this->restore_error_log();
	}
}
