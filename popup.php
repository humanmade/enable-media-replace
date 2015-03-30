<?php
/**
 * Uploadscreen for selecting and uploading new media file
 *
 * @author      Måns Jonasson  <http://www.mansjonasson.se>
 * @copyright   Måns Jonasson 13 sep 2010
 * @version     $Revision: 2303 $ | $Date: 2010-09-13 11:12:35 +0200 (ma, 13 sep 2010) $
 * @package     wordpress
 * @subpackage  enable-media-replace
 *
 */

if ( ! current_user_can( 'upload_files' ) ) {
	wp_die( __( 'You do not have permission to upload files.', 'enable-media-replace' ) );
}

global $wpdb;

$table_name = $wpdb->prefix . 'posts';

$attachment_id = absint( $_GET['attachment_id'] );

$sql = $wpdb->prepare( "SELECT guid, post_mime_type FROM $table_name WHERE ID = %d", $attachment_id );

list( $current_filename, $current_filetype ) = $wpdb->get_row( $sql, ARRAY_N );

$current_filename = substr( $current_filename, ( strrpos( $current_filename, '/' ) + 1 ) );

?>
<div class="wrap">
	<div id="icon-upload" class="icon32"><br/></div>
	<h2><?php echo esc_html__( 'Replace Media Upload', 'enable-media-replace' ); ?></h2>

	<?php
	$url     = admin_url( 'upload.php?page=enable-media-replace/enable-media-replace.php&noheader=true&action=media_replace_upload&attachment_id=' . $attachment_id );
	$action  = 'media_replace_upload';
	$formurl = wp_nonce_url( $url, $action );
	if ( FORCE_SSL_ADMIN ) {
		$formurl = str_replace( 'http:', 'https:', $formurl );
	}
	?>

	<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( $formurl ); ?>">
		<?php
		#wp_nonce_field('enable-media-replace');
		?>
		<input type="hidden" name="ID" value="<?php echo esc_attr( $attachment_id ); ?>"/>

		<div id="message" class="updated fade">
			<p><?php esc_html_e( 'NOTE: You are about to replace the media file', 'enable-media-replace' ); ?>
				"<?php echo esc_html( $current_filename ); ?>
				". <?php esc_html_e( 'There is no undo. Think about it!', 'enable-media-replace' ); ?></p></div>

		<p><?php esc_html_e( 'Choose a file to upload from your computer', 'enable-media-replace' ); ?></p>

		<input type="file" name="userfile"/>

		<?php do_action( 'emr_before_replace_type_options' ); ?>

		<?php if ( apply_filters( 'emr_display_replace_type_options', true ) ) : ?>
			<p><?php esc_html_e( 'Select media replacement type:', 'enable-media-replace' ); ?></p>

			<label for="replace_type_1"><input CHECKED id="replace_type_1" type="radio" name="replace_type" value="replace"> <?php esc_html_e( 'Just replace the file', 'enable-media-replace' ); ?>
			</label>
			<p class="howto"><?php esc_html_e( 'Note: This option requires you to upload a file of the same type (', 'enable-media-replace' ); ?><?php esc_html_e( $current_filetype ); ?><?php esc_html_e( ') as the one you are replacing. The name of the attachment will stay the same (', 'enable-media-replace' ); ?><?php echo esc_html( $current_filename ); ?><?php esc_html_e( ') no matter what the file you upload is called.', 'enable-media-replace' ); ?></p>

			<?php if ( apply_filters( 'emr_enable_replace_and_search', true ) ) : ?>
				<label for="replace_type_2"><input id="replace_type_2" type="radio" name="replace_type" value="replace_and_search"> <?php esc_html_e( 'Replace the file, use new file name and update all links', 'enable-media-replace' ); ?>
				</label>
				<p class="howto"><?php esc_html_e( 'Note: If you check this option, the name and type of the file you are about to upload will replace the old file. All links pointing to the current file (', 'enable-media-replace' ); ?><?php echo esc_html( $current_filename ); ?><?php esc_html_e( ') will be updated to point to the new file name.', 'enable-media-replace' ); ?></p>
				<p class="howto"><?php esc_html_e( 'Please note that if you upload a new image, only embeds/links of the original size image will be replaced in your posts.', 'enable-media-replace' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<input type="hidden" name="replace_type" value="replace"/>
		<?php endif; ?>
		<input type="submit" class="button" value="<?php esc_html_e( 'Upload', 'enable-media-replace' ); ?>"/>
		<a href="#" onclick="history.back();"><?php esc_html_e( 'Cancel', 'enable-media-replace' ); ?></a>
	</form>
</div>
