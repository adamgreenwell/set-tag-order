<?php
/**
 * The settings page: registration, sanitization, and the Plugins screen link.
 *
 * @package SetTagOrder\Tests
 */
class SettingsTest extends WP_UnitTestCase {

	use TagOrderTestHelpers;

	public function set_up() {
		parent::set_up();
		$this->reset_plugin_state();

		// Settings register on admin_init, which does not fire in the test
		// bootstrap, so run the registration directly.
		settagord_register_settings();
	}

	public function test_settings_are_registered_with_defaults() {
		$registered = get_registered_settings();

		foreach ( array( 'settagord_separator', 'settagord_class', 'settagord_debug_mode' ) as $key ) {
			$this->assertArrayHasKey( $key, $registered, "$key should be registered" );
			$this->assertSame( 'settagord_settings', $registered[ $key ]['group'] );
		}

		$this->assertSame( '', $registered['settagord_separator']['default'] );
		$this->assertSame( 'tag', $registered['settagord_class']['default'] );
		$this->assertFalse( $registered['settagord_debug_mode']['default'] );
	}

	public function test_class_setting_is_sanitized_to_valid_html_classes() {
		$this->assertSame( 'badge highlight', settagord_sanitize_class_list( '  badge   highlight  ' ) );
		$this->assertSame( 'badge', settagord_sanitize_class_list( 'badge badge' ) );
		$this->assertSame( '', settagord_sanitize_class_list( '' ) );
		$this->assertSame( '', settagord_sanitize_class_list( '   ' ) );
	}

	public function test_class_setting_strips_characters_invalid_in_a_class_attribute() {
		$this->assertSame( 'scriptalert1script', settagord_sanitize_class_list( '<script>alert(1)</script>' ) );
		$this->assertSame( 'oktoo', settagord_sanitize_class_list( 'ok"too' ) );
		$this->assertStringNotContainsString( '"', settagord_sanitize_class_list( 'a" onmouseover="x' ) );
	}

	public function test_class_setting_sanitization_runs_through_the_options_api() {
		update_option( 'settagord_class', 'tag' );

		$sanitized = apply_filters( 'sanitize_option_settagord_class', 'badge  <b>bold</b>', 'settagord_class', 'badge  <b>bold</b>' );

		$this->assertStringNotContainsString( '<', $sanitized );
	}

	public function test_settings_action_link_is_added_to_the_plugin_row() {
		$links = apply_filters(
			'plugin_action_links_' . plugin_basename( SETTAGORD_PLUGIN_FILE ),
			array( '<a href="#">Deactivate</a>' )
		);

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'page=settagord-settings', $links[0] );
		$this->assertStringContainsString( 'options-general.php', $links[0] );

		// Prepended, so it reads before Deactivate.
		$this->assertStringContainsString( 'Deactivate', $links[1] );
	}

	public function test_debug_logging_is_off_by_default_and_follows_the_option() {
		delete_option( 'settagord_debug_mode' );
		$this->assertFalse( settagord_debug_enabled() );

		update_option( 'settagord_debug_mode', true );
		$this->assertTrue( settagord_debug_enabled() );

		update_option( 'settagord_debug_mode', false );
		$this->assertFalse( settagord_debug_enabled() );
	}

	private function render_fields() {
		ob_start();
		do_settings_sections( 'settagord-settings' );

		return ob_get_clean();
	}

	public function test_settings_sections_and_fields_render() {
		$html = $this->render_fields();

		$this->assertStringContainsString( 'Display Settings', $html );
		$this->assertStringContainsString( 'Advanced Settings', $html );
		$this->assertStringContainsString( 'name="settagord_separator"', $html );
		$this->assertStringContainsString( 'name="settagord_class"', $html );
		$this->assertStringContainsString( 'name="settagord_debug_mode"', $html );
	}

	public function test_field_values_are_escaped_for_attribute_context() {
		update_option( 'settagord_separator', '" onfocus="alert(1)' );
		update_option( 'settagord_class', '"><script>x</script>' );

		$html = $this->render_fields();

		$this->assertStringNotContainsString( 'onfocus="alert(1)"', $html );
		$this->assertStringNotContainsString( '<script>x</script>', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	public function test_debug_checkbox_reflects_the_stored_value() {
		update_option( 'settagord_debug_mode', true );
		$this->assertStringContainsString( "checked='checked'", $this->render_fields() );

		update_option( 'settagord_debug_mode', false );
		$this->assertStringNotContainsString( "checked='checked'", $this->render_fields() );
	}

	public function test_settings_page_renders_for_an_administrator() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'options-general' );

		// WordPress sets $title before rendering an admin page;
		// get_admin_page_title() falls back to inspecting the menu globals
		// without it, which warns when they are absent.
		$GLOBALS['title']       = 'Set Tag Order Settings';
		$GLOBALS['plugin_page'] = 'settagord-settings';

		ob_start();
		settagord_render_settings_page();
		$html = ob_get_clean();

		unset( $GLOBALS['title'], $GLOBALS['plugin_page'] );

		$this->assertStringContainsString( '<form action="options.php" method="post">', $html );
		$this->assertStringContainsString( 'settagord_settings', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	public function test_settings_page_renders_nothing_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		settagord_render_settings_page();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_settings_page_is_added_under_the_options_menu() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		settagord_register_settings_page();

		global $submenu;

		$slugs = wp_list_pluck( (array) ( $submenu['options-general.php'] ?? array() ), 2 );

		$this->assertContains( 'settagord-settings', $slugs );
	}

	public function test_plugin_version_constants_match_the_header() {
		$header = get_file_data( SETTAGORD_PLUGIN_FILE, array( 'Version' => 'Version' ) );

		$this->assertSame( $header['Version'], SETTAGORD_VERSION );
	}
}
