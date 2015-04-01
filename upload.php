<?php
if ( ! current_user_can( 'upload_files' ) ) {
	wp_die( __( 'You do not have permission to upload files.', 'enable-media-replace' ) );
}

if ( ! ua_has_files_to_upload( 'userfile' ) ) {
	wp_die( __( 'Please select a file to upload', 'enable-media-replace' ), '', array( 'back_link' => true ) );
}

// Define DB table names
global $wpdb;
$table_name          = $wpdb->prefix . 'posts';
$postmeta_table_name = $wpdb->prefix . 'postmeta';

$current_attachment_data = get_post( absint( $_POST['ID'] ) );
$current_filetype = $current_attachment_data->post_mime_type;

// Massage a bunch of vars
$current_guid     = $current_attachment_data->guid;

$current_file     = get_attached_file( absint( $_POST['ID'] ), apply_filters( 'emr_unfiltered_get_attached_file', true ) );
$current_path     = pathinfo( $current_file, PATHINFO_DIRNAME );
$current_file     = str_replace( '//', '/', $current_file );
$current_filename = pathinfo( $current_file, PATHINFO_BASENAME );

$replace_type = sanitize_text_field( $_POST['replace_type'] );
// We have two types: replace / replace_and_search

if ( is_uploaded_file( $_FILES['userfile']['tmp_name'] ) ) {

	$form_fields = array( 'save' );

	if ( isset( $_POST['save'] ) ) {

		$form_url = add_query_arg( array( 'page' => 'enable-media-replace/enable-media-replace.php', 'noheader' => 'true', 'action' => 'media_replace_upload', 'attachment_id' => absint( $_POST['ID'] ) ), self_admin_url( 'upload.php' ) );

		if ( false === ( $creds = request_filesystem_credentials( $form_url, '', false, false, $form_fields ) ) ) {
			return true;
		}

		// now we have some credentials, try to get the wp_filesystem running
		if ( ! WP_Filesystem( $creds ) ) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials( $form_url, '', true, false, $form_fields );
			return true;
		}

		global $wp_filesystem;

		// New method for validating that the uploaded file is allowed, using WP:s internal wp_check_filetype_and_ext() function.
		$filedata = wp_check_filetype_and_ext( $_FILES['userfile']['tmp_name'], $_FILES['userfile']['name'] );

		if ( $filedata['ext'] == '' ) {
			esc_html_e( 'File type does not meet security guidelines. Try another.', 'enable-media-replace' );
			exit;
		}

		$new_filename = $_FILES['userfile']['name'];
		$new_filesize = $_FILES['userfile']['size'];
		$new_filetype = $filedata['type'];

		// save original file permissions
		$original_file_perms = $wp_filesystem->gethchmod( $current_file );

		if ( $replace_type == 'replace' ) {
			// Drop-in replace and we don't even care if you uploaded something that is the wrong file-type.
			// That's your own fault, because we warned you!

			emr_delete_current_files( $current_file );

			// Move new file to old location/name
			move_uploaded_file( $_FILES['userfile']['tmp_name'], $current_file );

			// Chmod new file to original file permissions
			$wp_filesystem->chmod( $current_file, $original_file_perms );

			// Make thumb and/or update metadata
			wp_update_attachment_metadata( absint( $_POST['ID'] ), wp_generate_attachment_metadata( absint( $_POST['ID'] ), $current_file ) );

			// Trigger possible updates on CDN and other plugins
			update_attached_file( absint( $_POST['ID'] ), $current_file );
		} elseif ( 'replace_and_search' == $replace_type && apply_filters( 'emr_enable_replace_and_search', true ) ) {
			// Replace file, replace file name, update meta data, replace links pointing to old file name

			emr_delete_current_files( $current_file );

			// Massage new filename to adhere to WordPress standards
			$new_filename = wp_unique_filename( $current_path, $new_filename );

			// Move new file to old location, new name
			$new_file = $current_path . '/' . $new_filename;
			move_uploaded_file( $_FILES['userfile']['tmp_name'], $new_file );

			// Chmod new file to original file permissions
			$wp_filesystem->chmod( $current_file, $original_file_perms );

			$new_filetitle = apply_filters( 'enable_media_replace_title', preg_replace( '/\.[^.]+$/', '', wp_basename( $new_file ) ) ); // Thanks Jonas Lundman (http://wordpress.org/support/topic/add-filter-hook-suggestion-to)

			// Keep current post title if it was already set or not different.
			if ( ! empty( $current_attachment_data->post_title ) && ( $current_attachment_data->post_title !== $new_filetitle ) ) {
				$new_filetitle = $current_attachment_data->post_title;
			}

			$new_guid = str_replace( $current_filename, $new_filename, $current_guid );

			// Update database file name
			$post_data = array(
				'ID' => absint( $_POST['ID'] ),
				'post_title' => $new_filetitle,
				'post_name' => $new_filetitle,
				'post_mime_type' => $new_filetype
			);

			$ret = wp_update_post( $post_data );

			if ( ! is_wp_error( $ret ) ) {
				$updated = $wpdb->update( $wpdb->posts, array( 'guid' => $new_guid ), array( 'ID' => $ret ) );
			}

			$current_file_meta = get_post_meta( absint( $_POST['ID'] ), '_wp_attached_file', true );
			$new_meta_name = str_replace( $current_filename, $new_filename, $current_file_meta );
			update_post_meta( absint( $_POST['ID'] ), '_wp_attached_file', $new_meta_name );

			// Make thumb and/or update metadata
			wp_update_attachment_metadata( absint( $_POST['ID'] ), wp_generate_attachment_metadata( absint( $_POST['ID'] ), $new_file ) );

			// Search-and-replace filename in post database
			$sql = $wpdb->prepare(
				"SELECT ID, post_content FROM $table_name WHERE post_content LIKE %s;",
				'%' . $current_guid . '%'
			);

			$rs = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $rs AS $rows ) {

				// replace old guid with new guid
				$post_content = $rows['post_content'];
				$post_content = addslashes( str_replace( $current_guid, $new_guid, $post_content ) );
				
				$updated = $wpdb->update( $wpdb->posts, array( 'post_content' => $post_content ), array( 'ID' => $rows['ID'] ) );
			}

			// Trigger possible updates on CDN and other plugins
			update_attached_file( absint( $_POST['ID'] ), $new_file );

		}

		$return_url = add_query_arg( array( 'post' => absint( $_POST['ID'] ), 'action' => 'edit', 'message' => 1 ), self_admin_url( 'post.php' ) );

		// Execute hook actions - thanks rubious for the suggestion!
		if ( isset( $new_guid ) ) {
			do_action( 'enable-media-replace-upload-done', ( $new_guid ? $new_guid : $current_guid ) );

			wp_redirect( $return_url );
			exit;
		}
	}
} else {

	//Reload the previous screen
	wp_safe_redirect( wp_get_referer() );
	exit;
}

