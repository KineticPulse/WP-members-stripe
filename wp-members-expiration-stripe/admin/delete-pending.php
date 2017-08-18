<?php
/**
 * WP-Members Subscription Extension Functions
 *
 * Functions to purge and export pending users.
 * 
 * This file is part of the WP-Members plugin by Chad Butler
 * You can find out more about this plugin at http://rocketgeek.com
 * Copyright (c) 2006-2014  Chad Butler
 * WP-Members(tm) is a trademark of butlerblog.com
 *
 * @package WordPress
 * @subpackage WP-Members
 * @author Chad Butler
 * @copyright 2006-2014
 */


/**
 * Purge the pending users
 *
 * @since 0.3
 *
 * @param int  $num_days Deletes users older than $num_days
 * @param bool $export   If true, exports purged users as a CSV, false by default
 * @param bool $inc_all  If true, includes all pending users in export file, if false, just the users deleted, false by default
 */
 
function wpmem_exp_purge_pending( $num_days = 7, $export = false, $inc_all = false )
{
	$p = 0;
	if( $export ){
		wpmem_exp_purge_export( $num_days, $inc_all );
		return "$p " . __( 'pending users were exported and purged', 'wp-members' );
	} else {
		$args = array( 'meta_value' => 'pending' );
		$user_arr = get_users( $args );
		foreach( $user_arr as $user ) {
		
			$to_del = ( strtotime( $user->user_registered ) + $num_days * 24 * 60 * 60 < time() ) ? true : false;
			if( $to_del ) {
				wp_delete_user( $user->ID );
				$p = $p+1;
			}
		}
		return "$p " . __( 'pending users were purged', 'wp-members' );
	}
}


/**
 * Builds the export file of purged users
 *
 * @since 0.3
 *
 * @param int  $num_days
 * @param bool $inc_all
 */
function wpmem_exp_purge_export( $num_days, $inc_all = false )
{
	// start with clean headers...
	header_remove(); 


	/**
	 * Output needs to be buffered, start the buffer
	 */
	ob_start();

	
	// initial export setup...
	$args = array( 'meta_value' => 'pending' );
	$user_arr = get_users( $args );
	$x = 1;
	$p = 0;
	
	// if user tracking extention is active, we can get last login
	$get_last_login = ( is_plugin_active( 'wp-members-userstats/stats.php' ) ) ? true : false;

	// generate a filename based on date of export
	$today = date( "m-d-y" ); 
	$filename = "wp-members-user-delete-" . $today . ".csv";

	
	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );


	// get the fields
	$wpmem_fields = get_option( 'wpmembers_fields' );

	// do the header row
	$hrow = "User ID,Username,";

	for( $row = 0; $row < count( $wpmem_fields ); $row++ ) {
		$hrow.= $wpmem_fields[$row][1] . ",";
	}

	if( WPMEM_MOD_REG == 1 ) {
		$hrow.= __( 'Activated?', 'wp-members' ) . ",";
	}

	if( WPMEM_USE_EXP == 1 ) {
		$hrow.= __( 'Subscription', 'wp-members' ) . "," . __( 'Expires', 'wp-members' ) . ",";
	}

	$hrow.= __( 'Registered', 'wp-members' ) . ",";
	$hrow.= __( 'IP', 'wp-members' );
	
	if( $get_last_login ) {
		$hrow.= "," . __( 'Last Login', 'wp-members' );
	}
	
	$data = $hrow . "\r\n";

	// we used the fields array once, rewind so we can use it again
	reset( $wpmem_fields );


	foreach( $user_arr as $user ) {

		//$exdate = get_user_meta( $user->ID, 'expires', true );
		
		$to_del = ( strtotime( $user->user_registered ) + $num_days * 24 * 60 * 60 < time() ) ? true : false;
		
		//echo $x . "," . $user->ID . "," . $user->user_login . "," . $user->user_email . "<br \>";
	
		$user_info = get_userdata( $user->ID );

		$data.= '"' . $user_info->ID . '","' . $user_info->user_login . '",';
		
		for( $row = 0; $row < count( $wpmem_fields ); $row++ ) {
			
			if( $wpmem_fields[$row][2] == 'user_email' ) {
				$data.= '"' . $user_info->user_email . '",';
			} else {
				$data.= '"' . get_user_meta( $user->ID, $wpmem_fields[$row][2], true ) . '",';
			}
			
		}


		if( WPMEM_USE_EXP ==1 ) {
		
			$data.= '"' . get_user_meta( $user->ID, "exp_type", true ) . '",';
			$data.= '"' . get_user_meta( $user->ID, "expires", true  ) . '",';
		
		}
		
		$data.= '"' . $user_info->user_registered . '",';
		$data.= '"' . get_user_meta( $user->ID, "wpmem_reg_ip", true ) . '"';
		
		if( $get_last_login ) {
			$data.= ',"' . get_user_meta( $user->ID, "wpmemstat_last_login", true ) . '"';
		}	

		if( $to_del ) {
		
			wp_delete_user( $user->ID );
			
			$data.= ',"deleted"';
			
			$p = $p+1;
		}
		
		$data.= "\r\n";
		
		$x = $x+1;
		
	}


	echo $data;
	
	/**
	 * Clear the buffer 
	 */
	ob_flush();
	
	exit();
}

/** End of File **/