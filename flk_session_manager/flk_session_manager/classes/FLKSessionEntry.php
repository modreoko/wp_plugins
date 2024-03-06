<?php

class FLKSessionEntry{
    protected static $location_post_id = null; 
    
    protected static $FLK_Location = null; 
    
    protected static $session_registration_status_field_id = null; 

    protected static $entry = null;

    /**
     * initialize GF Entry for given id
     * @param integer $id - entry id
     */
    public static function initEntry( $id ){
        if( self::$entry === null ){

            if( is_numeric( $id ) || self::$entry['id'] != $id ){
                self::$entry = GFAPI::get_entry( $id );
            } elseif( is_array( $id ) ){
                self::$entry = $id;
            }
        }
    }

    /**
     * returns GF Entry
     * @return array|WP_Error
     */
    public static function getEntry(){
        return self::$entry;
    }

    /**
     * stores session registration status field id
     * @param integer $id
     */
    public static function setSessionRegistrationStatusFieldId( $id ){
        self::$session_registration_status_field_id = $id;
    }

    /**
     * stores location post ID
     * @param integer $location_post_id
     */
    public static function setLocationPostId( $location_post_id ){
        self::$location_post_id = $location_post_id;
    }

    /**
     * returns FLK_Location for current entry
     * @return NULL|FLK_Location
     */
    public static function getFlkLocation(){
        if( self::$FLK_Location === null && self::$location_post_id === null ){
            return null; 
        }
        
        if( self::$FLK_Location === null ){
            self::$FLK_Location = new FLK_Location( self::$location_post_id, 'post_id' );
            self::$FLK_Location->setSessionRegistrationStatusFieldId( self::$session_registration_status_field_id );
        }
        
        return self::$FLK_Location;
    }

    public static function setFlkLocation( $location ){
        self::$FLK_Location = $location;
    }
    
    /**
     * modifies default meta boxes on entry screen
     * @param array $args
     * @param string $metabox
     */
    public static function display_meta_box_details( $args, $metabox ){
        $form  = $args['form'];
        $entry = $args['entry'];
        $mode  = $args['mode'];
        ?>
        <div id="submitcomment" class="submitbox">
        <div id="minor-publishing" style="padding:10px;">
        <?php esc_html_e( 'Entry Id', 'gravityforms' ); ?>: <?php echo absint( $entry['id'] ) ?><br /><br />
				<?php esc_html_e( 'Submitted on', 'gravityforms' ); ?>: <?php echo esc_html( GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) ) ?>
				<br /><br />
				<?php
				if ( ! empty( $entry['date_updated'] ) && $entry['date_updated'] != $entry['date_created'] ) {
					esc_html_e( 'Updated', 'gravityforms' ); ?>: <?php echo esc_html( GFCommon::format_date( $entry['date_updated'], false, 'Y/m/d' ) );
					echo '<br /><br />';
				}

				if ( ! empty( $entry['ip'] ) ) {
					esc_html_e( 'User IP', 'gravityforms' ); ?>: <?php echo esc_html( $entry['ip'] );
					echo '<br /><br />';
				}

				if ( ! empty( $entry['created_by'] ) && $usermeta = get_userdata( $entry['created_by'] ) ) {
					?>
					<?php esc_html_e( 'User', 'gravityforms' ); ?>:
					<a href="user-edit.php?user_id=<?php echo absint( $entry['created_by'] ) ?>"><?php echo esc_html( $usermeta->user_login ) ?></a>
					<br /><br />
					<?php
				}

				esc_html_e( 'Embed Url', 'gravityforms' ); ?>:
				<a href="<?php echo esc_url( $entry['source_url'] ) ?>" target="_blank">.../<?php echo esc_html( GFCommon::truncate_url( $entry['source_url'] ) ) ?></a>
				<br /><br />
				<?php
				if ( ! empty( $entry['post_id'] ) ) {
					$post = get_post( $entry['post_id'] );
					?>
					<?php esc_html_e( 'Edit Post', 'gravityforms' ); ?>:
					<a href="post.php?action=edit&post=<?php echo absint( $post->ID ) ?>"><?php echo esc_html( $post->post_title ) ?></a>
					<br /><br />
					<?php
				}

				/**
				 * Adds additional information to the entry details
				 *
				 * @param int   $form['id'] The form ID
				 * @param array $lead       The Entry object
				 */
				do_action( 'gform_entry_info', $form['id'], $entry );

				?>
			</div>
			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					switch ( $entry['status'] ) {
						case 'spam' :
							?>
							<a onclick="jQuery('#action').val('unspam'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Not Spam', 'gravityforms' ) ?></a>
							<?php
							echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '';
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<a class="submitdelete deletion" onclick="if ( confirm('<?php echo esc_js( __( "You are about to delete this entry. 'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>') ) {jQuery('#action').val('delete'); jQuery('#entry_form').submit(); return true;} return false;" href="#"><?php esc_html_e( 'Delete Permanently', 'gravityforms' ) ?></a>
								<?php
							}

							break;

						case 'trash' :
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
							?>
								<a onclick="jQuery('#action').val('restore'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Restore', 'gravityforms' ) ?></a>
								|
								<a class="submitdelete deletion" onclick="if ( confirm('<?php echo esc_js( __( "You are about to delete this entry. 'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>') ) {jQuery('#action').val('delete'); jQuery('#entry_form').submit(); return true;} return false;" href="#"><?php esc_html_e( 'Delete Permanently', 'gravityforms' ) ?></a>
								<?php
							}

							break;

						default :
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<a class="submitdelete deletion" onclick="jQuery('#action').val('trash'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Move to Trash', 'gravityforms' ) ?></a>
								<?php
								echo GFCommon::spam_enabled( $form['id'] ) ? '|' : '';
							}
							if ( GFCommon::spam_enabled( $form['id'] ) ) {
								?>
								<a class="submitdelete deletion" onclick="jQuery('#action').val('spam'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Mark as Spam', 'gravityforms' ) ?></a>
								<?php
							}
					}

					?>
				</div>
				<div id="publishing-action">
					<?php
					if ( GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) && $entry['status'] != 'trash' ) {
						$button_text      = $mode == 'view' ? __( 'Edit', 'gravityforms' ) : __( 'Update', 'gravityforms' );
						$disabled         = $mode == 'view' ? '' : ' disabled="disabled" ';
						$update_button_id = $mode == 'view' ? 'gform_edit_button' : 'gform_update_button';
						$button_click     = $mode == 'view' ? "jQuery('#screen_mode').val('edit');" : "jQuery('#action').val('update'); jQuery('#screen_mode').val('view');";
						$update_button    = '<input id="' . $update_button_id . '" ' . $disabled . ' class="button button-large button-primary" type="submit" tabindex="4" value="' . esc_attr( $button_text ) . '" name="save" onclick="' . $button_click . '"/>';

						/**
						 * A filter to allow the modification of the button to update an entry detail
						 *
						 * @param string $update_button The HTML Rendered for the Entry Detail update button
						 */
						echo apply_filters( 'gform_entrydetail_update_button', $update_button );
						if ( $mode == 'edit' ) {
							echo '&nbsp;&nbsp;<input class="button button-large" type="submit" tabindex="5" value="' . esc_attr__( 'Cancel', 'gravityforms' ) . '" name="cancel" onclick="jQuery(\'#screen_mode\').val(\'view\');"/>';
						}
					}
					?>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
    }
}