function ua_has_files_to_upload( $id ) {
	return ( ! empty( $_FILES ) ) && isset( $_FILES[ $id ] );
}

function emr_delete_current_files( $current_file ) {

	global $wp_filesystem;

	// Delete old file

	// Find path of current file
	$current_path = substr( $current_file, 0, ( strrpos( $current_file, '/' ) ) );

	// Check if old file exists first
	if ( file_exists( $current_file ) ) {
		$wp_filesystem->delete( $current_file );
	}

	// Delete old resized versions if this was an image
	$suffix = substr( $current_file, ( strlen( $current_file ) - 4 ) );
	$prefix = substr( $current_file, 0, ( strlen( $current_file ) - 4 ) );
	$imgAr  = array( '.png', '.gif', '.jpg' );
	if ( in_array( $suffix, $imgAr ) ) {
		// It's a png/gif/jpg based on file name
		// Get thumbnail filenames from metadata
		$metadata = wp_get_attachment_metadata( absint( $_POST['ID'] ) );
		if ( is_array( $metadata ) ) { // Added fix for error messages when there is no metadata (but WHY would there not be? I don't know…)
			foreach ( $metadata['sizes'] AS $thissize ) {
				// Get all filenames and do an unlink() on each one;
				$thisfile = $thissize['file'];
				// Create array with all old sizes for replacing in posts later
				$oldfilesAr[] = $thisfile;
				// Look for files and delete them
				if ( strlen( $thisfile ) ) {
					$thisfile = $current_path . '/' . $thissize['file'];
					if ( file_exists( $thisfile ) ) {
						$wp_filesystem->delete( $thisfile );
					}
				}
			}
		}
		// Old (brutal) method, left here for now
		//$mask = $prefix . "-*x*" . $suffix;
		//array_map( "unlink", glob( $mask ) );
	}

}
