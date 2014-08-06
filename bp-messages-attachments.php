<?php
/*
Plugin Name: BP Messages Attachments
Version: 0.1
Description: Attachments for BuddyPress messages
Author: Boone Gorges
Author URI: http://boone.gorg.es
Text Domain: bp-messages-attachments
Domain Path: /languages
*/

define( 'BPMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BPMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin loader.
 *
 * @since 0.1
 */
function bpma_include() {
	if ( ! bp_is_active( 'messages' ) ) {
		return;
	}

	require BPMA_PLUGIN_DIR . 'includes/bpma.php';
}
add_action( 'bp_include', 'bpma_include' );
