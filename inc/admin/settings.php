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
function settagord_register_settings_page() {
	add_options_page(
		__( 'Set Tag Order Settings', 'set-tag-order' ),
		__( 'Set Tag Order', 'set-tag-order' ),
		'manage_options',
		'settagord-settings',
		'settagord_render_settings_page'
	);
}
add_action( 'admin_menu', 'settagord_register_settings_page' );

/**
 * Register settings and fields
 *
 * Creates all the settings fields and sections for the plugin options
 *
 * @since 1.0.0
 * @return void
 */
function settagord_register_settings() {
	// Register settings with validation callbacks
	register_setting( 'settagord_settings', 'settagord_separator', [
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field'
	] );

	register_setting( 'settagord_settings', 'settagord_class', [
		'type'              => 'string',
		'default'           => 'tag',
		'sanitize_callback' => 'settagord_sanitize_class_list'
	] );

	register_setting( 'settagord_settings', 'settagord_debug_mode', [
		'type'              => 'boolean',
		'default'           => false,
		'sanitize_callback' => 'rest_sanitize_boolean'
	] );

	// Display settings section
	add_settings_section(
		'settagord_main_section',
		__( 'Display Settings', 'set-tag-order' ),
		function () {
			echo '<p>' . esc_html__( 'Customize how your ordered tags are displayed on your site.', 'set-tag-order' ) . '</p>';
		},
		'settagord-settings'
	);

	// Tag separator field
	add_settings_field(
		'settagord_separator',
		__( 'Tag Separator', 'set-tag-order' ),
		function () {
			$separator = get_option( 'settagord_separator', '' );
			echo '<input type="text" id="settagord_separator" name="settagord_separator" value="' . esc_attr( $separator ) . '" class="regular-text" />';
			echo '<p class="description">' . esc_html__( 'Character placed between tags. Leave empty to keep whatever separator your theme already uses. Surrounding spaces are trimmed on save; spacing comes from CSS.', 'set-tag-order' ) . '</p>';
		},
		'settagord-settings',
		'settagord_main_section'
	);

	// Tag CSS class field
	add_settings_field(
		'settagord_class',
		__( 'Tag CSS Class', 'set-tag-order' ),
		function () {
			$class = get_option( 'settagord_class', 'tag' );
			echo '<input type="text" id="settagord_class" name="settagord_class" value="' . esc_attr( $class ) . '" class="regular-text" />';
			echo '<p class="description">' . esc_html__( 'CSS class or classes for tag links, separated by spaces. The default "tag" means no class is added, matching WordPress behaviour.', 'set-tag-order' ) . '</p>';
		},
		'settagord-settings',
		'settagord_main_section'
	);

	// Advanced settings section
	add_settings_section(
		'settagord_advanced_section',
		__( 'Advanced Settings', 'set-tag-order' ),
		function () {
			echo '<p>' . esc_html__( 'These settings are intended for development and troubleshooting purposes.', 'set-tag-order' ) . '</p>';
		},
		'settagord-settings'
	);

	// Debug mode field
	add_settings_field(
		'settagord_debug_mode',
		__( 'Debug Mode', 'set-tag-order' ),
		function () {
			$debug_mode = get_option( 'settagord_debug_mode', false );
			echo '<label for="settagord_debug_mode">';
			echo '<input type="checkbox" id="settagord_debug_mode" name="settagord_debug_mode" value="1" ' . checked( $debug_mode, true, false ) . ' />';
			echo ' ' . esc_html__( 'Enable debug logging', 'set-tag-order' );
			echo '</label>';
			echo '<p class="description">' . esc_html__( 'When enabled, diagnostic information is written to the error log. Use only for troubleshooting.', 'set-tag-order' ) . '</p>';
		},
		'settagord-settings',
		'settagord_advanced_section'
	);
}
add_action( 'admin_init', 'settagord_register_settings' );

/**
 * Sanitize the tag CSS class setting.
 *
 * sanitize_text_field() alone would accept characters that are not valid in a
 * class attribute. Each space-separated token is run through
 * sanitize_html_class() instead, and anything that sanitizes to nothing is
 * dropped rather than silently producing an empty class.
 *
 * @since 1.2.0
 * @param string $value Raw setting value.
 * @return string Space-separated list of valid class names.
 */
function settagord_sanitize_class_list( $value ) {
	$classes = preg_split( '/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );

	if ( ! $classes ) {
		return '';
	}

	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', array_unique( $classes ) );
}

/**
 * Add a Settings link to the plugin's row on the Plugins screen.
 *
 * @since 1.2.0
 * @param array $links Existing action links.
 * @return array Action links with the settings shortcut prepended.
 */
function settagord_add_settings_action_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=settagord-settings' ) ),
		esc_html__( 'Settings', 'set-tag-order' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SETTAGORD_PLUGIN_FILE ), 'settagord_add_settings_action_link' );

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