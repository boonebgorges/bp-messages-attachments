<?php

function bpma_enqueue_assets() {
	global $wp_scripts;

	if ( bp_is_messages_component() ) {
		// Locate BP's script so we can be dependent on it
		foreach ( $wp_scripts->registered as $script ) {
			if ( preg_match( '/^bp\-[^\-]+\-js$/', $script->handle ) ) {
				$handle = $script->handle;
				break;
			}
		}

		if ( empty( $handle ) ) {
			$handle = 'bp-legacy-js';
		}

//		wp_enqueue_media();
		wp_enqueue_script( 'bp-messages-attachments', BPMA_PLUGIN_URL . 'assets/js/bp-messages-attachments.js', array( $handle ) );
		wp_enqueue_style( 'bp-messages-attachments', BPMA_PLUGIN_URL . 'assets/css/bp-messages-attachments.css' );

		$settings = apply_filters( 'bpma_upload_settings', array(
			'file_types' => get_allowed_mime_types(),
			'max_size'   => wp_max_upload_size(),
		) );

		// Merge with normal translatable strings
		$max_size_display = floor( $settings['max_size'] / 1028 );
		$strings = array_merge( $settings, array(
			'bad_size' => sprintf( __( 'The following file exceeds the maximum upload size of %s M:', 'bp-messages-attachments' ), $max_size_display ),
			'bad_type' => __( 'The following files is not of a permitted file type:', 'bp-messages-attachments' ),
		) );

		wp_localize_script( 'bp-messages-attachments', 'BP_Messages_Attachments', $strings );
	}
}
add_action( 'bp_enqueue_scripts', 'bpma_enqueue_assets', 20 );

function bpma_add_attachment_markup() {

	?>
	<label for="bpma-attachments"><?php _e( 'Attachments', 'bp-messages-attachments' ); ?></label>

	<input type="file" name="bpma-attachments[]" id="bpma-attachments" multiple="" />
	<input type="hidden" name="bpma-attachments-validated" id="bpma-attachments-validated" />

	<div id="bpma-attachment-feedback">
		<ul id="bpma-attachment-errors"></ul>
		<ul id="bpma-attachment-details"></ul>
	</div>

	<?php /* <input type="button" name="bpma-attach-button" id="bpma-attach-button" value="<?php _e( 'Add Attachments', 'bp-messages-attachments' ) ?>" /> */ ?>
	<?php
}
add_action( 'bp_after_messages_compose_content', 'bpma_add_attachment_markup' );
add_action( 'bp_after_message_reply_box', 'bpma_add_attachment_markup' );

/**
 * Markup for display under messages
 */
function bpma_display_attachment_markup() {
	$attachments = bpma_get_message_attachments( bp_get_the_thread_message_id() );

	if ( ! empty( $attachments ) ) {
	?>
		<div class="message-attachments">
			<ul>
			<?php foreach ( $attachments as $attachment ) : ?>
				<li>
					<img src="<?php echo esc_attr( $attachment->mime_type_icon ) ?>" />
					<a href="<?php echo esc_attr( $attachment->guid ) ?>"><?php echo esc_html( basename( $attachment->guid ) ) ?></a>
				</li>
			<?php endforeach ?>
			</ul>
		</div>
	<?php
	}
}
add_action( 'bp_after_message_content', 'bpma_display_attachment_markup' );

/**
 * Get attachments for a message.
 */
function bpma_get_message_attachments( $message_id ) {
	$atts_query = new WP_Query( array(
		'posts_per_page'         => -1,
		'update_post_term_cache' => false,
		'post_type'              => 'attachment',
		'post_status'            => 'inherit',
		'post_parent'            => 0,
		'meta_query'             => array(
			array(
				'key'   => 'bp_message_id',
				'value' => $message_id,
			),
		),
	) );

	$atts = $atts_query->posts;

	foreach ( $atts as &$att ) {
		$att->file_path = get_post_meta( $att->ID, '_wp_attached_file', true );

		if ( ! empty( $att->post_mime_type ) ) {
			$att->mime_type_icon = wp_mime_type_icon( $att->post_mime_type );
		}
	}

	return $atts_query->posts;
}

