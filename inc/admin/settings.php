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
		'tag-order-settings',
		'render_tag_order_settings'
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
	register_setting( 'tag_order_settings', 'tag_order_separator', [
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field'
	] );

	register_setting( 'tag_order_settings', 'tag_order_class', [
		'type'              => 'string',
		'default'           => 'tag',
		'sanitize_callback' => 'sanitize_text_field'
	] );

	register_setting( 'tag_order_settings', 'tag_order_debug_mode', [
		'type'              => 'boolean',
		'default'           => false,
		'sanitize_callback' => 'rest_sanitize_boolean'
	] );

	// Display settings section
	add_settings_section(
		'tag_order_main_section',
		'Display Settings',
		function () {
			echo '<p>Customize how your ordered tags are displayed on your site.</p>';
		},
		'tag_order_settings'
	);

	// Tag separator field
	add_settings_field(
		'tag_separator',
		'Tag Separator',
		function () {
			$separator = get_option( 'tag_order_separator', '' );
			echo '<input type="text" name="tag_order_separator" value="' . esc_attr( $separator ) . '" class="regular-text" />';
			echo '<p class="description">Enter a character to separate tags (leave empty for no separator)</p>';
		},
		'tag_order_settings',
		'tag_order_main_section'
	);

	// Tag CSS class field
	add_settings_field(
		'tag_class',
		'Tag CSS Class',
		function () {
			$class = get_option( 'tag_order_class', 'tag' );
			echo '<input type="text" name="tag_order_class" value="' . esc_attr( $class ) . '" class="regular-text" />';
			echo '<p class="description">Enter CSS class(es) for tag links (separate multiple classes with spaces)</p>';
		},
		'tag_order_settings',
		'tag_order_main_section'
	);

	// Advanced settings section
	add_settings_section(
		'tag_order_advanced_section',
		'Advanced Settings',
		function () {
			echo '<p>These settings are intended for development and troubleshooting purposes.</p>';
		},
		'tag_order_settings'
	);

	// Debug mode field
	add_settings_field(
		'debug_mode',
		'Debug Mode',
		function () {
			$debug_mode = get_option( 'tag_order_debug_mode', false );
			echo '<label for="tag_order_debug_mode">';
			echo '<input type="checkbox" id="tag_order_debug_mode" name="tag_order_debug_mode" value="1" ' . checked( $debug_mode, true, false ) . ' />';
			echo ' Enable debug logging';
			echo '</label>';
			echo '<p class="description">When enabled, diagnostic information will be written to the error log. Use only for troubleshooting.</p>';
		},
		'tag_order_settings',
		'tag_order_advanced_section'
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
function render_tag_order_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
			<?php
			settings_fields( 'tag_order_settings' );
			do_settings_sections( 'tag_order_settings' );
			submit_button();
			?>
        </form>
    </div>
	<?php
}