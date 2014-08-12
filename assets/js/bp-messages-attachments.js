window.wp = window.wp || {};

(function($){
	var file_mime_type,
		file_name,
		file_results,
		file_size_display,
		error_files = [],
		validation_result,
		validation_results = [],
		$attachment_details,
		$attachment_errors,
		$attachment_input,
		$file_el;

	$( document ).ready( function() {
		$attachment_details = $( '#bpma-attachment-details' );
		$attachment_errors = $( '#bpma-attachment-errors' );
		$attachment_input = $( '#bpma-attachments' );

		// Lame
		// Don't send reply via AJAX
		$( '#send_reply_button' ).unbind( 'click' );

		// Lamer
		// Add enctype to reply form
		$( 'form#send-reply' ).attr( 'enctype', 'multipart/form-data' );

		// Check file details after attachment
		$attachment_input.on( 'change', function() {
			error_files = validate_attachments( this.files );
			if ( error_files.length ) {
				// Unset the item and clear the successful attachment details
				$attachment_input.wrap( '<form>' ).closest( 'form' ).get(0).reset();
				$attachment_details.empty();
			}
			return;
		} );
	} );

	function validate_attachments( files ) {
		error_files = [];
		$.each( files, function( k, file ) {
			validation_result = validate_file( file );

			file_size_display = ( Math.round( file.size / 1028 ) ) + 'M';
			file_name_display = '<code class="bpma-file-name">' + file.name + ' (' + file_size_display + ')</code>';

			switch ( validation_result.status ) {
				case 'success' :
					$attachment_details.append( '<li>' + file_name_display + '</li>' );
					break;

				case 'bad_type' :
					$attachment_errors.append( '<li>' + BP_Messages_Attachments.bad_type + ' ' + file_name_display + '</li>' );
					error_files.push( file );
					break;
				
				case 'bad_size' :
					$attachment_errors.append( '<li>' + BP_Messages_Attachments.bad_size + ' ' + file_name_display + '</li>' );
					error_files.push( file );
					break;
					
			}
		} );

		if ( $attachment_errors.find( 'li' ).length ) {
			$attachment_errors.show();
		} else {
			$attachment_errors.hide();
		}

		return error_files;
	}

	function validate_file( file ) {
		file_results = {};
		file_results.status = 'error';
		file_results.size = '';
		file_results.name = '';
		file_results.type = '';

		if ( typeof file.type !== 'undefined' ) {
			$.each( BP_Messages_Attachments.file_types, function( k, type ) {
				if ( type == file.type ) {
					file_results.type = file.type;
					return false;
				}
			} );

			if ( file_results.type.length ) {
				file_results.status = 'success';
			} else {
				file_results.status = 'bad_type';
			}
		}

		if ( typeof file.size !== 'undefined' && 'success' === file_results.status ) {
			file_results.size = file.size;
			if ( file.size <= BP_Messages_Attachments.max_size ) {
				file_results.status = 'success';
			} else {
				file_results.status = 'bad_size';
			}
		}

		if ( typeof file.name.length !== 'undefined' ) {
			file_results.name = file.name;
		}

		return file_results;
	}

	// Gah
	return;

}(jQuery));