function bpma_handle_upload( $message ) {
	if ( empty( $_FILES['bpma-attachments'] ) ) {
		return;
	}

	// Parse into separate objects that can be passed to wp_handle_upload()
	$files = array();

	foreach ( $_FILES['bpma-attachments'] as $fd_key => $fd_values ) {
		foreach ( $fd_values as $i => $fd_value ) {
			if ( ! isset( $files[ $i ] ) ) {
				$files[ $i ] = array();
			}

			$files[ $i ][ $fd_key ] = $fd_value;
		}
	}

	// Stash $message in the buddypress() object for reference in the
	// bpma_upload_dir() filter
	buddypress()->bpma_message = $message;

	// wp_handle_upload() requires the admin
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	add_filter( 'upload_dir', 'bpma_upload_dir' );
	foreach ( $files as $file ) {
		$overrides = array(
			'test_form' => false,
		);
		$upload = wp_handle_upload( $file, $overrides );

		$attachment_id = bpma_create_message_attachment( $message, $upload );
	}
	remove_filter( 'upload_dir', 'bpma_upload_dir' );

	// Don't clean up - we use this later when filtering notification email
	//unset( buddypress()->bpma_message );
}
add_action( 'messages_message_sent', 'bpma_handle_upload', 5 ); // Early to precede notifications

/**
 * Filter the upload directory for message attachments
 *
 * Format: [basedir]/bp-attachments/messages/[message_id]
 */
function bpma_upload_dir( $uploads ) {
	if ( empty( buddypress()->bpma_message ) ) {
		return $uploads;
	}

	$message = buddypress()->bpma_message;

	if ( ! is_a( $message, 'BP_Messages_Message' ) ) {
		return $uploads;
	}

	$message_id = $message->id;

	$messages_parent_dir = $uploads['basedir'] . '/bp-attachments/messages';

	if ( ! file_exists( $messages_parent_dir ) ) {
		wp_mkdir_p( $messages_parent_dir );
		bpma_create_htaccess_file( $messages_parent_dir );
	}

	$message_att_dir = $messages_parent_dir . '/' . $message_id;

	if ( ! file_exists( $message_att_dir ) ) {
		wp_mkdir_p( $message_att_dir );
	}

	$uploads['path'] = $message_att_dir;
	$uploads['url']  = $uploads['baseurl'] . '/bp-attachments/messages/' . $message_id;

	return $uploads;
}

/**
 * Generate the attachment post for the uploaded item.
 *
 * Largely copied from media_handle_upload(), which is only available in the
 * admin, and which is overdependent on the specifics of the $_POST data.
 */
function bpma_create_message_attachment( BP_Messages_Message $message, $upload ) {

	// Support filenames with dots
	$name_parts = pathinfo( $upload['url'] );
	$name = trim( substr( basename( $upload['url'] ), 0, -( 1 + strlen( $name_parts['extension'] ) ) ) );

	// Construct the attachment array
	$attachment = array(
		'post_mime_type' => $upload['type'],
		'guid'           => $upload['url'],
		'post_parent'    => 0,
		'post_title'     => $name,
		'post_content'   => '',
	);

	// Save the data
	$id = wp_insert_attachment( $attachment, $upload['file'], 0 );
	if ( ! is_wp_error( $id ) ) {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . '/wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload ) );

		update_post_meta( $id, 'bp_message_id', $message->id );
	}

	return $id;
}

/**
 * Only support Apache for now
 */
