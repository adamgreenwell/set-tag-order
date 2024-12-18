<?php

/*
* File Name: set-tag-order.php
*/

// Register settings page
add_action( 'admin_menu', function () {
	add_options_page(
		'Tag Order Settings',
		'Tag Order',
		'manage_options',
		'tag-order-settings',
		'render_tag_order_settings'
	);
} );

// Register settings
add_action( 'admin_init', function () {
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

	add_settings_section(
		'tag_order_main_section',
		'Display Settings',
		function () {
			echo '<p>Customize how your ordered tags are displayed on your site.</p>';
		},
		'tag_order_settings'
	);

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
} );

// Render settings page
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