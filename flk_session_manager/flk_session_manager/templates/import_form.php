<?php
/**
 * @name: import_form.php
 * 
 * @desc Form to upload session attendance in XLSX format
 * 
 * @author: Ivan Kubica
 * 
 * @var integer $session_id - global variable for selected event
 * @var boolean $import_result - global variable import result
 */
?>
<h1 class="wp-heading-inline">
	<?php esc_html_e( 'Import session registration', FLK_PLUGIN_TEXTDOMAIN ); ?>
</h1>
<?php 
$import_result = get_transient( 'flk_session_import_result' );

if( $import_result == 'success' ){
    echo '<div class="row notice updated">Session registration was imported successfully</div>';
} elseif( $import_result == 'error' ) {
    $errors = unserialize( get_transient( 'flk_session_import_errors' ) ); 
    $output = '';
    foreach( $errors as $error){
        if( is_string( $error ) ){
            $output .= '<li>' . $error . '</li>';
        } else {
            foreach( $error as $i => $error_msg) {
                $output .= '<li>Line '. $i . ': ' . $error_msg.'</li>';
            }
        }
    }
    echo '<div class="row notice error"><ul>' . $output . '</ul></div>';
}?>
<div>
	<?php echo __('Select .xlsx file to import session attendance.') ?>
</div>
<form enctype="multipart/form-data" method="POST" action="admin-post.php">
	<input type="hidden" name="action" value="import_session_attendance">
	<?php 
	if( is_numeric( $session_id ) ){
	    $event = new FLK_Event( $session_id ); ?>
	    <input type="hidden" name="eid" id="eid" value="<?php echo $session_id ?>">
	    <div class="row">
	    	<span><?php echo __('Importing data to session', FLK_PLUGIN_TEXTDOMAIN ) ?>:</span>
	    	<a href="/wp-admin/post.php?post=<?php echo $event->post_id; ?>&action=edit"><?php echo $event->event_name ?></a>
	   	</div>
	<?php } else { ?>
		<div class="row">
			<label for="eid"><?php echo __('Select Session') ?></label>
			<span>
        		<select name="eid" id="eid">
    				<?php 
    				$events = getAvalableSessionOptions(); 
                    $current_location_id = null; 
                    $i = 0;
    
    				foreach( $events as $FLK_Event ){
    				    // ignore sessions where the session registration is turned off
    				    if( !$FLK_Event->getEnabledSessionRegistration() ){
    				        continue;
    				    }
    				    
    				    if( $current_location_id != $FLK_Event->location_id ){
    				        echo $i > 0 ? '</optgroup>' : null; 
    				        $i++;
    				        $current_location_id = $FLK_Event->location_id;
    
    				        echo '<optgroup label="' . $FLK_Event->getFlkLocation()->location_name . '">';
    				    }
				    
				    echo sprintf( '<option value="%s">%s</option>', $FLK_Event->event_id, $FLK_Event->event_name );
				    }
				    ?>
    			</select><br/>
    			<?php echo __('Listed are only sessions with enabled session registration.', FLK_PLUGIN_TEXTDOMAIN ) ?>
			</span>
		</div>
	<?php }?>
    <div class="row">
    	<label for="session_attendance"><?php echo __('Select file to import') ?></label>
        <input required type="file" id="session_attendance" name="session_attendance" accept=".xlsx">
    </div>
    <?php submit_button(__('Import', FLK_PLUGIN_TEXTDOMAIN) ); ?>
</form>