function bpma_create_htaccess_file( $dir ) {
	if ( ! file_exists( 'insert_with_markers' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/misc.php' );
	}

	$site_url = parse_url( site_url() );
	$path = ( !empty( $site_url['path'] ) ) ? $site_url['path'] : '/';

	$rules = array(
		'RewriteEngine On',
		'RewriteBase ' . $path,
		'RewriteRule (.+) ?bp-messages-attachment=$1 [R=302,NC]',
	);

	insert_with_markers( trailingslashit( $dir ) . '.htaccess', 'BP Messages Attachments', $rules );
}

/**
 * Catch bp-messages-attachment requests and serve files.
 */
function bpma_serve_attachments() {
	if ( empty( $_GET['bp-messages-attachment'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		bp_core_no_access();
		die();
	}

	$att = urldecode( $_GET['bp-messages-attachment'] );

	$uploads  = wp_upload_dir();
	$att_path = $uploads['basedir'] . '/bp-attachments/messages/' . $att;

	if ( ! file_exists( $att_path ) ) {
		bp_core_add_message( __( 'Sorry, that file could not be found.', 'bp-messages-attachments' ), 'error' );
		bp_core_redirect( wp_get_referer() );
		die();
	}

	$att_data   = explode( '/', $att );
	$message_id = intval( $att_data[0] );

	if ( ! $message_id ) {
		return;
	}

	$message = new BP_Messages_Message( $message_id );

	if ( empty( $message->thread_id ) ) {
		return;
	}

	$thread = new BP_Messages_Thread( $message->thread_id );

	$recipient_ids = wp_list_pluck( $thread->recipients, 'user_id' );

	if ( ! in_array( bp_loggedin_user_id(), $recipient_ids ) && ! is_super_admin() ) {
		bp_core_add_message( __( 'You do not have access to that attachment.', 'bp-messages-attachments' ), 'error' );
		bp_core_redirect( bp_loggedin_user_domain() . bp_get_messages_slug() . '/' );
		return;
	}

	// We've made it this far - serve the file
	// From ms-files.php

	if ( ! defined( 'WPMU_SENDFILE' ) ) {
		define( 'WPMU_SENDFILE', false );
	}

	if ( ! defined( 'WPMU_ACCEL_REDIRECT' ) ) {
		define( 'WPMU_ACCEL_REDIRECT', false );
	}

	error_reporting( 0 );

	$mime = wp_check_filetype( $att_path );
	if( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
		$mime[ 'type' ] = mime_content_type( $att_path );

	if( $mime[ 'type' ] )
		$mimetype = $mime[ 'type' ];
	else
		$mimetype = 'image/' . substr( $att_path, strrpos( $att_path, '.' ) + 1 );

	header( 'Content-Type: ' . $mimetype ); // always send this
	if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
		header( 'Content-Length: ' . filesize( $att_path ) );

	// Optional support for X-Sendfile and X-Accel-Redirect
	if ( WPMU_ACCEL_REDIRECT ) {
		header( 'X-Accel-Redirect: ' . str_replace( WP_CONTENT_DIR, '', $att_path ) );
		exit;
	} elseif ( WPMU_SENDFILE ) {
		header( 'X-Sendfile: ' . $att_path );
		exit;
	}

	$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $att_path ) );
	$etag = '"' . md5( $last_modified ) . '"';
	header( "Last-Modified: $last_modified GMT" );
	header( 'ETag: ' . $etag );
	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

	// Support for Conditional GET - use stripslashes to avoid formatting.php dependency
	$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

	if( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;

	$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	// If string is empty, return 0. If not, attempt to parse into a timestamp
	$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

	// Make a timestamp for our most recent modification...
	$modified_timestamp = strtotime($last_modified);

	if ( ( $client_last_modified && $client_etag )
		? ( ( $client_modified_timestamp >= $modified_timestamp) && ( $client_etag == $etag ) )
		: ( ( $client_modified_timestamp >= $modified_timestamp) || ( $client_etag == $etag ) )
		) {
		status_header( 304 );
		exit;
	}

	// If we made it this far, just serve the file
	readfile( $att_path );
	exit;
}
add_action( 'bp_actions', 'bpma_serve_attachments' );

/**
 * Add attachment message to notification email.
 */
function bpma_notification_filter( $email_content, $sender_name, $subject, $content, $message_link ) {
	if ( empty( buddypress()->bpma_message ) ) {
		return $email_content;
	}

	$attachments = bpma_get_message_attachments( buddypress()->bpma_message->id );

	if ( ! empty( $attachments ) ) {
		$att_links = array();
		foreach ( $attachments as $attachment ) {
			$att_links[] = '- ' . $attachment->guid . "\n";
		}
		$att_links_text = sprintf( __( "This message has attachments: \n%s", 'bp-messages-attachments' ), implode( '', $att_links ) );

		// Add the line before the "To view and read..." link
		$search = '|^(.*?' . $message_link . '.*?)$|m';
		$email_content = preg_replace( $search, $att_links_text . "\n" . '\1', $email_content );
	}

	return $email_content;
}
add_filter( 'messages_notification_new_message_message', 'bpma_notification_filter', 10, 5 );
