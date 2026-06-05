<?php
/**
 * Plugin Settings
 *
 * Handles the admin settings page and options for the Set Tag Order plugin.
 *
 * @package    SetTagOrder
 * @subpackage Admin
 * @author     Adam Greenwell
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register settings page
 *
 * Adds the plugin settings page to the WordPress admin menu
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_menu', function () {
	add_options_page(
		'Set Tag Order Settings',
		'Set Tag Order',
		'manage_options',
		'settagord-settings',
		'settagord_render_settings_page'
	);
} );

/**
 * Register settings and fields
 *
 * Creates all the settings fields and sections for the plugin options
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_init', function () {
	// Register settings with validation callbacks
	register_setting( 'settagord_settings', 'settagord_separator', [
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field'
	] );

	register_setting( 'settagord_settings', 'settagord_class', [
		'type'              => 'string',
		'default'           => 'tag',
		'sanitize_callback' => 'sanitize_text_field'
	] );

	register_setting( 'settagord_settings', 'settagord_debug_mode', [
		'type'              => 'boolean',
		'default'           => false,
		'sanitize_callback' => 'rest_sanitize_boolean'
	] );

	// Display settings section
	add_settings_section(
		'settagord_main_section',
		'Display Settings',
		function () {
			echo '<p>Customize how your ordered tags are displayed on your site.</p>';
		},
		'settagord-settings'
	);

	// Tag separator field
	add_settings_field(
		'settagord_separator',
		'Tag Separator',
		function () {
			$separator = get_option( 'settagord_separator', '' );
			echo '<input type="text" name="settagord_separator" value="' . esc_attr( $separator ) . '" class="regular-text" />';
			echo '<p class="description">Enter a character to separate tags (leave empty for no separator)</p>';
		},
		'settagord-settings',
		'settagord_main_section'
	);

	// Tag CSS class field
	add_settings_field(
		'settagord_class',
		'Tag CSS Class',
		function () {
			$class = get_option( 'settagord_class', 'tag' );
			echo '<input type="text" name="settagord_class" value="' . esc_attr( $class ) . '" class="regular-text" />';
			echo '<p class="description">Enter CSS class(es) for tag links (separate multiple classes with spaces)</p>';
		},
		'settagord-settings',
		'settagord_main_section'
	);

	// Advanced settings section
	add_settings_section(
		'settagord_advanced_section',
		'Advanced Settings',
		function () {
			echo '<p>These settings are intended for development and troubleshooting purposes.</p>';
		},
		'settagord-settings'
	);

	// Debug mode field
	add_settings_field(
		'settagord_debug_mode',
		'Debug Mode',
		function () {
			$debug_mode = get_option( 'settagord_debug_mode', false );
			echo '<label for="settagord_debug_mode">';
			echo '<input type="checkbox" id="settagord_debug_mode" name="settagord_debug_mode" value="1" ' . checked( $debug_mode, true, false ) . ' />';
			echo ' Enable debug logging';
			echo '</label>';
			echo '<p class="description">When enabled, diagnostic information will be written to the error log. Use only for troubleshooting.</p>';
		},
		'settagord-settings',
		'settagord_advanced_section'
	);
} );

/**
 * Render settings page
 *
 * Outputs the HTML for the plugin settings page
 *
 * @since 1.0.0
 * @return void
 */
function settagord_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
			<?php
			settings_fields( 'settagord_settings' );
			do_settings_sections( 'settagord-settings' );
			submit_button();
			?>
        </form>
    </div>
	<?php
}