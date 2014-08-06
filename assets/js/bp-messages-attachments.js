window.wp = window.wp || {};

(function($){
	$( document ).ready( function() {
		// Lame
		// Don't send reply via AJAX
		$( '#send_reply_button' ).unbind( 'click' );

		// Lamer
		// Add enctype to reply form
		$( 'form#send-reply' ).attr( 'enctype', 'multipart/form-data' );
	} );

	// Gah
	return;

	var file_frame, BP_Docs_MediaFrame, Library;

	$( document ).ready( function() {
		file_frame = new BP_Docs_MediaFrame({
			title: 'Foo',
//			title: bp_docs_attachments.upload_title,
			button: {
				text: 'Bar',
//				text: bp_docs_attachments.upload_button,
			},
			multiple: false
		});

		Library = file_frame.states.get('library');

		$( '#bpma-attach-button' ).on( 'click', function( e ) {
			// Change to upload mode
			Library.frame.content.mode( 'upload' );

			// Open the dialog
			file_frame.open();
		} );
	} )

	// Extension of the WP media view for our use
	BP_Docs_MediaFrame = wp.media.view.MediaFrame.Select.extend({
		browseRouter: function( view ) {
			view.set({
				upload: {
					text:     wp.media.view.l10n.uploadFilesTitle,
					priority: 20
				}
			});
		}
	});


}(jQuery));
