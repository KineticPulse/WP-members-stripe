<?php
/**
 * WP-Members Subscription Extension Admin Functions
 *
 * Functions to manage administration.
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
 * Actions and Filters
 */
add_filter( 'wpmem_admin_tabs', 'wpmem_a_sub_tab' );
add_action( 'wpmem_admin_do_tab', 'wpmem_a_subscription_tab', 1, 1 );
add_action( 'admin_init', 'wpmem_update_exp' );
add_action( 'admin_init', 'wpmem_exp_admin_do_purge' );
add_action( 'admin_menu', 'wpmem_exp_admin_users_menu' );
add_action( 'admin_footer-users.php', 'wpmem_exp_bulk_user_action' );
add_action( 'load-users.php', 'wpmem_exp_users_page_load' );
add_filter( 'user_row_actions', 'wpmem_exp_insert_activate_link', 10, 2 );
add_action( 'admin_notices', 'wpmem_exp_users_admin_notices' );


/**
 * Expires a user by setting their expiration date to yesterday
 *
 * @since 0.3
 *
 * @param int $user The user ID
 */
function wpmem_exp_expire_user( $user ) {
	$exp_date = date( "m/d/Y", strtotime( "-1 day" ) );
	update_user_meta( $user, 'expires', $exp_date );
	return;
}



/**
 * Extends a user
 *
 * @since 0.1
 *
 * @param int $user_id
 *
 * @todo rework this to utilize wpmem_set_exp
 */
function wpmem_a_extend_user( $user_id )
{
	// this is a truncated version of wpmem_set_exp until i can rework that function accordingly...
	if( isset( $_POST['wpmem_extend'] ) && $_POST['wpmem_extend'] > 0 ) {

		// get the expiration periods
		$exp_arr = get_option( 'wpmembers_experiod' );

		$exp_num = $_POST['wpmem_extend'];
		$exp_per = $exp_arr['subscription_per'];
		
		// if expiration is in the past, extend from today
		// otherwise extend from expiration date
		$tmp = get_user_meta( $user_id, 'expires', true );
		
		$exp_from = ( strtotime( $tmp ) > strtotime( date( "m/d/Y" ) ) ) ? $tmp : date( "m/d/Y" );
		
		$wpmem_exp = wpmem_exp_date( $exp_num, $exp_per, $exp_from );
		update_user_meta( $user_id, 'expires', $wpmem_exp );
		update_user_meta( $user_id, 'exp_type', 'subscription' );
	}
	
	// check to see if we are expiring a user
	if( isset( $_POST['expire_user'] ) && $_POST['expire_user'] == 'expire' ) {
		wpmem_exp_expire_user( $user_id );
	}
	
	return;
}


/**
 * Adds trial & expiration information to the plugin settings page
 *
 * @since 0.1
 *
 * @param date   $wpmem_experiod
 * @param string $trial
 * @param string $expire
 */
function wpmem_a_build_expiration( $wpmem_experiod, $trial, $expire )
{
	extract( wp_parse_args( '',  get_option( 'wpmembers_stripe' ) ) );
	
	global $did_update;
	
	if( $did_update ) { ?>
		<div id="message" class="updated fade"><p><strong><?php echo $did_update; ?></strong></p></div>
	<?php } ?>
	
		<div class="metabox-holder has-right-sidebar">
	
		<div class="inner-sidebar">
			<div class="postbox">
				<h3><span>WP-Members Stripe Module</span></h3>
				<div class="inside">
					<p><strong><?php _e('Version:', 'wp-members'); ?> 1.0</strong><br /></p>
				</div>
			</div>
			<?php wpmem_a_meta_box(); ?>
		</div>

		<div id="post-body">
			<div id="post-body-content">
				<div class="postbox">
					<h3><span><?php _e( 'Trial & Subscription Settings', 'wp-members' ); ?></span></h3>
					<div class="inside">
						<form name="updatesettings" id="updatesettings" method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
							<ul>
							<?php if( ! WPMEM_USE_EXP ) {	?>
								<p>Expiration is not currently turned on in the <a href="<?php get_admin_url(); ?>options-general.php?page=wpmem-settings&tab=options">plugin options</a>.</p>
							<?php } else { ?>
							<?php if( function_exists( 'wp_nonce_field' ) ) { wp_nonce_field( 'wpmem-update-exp' ); } ?>
							<?php if( $trial == 1  ) { wpmem_a_build_exp_field( $wpmem_experiod, 'trial' ); }
								  if( $expire == 1 ) { wpmem_a_build_exp_field( $wpmem_experiod, 'subscription' ); } ?>
								  <li><strong>Payment Settings</strong></li>
								<li>
									<label>Transaction Type</label>
									<select name="stripe_cmd">
									<?php $arr = array( 'single' => 'Basic', 'recurring' => 'Recurring' );
									
									foreach( $arr as $key => $val ) {
										echo wpmem_create_formfield( $val, 'option', $key, $stripe_cmd );
									} ?>
									</select>
								</li>
								<li>
									<label>Currency</label>
									<select name="currency">
									<?php $arr = array(
										array( 'code'=>'USD', 'currency'=>'US Dollars' ),
										array( 'code'=>'AUD', 'currency'=>'Australian Dollar' ),
										array( 'code'=>'BRL', 'currency'=>'Brazilian Real' ),
										array( 'code'=>'CAD', 'currency'=>'Canadian Dollar' ),
										array( 'code'=>'CZK', 'currency'=>'Czech Koruna' ),
										array( 'code'=>'DKK', 'currency'=>'Danish Krone' ),
										array( 'code'=>'EUR', 'currency'=>'Euro' ),
										array( 'code'=>'HKD', 'currency'=>'Hong Kong Dollar' ),
										array( 'code'=>'HUF', 'currency'=>'Hungarian Forint' ),
										array( 'code'=>'ILS', 'currency'=>'Israeli New Sheqel' ),
										array( 'code'=>'JPY', 'currency'=>'Japanese Yen' ),
										array( 'code'=>'MYR', 'currency'=>'Malaysian Ringgit' ),
										array( 'code'=>'MXN', 'currency'=>'Mexican Peso' ),
										array( 'code'=>'NOK', 'currency'=>'Norwegian Krone' ),
										array( 'code'=>'NZD', 'currency'=>'New Zealand Dollar' ),
										array( 'code'=>'PHP', 'currency'=>'Philippine Peso' ),
										array( 'code'=>'PLN', 'currency'=>'Polish Zloty' ),
										array( 'code'=>'GBP', 'currency'=>'Pound Sterling' ),
										array( 'code'=>'SGD', 'currency'=>'Singapore Dollar' ),
										array( 'code'=>'SEK', 'currency'=>'Krona' ),
										array( 'code'=>'CHF', 'currency'=>'Franc' ),
										array( 'code'=>'TWD', 'currency'=>'Taiwan New Dollar' ),
										array( 'code'=>'THB', 'currency'=>'Thai Baht' ),
										array( 'code'=>'TRY', 'currency'=>'Turkish Lira' )
									);
									
									for( $row = 0; $row < ( count( $arr ) ); $row++ ) {
										echo wpmem_create_formfield( $arr[$row]['currency'], 'option', $arr[$row]['code'], $wpmem_experiod['currency'] );
									} ?>
									</select>
								</li>
								<li>
									<label>Stripe Live/Test</label>
									<select name="stripe_live"><?php
									echo wpmem_create_formfield( 'Test', 'option', '0', $stripe_live );
									echo wpmem_create_formfield( 'Live', 'option', '1', $stripe_live );
									?></select>

								<li>
									<label>Stripe Live Secret</label>
										<?php echo wpmem_create_formfield('stripe_live_secret','text',$stripe_live_secret,'','input_large');?>								
								</li>
								<li>
									<label>Stripe Live Publishable</label>
										<?php echo wpmem_create_formfield('stripe_live_publishable','text',$stripe_live_publishable,'','input_large');?>								
								</li>
								<li>
									<label>Stripe Test Secret</label>
										<?php echo wpmem_create_formfield('stripe_test_secret','text',$stripe_test_secret,'','input_large');?>								
								</li>
								<li>
									<label>Stripe Test Publishable</label>
										<?php echo wpmem_create_formfield('stripe_test_publishable','text',$stripe_test_publishable,'','input_large');?>								
								</li>

								</li>
								<li>
							  <?php if( ! function_exists( 'wpmem_admin_page_list' ) ) { include_once( WPMEM_PATH . '/admin/tab-options.php' ); } ?>
								<li>
									<label><?php _e( 'User Login Page (optional):', 'wp-members' ); ?></label>
									<select name="login_page">
										<?php wpmem_admin_page_list( $login_page ); ?>
									</select>
								</li>

								<li>
									<label>&nbsp;</label>
									<input type="hidden" name="wpmem_admin_a" value="update_exp" />
									<input type="submit" name="save"  class="button-primary" value="<?php _e( 'Update', 'wp-members' ); ?> &raquo;" />
								</li>
							<?php } ?>
							</ul>
						</form>
					</div><!-- .inside -->
				</div>
			</div><!-- #post-body-content -->
		</div><!-- #post-body -->
	</div><!-- .metabox-holder -->
	<?php
}


/**
 * Builds the expiration form for the plugin options panel
 *
 * @since 0.1
 *
 * @param date   $wpmem_experiod The expiration date
 * @param string $field          Which field is being built
 */
function wpmem_a_build_exp_field( $wpmem_experiod, $field )
{
	switch( $field ) {
		case( 'trial' ):
			$title = __( 'Set Trial Period', 'wp-members' );
			break;
		case( 'subscription' ):
			$title = __( 'Set Subscription Levels', 'wp-members' );
			break;
	} ?>
	<li><strong><?php echo $title; ?></strong></li>
		<?php for ($x = 1; $x <= 4; $x++) {?>
	<li>
		Level <?php echo $x;?> Cost: <?php echo wpmem_create_formfield( $field . '_cost_'.$x, 'text', $wpmem_experiod[$field."_cost_".$x], '', 'input_small' ); ?>
		Level <?php echo $x;?> Name: <?php echo wpmem_create_formfield( $field . '_name_'.$x, 'text', $wpmem_experiod[$field."_name_".$x], '', 'textbox' ); ?>
		<?php echo wpmem_create_formfield( $field . '_id_'.$x, 'hidden', 'student'); ?>

		<select name="<?php echo $field.'_num_'.$x;?>">
        <?php
			$max = apply_filters( 'wpmem_exp_experiod_max', 12 ) + 1;
			for( $i = 1; $i < $max; $i++ ) {
			echo wpmem_create_formfield( $i, 'option', $i, $wpmem_experiod[$field."_num_".$x] );
		} ?>
        </select>
		<select name="<?php echo $field.'_period_'.$x;?>"><?php
			echo wpmem_create_formfield( __( 'Day(s)', 'wp-members' ), 'option', 'day', $wpmem_experiod[$field."_per_".$x] );
			echo wpmem_create_formfield( __( 'Week(s)', 'wp-members' ), 'option', 'week', $wpmem_experiod[$field."_per_".$x] );
			echo wpmem_create_formfield( __( 'Month(s)', 'wp-members' ), 'option', 'month', $wpmem_experiod[$field."_per_".$x] );
			echo wpmem_create_formfield( __( 'Year(s)', 'wp-members' ), 'option', 'year', $wpmem_experiod[$field."_per_".$x] );
			?>
		</select>
	</li>
	<?php }?>
		   <?php
}


/**
 * Create an expiration period
 *
 * @since 0.1
 *
 * @return array $wpmem_newexperiod
 */
function wpmem_a_newexperiod()
{
	return array(
		'subscription_num_1'  => ( isset( $_POST['subscription_num_1']    ) ) ? $_POST['subscription_num_1']    : '',
		'subscription_per_1'  => ( isset( $_POST['subscription_period_1'] ) ) ? $_POST['subscription_period_1'] : '',
		'subscription_cost_1' => ( isset( $_POST['subscription_cost_1']   ) ) ? $_POST['subscription_cost_1']   : '',
		'subscription_name_1' => ( isset( $_POST['subscription_name_1']   ) ) ? $_POST['subscription_name_1']   : '',
		'subscription_id_1' => ( isset( $_POST['subscription_id_1']   ) ) ? $_POST['subscription_id_1']   : '',
		'subscription_num_2'  => ( isset( $_POST['subscription_num_2']    ) ) ? $_POST['subscription_num_2']    : '',
		'subscription_per_2'  => ( isset( $_POST['subscription_period_2'] ) ) ? $_POST['subscription_period_2'] : '',
		'subscription_cost_2' => ( isset( $_POST['subscription_cost_2']   ) ) ? $_POST['subscription_cost_2']   : '',
		'subscription_name_2' => ( isset( $_POST['subscription_name_2']   ) ) ? $_POST['subscription_name_2']   : '',
		'subscription_id_2' => ( isset( $_POST['subscription_id_2']   ) ) ? $_POST['subscription_id_2']   : '',
		'subscription_num_3'  => ( isset( $_POST['subscription_num_3']    ) ) ? $_POST['subscription_num_3']    : '',
		'subscription_per_3'  => ( isset( $_POST['subscription_period_3'] ) ) ? $_POST['subscription_period_3'] : '',
		'subscription_cost_3' => ( isset( $_POST['subscription_cost_3']   ) ) ? $_POST['subscription_cost_3']   : '',
		'subscription_name_3' => ( isset( $_POST['subscription_name_3']   ) ) ? $_POST['subscription_name_3']   : '',
		'subscription_id_3' => ( isset( $_POST['subscription_id_3']   ) ) ? $_POST['subscription_id_3']   : '',
		'subscription_num_4'  => ( isset( $_POST['subscription_num_4']    ) ) ? $_POST['subscription_num_4']    : '',
		'subscription_per_4'  => ( isset( $_POST['subscription_period_4'] ) ) ? $_POST['subscription_period_4'] : '',
		'subscription_cost_4' => ( isset( $_POST['subscription_cost_4']   ) ) ? $_POST['subscription_cost_4']   : '',
		'subscription_name_4' => ( isset( $_POST['subscription_name_4']   ) ) ? $_POST['subscription_name_4']   : '',
		'subscription_id_4' => ( isset( $_POST['subscription_id_4']   ) ) ? $_POST['subscription_id_4']   : '',
		'trial_num'         => ( isset( $_POST['trial_num']           ) ) ? $_POST['trial_num']           : '',
		'trial_per'         => ( isset( $_POST['trial_period']        ) ) ? $_POST['trial_period']        : '',
		'trial_cost'        => ( isset( $_POST['trial_cost']          ) ) ? $_POST['trial_cost']          : '',
		'trial_name'        => ( isset( $_POST['trial_name']          ) ) ? $_POST['trial_name']          : '',
		'currency'          => ( isset( $_POST['currency']            ) ) ? $_POST['currency']            : ''
	);
}


/**
 * Updates the Stripe Settings
 *
 * @since 0.1
 */
function wpmem_a_newstripe()
{
	return array(
		'stripe_live_secret'   => ( isset( $_POST['stripe_live_secret']   ) ) ? $_POST['stripe_live_secret']   : '',
		'stripe_live_publishable'   => ( isset( $_POST['stripe_live_publishable']   ) ) ? $_POST['stripe_live_publishable']   : '',
		'stripe_test_secret'   => ( isset( $_POST['stripe_test_secret']   ) ) ? $_POST['stripe_test_secret']   : '',
		'stripe_test_publishable'   => ( isset( $_POST['stripe_test_publishable']   ) ) ? $_POST['stripe_test_publishable']   : '',
		'stripe_live' => ( isset( $_POST['stripe_live'] ) ) ? $_POST['stripe_live'] : 0,
		'login_page'  => ( isset( $_POST['login_page']  ) ) ? $_POST['login_page']  : '',
		'stripe_cmd'  => ( isset( $_POST['stripe_cmd']  ) ) ? $_POST['stripe_cmd']  : '',
		'show_button' => ( isset( $_POST['show_button'] ) ) ? $_POST['show_button'] : 0
	);
}


/**
 * Displays a subscription information on the user profile
 *
 * @since 0.1
 *
 * @param int $user_id
 */
function wpmem_a_extenduser( $user_id )
{ ?>
	<tr>
		<th><label><?php
			$status = ucfirst( get_user_meta( $user_id, 'exp_type', 'true' ) );
			echo ( $status == 'Pending' ) ? ucfirst( __( 'expires', 'wp-members' ) ) : $status . ' ' . __( 'expires', 'wp-members' ); ?>:
		</label></th>
		<td><?php
			$exp = get_user_meta($user_id, 'expires', 'true');
			echo ( $exp == '01/01/1970' ) ? 'pending payment' : $exp; ?>&nbsp;&nbsp;&nbsp;
			<strong><?php _e( 'Extend:', 'wp-members' ); ?></strong>
			<select name="wpmem_extend">
				<option value="" selected>--</option>
				<?php for( $i = 1; $i < 13; $i++ ) { echo wpmem_create_formfield( $i, 'option', $i ); } ?>
			</select><?php
				$tmp = get_option( 'wpmembers_experiod' );
				echo ucfirst($tmp['subscription_per'])."(s)";
			?>
			&nbsp;&nbsp;&nbsp;
			<strong>Expire:</strong>
			<input id="expire_user" class="input" type="checkbox" name="expire_user" value="expire" />
		</td>
	</tr><?php
}


function wpmem_update_stripe_plans() {
	$arr = wp_parse_args(get_option( 'wpmembers_experiod' ), get_option( 'wpmembers_stripe' ) );
	extract( $arr );
	if($stripe_live=='0') {
			$secret_key = $stripe_test_secret;
		} else {
			$secret_key = $stripe_live_secret;
		}
require_once(STRIPE_BASE_DIR .'/vendor/stripe/stripe-php/init.php');

	\Stripe\Stripe::setApiKey($secret_key);
	try {
	\Stripe\Plan::create(array(
		  "amount" => $subscription_cost_1*100,
		  "interval" => "month",
		  "interval_count" => $subscription_num_1,
		  "name" => $subscription_name_1,
		  "statement_descriptor" => $subscription_name_1,
		  "currency" => $currency,
		  "id" => $subscription_id_1)
		);
}
catch(Exception $e) {
  $msg=$e->getMessage();
}
try {
	\Stripe\Plan::create(array(
		  "amount" => $subscription_cost_2*100,
		  "interval" => "month",
		  "interval_count" => $subscription_num_2,
		  "name" => $subscription_name_2,
		  "statement_descriptor" => $subscription_name_2,
		  "currency" => $currency,
		  "id" => $subscription_id_2)
		);
}
catch(Exception $e) {
  $msg=$e->getMessage();
}
try {
	\Stripe\Plan::create(array(
		  "amount" => $subscription_cost_3*100,
		  "interval" => "month",
		  "interval_count" => $subscription_num_3,
		  "name" => $subscription_name_3,
		  "statement_descriptor" => $subscription_name_3,
		  "currency" => $currency,
		  "id" => $subscription_id_3)
		);
}
catch(Exception $e) {
  $msg=$e->getMessage();
}
try {
	\Stripe\Plan::create(array(
		  "amount" => $subscription_cost_4*100,
		  "interval" => "month",
		  "interval_count" => $subscription_num_4,
		  "name" => $subscription_name_4,
		  "statement_descriptor" => $subscription_name_4,
		  "currency" => $currency,
		  "id" => $subscription_id_4)
		);
}
catch(Exception $e) {
  $msg=$e->getMessage();
}
}

/**
 * Update module settings
 *
 * @since 0.1
 */
function wpmem_update_exp()
{
	if( isset( $_POST['wpmem_admin_a'] ) ) {
	
		global $did_update;
		
		//check nonce
		//check_admin_referer('wpmem-update-exp');
		
		if( $_POST['wpmem_admin_a'] == 'update_exp' ) {
		
			$wpmem_newexperiod = wpmem_a_newexperiod();
			update_option( 'wpmembers_experiod', $wpmem_newexperiod );
			
			$wpmem_newstripe = wpmem_a_newstripe();
			update_option( 'wpmembers_stripe', $wpmem_newstripe );
			
			$wpmem_experiod = $wpmem_newexperiod;
			( WPMEM_DEBUG ) ? print_r( $wpmem_experiod ) : false;
			( WPMEM_DEBUG ) ? print_r( $wpmem_newstripe ) : false;
			$did_update = __( 'WP-Members expiration periods were updated', 'wp-members' );
			wpmem_update_stripe_plans();
		} 

		return $did_update;
	}
}


/**
 * Add subscription information to the user profile
 *
 * @since 0.1
 *
 * @param $admin
 */
function wpmem_a_build_sub_profile( $admin ) {
	?><h3><?php _e('Subscription Information'); ?></h3><?php
}


/**
 * Adds subscription to the admin tab array
 *
 * @since 0.1
 */
function wpmem_a_sub_tab( $tabs ) {
	return array_merge( $tabs, array( 'subscriptions'  => 'Subscriptions' ) );
}


/**
 * Builds the subscription tab in the admin
 *
 * @since 0.1
 */
function wpmem_a_subscription_tab( $tab )
{
	if( $tab == 'subscriptions' ) {
		$wpmem_experiod = get_option('wpmembers_experiod');
		wpmem_a_build_expiration( $wpmem_experiod, WPMEM_USE_TRL, WPMEM_USE_EXP );
	}
	
	return;
}


/**
 * Add the Delete Pending menu item to Users Menu
 *
 * @since 0.3
 */
function wpmem_exp_admin_users_menu() {
	add_users_page( 'Delete Pending', 'Delete Pending', 'create_users', 'delete-pending', 'wpmem_exp_admin_users' );
	//add_users_page( 'User Reports', 'User Reports', 'create_users', 'user-reports', 'wpmem_exp_admin_user_reports' );
}


/**
 * Build the Delete Pending admin page
 *
 * @since 0.3
 */
function wpmem_exp_admin_users()
{ 
	global $user_action_msg;
	?>
	<div class="wrap">
		<h2>Delete Pending Users</h2>
		<form name="deletepending" id="deletepending" method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
			<p>Delete accounts that have not completed payment<br />
			and have been pending for more than <input type="text" name="num_days" value="7" size="1" /> days</p>
			<p>
			  <input type="checkbox" name="export_purge" value="1" /> Export deleted users as CSV<br />
			  <input type="checkbox" name="inc_all" value="1" /> Include all pending users in export <span class="description">(including those not deleted)</span>
			</p>
			<p><input type="checkbox" name="confirm_purge" value="1" /> Confirm you want to delete pending users.</p>
			<p><strong>This operation cannot be undone!</strong></p>
			<input type="hidden" name="wpmem_admin_a" value="del_pending" />
			<input type="submit" name="save"  class="button-primary" value="<?php _e( 'Delete Pending', 'wp-members' ); ?> &raquo;" />
		</form>
	</div>
<?php
}


/**
 * Do pending user purge
 *
 * @since 0.3
 */
function wpmem_exp_admin_do_purge()
{
	global $user_action_msg;
	$user_action_msg = false;
	if( isset( $_POST['wpmem_admin_a'] ) && $_POST['wpmem_admin_a'] == 'del_pending' ) {
		if( isset( $_POST['confirm_purge'] ) && $_POST['confirm_purge'] == 1 ) {
			include_once( 'delete-pending.php' );
			$export_purge = ( isset( $_POST['export_purge'] ) ) ? $_POST['export_purge'] : false;
			$inc_all      = ( isset( $_POST['inc_all'] ) ) ? $_POST['inc_all'] : false;
			$user_action_msg = wpmem_exp_purge_pending( $_POST['num_days'], $export_purge, $inc_all );
		} else {
			$user_action_msg = "You need to confirm that you wish to delete pending users.  No action taken.";
		}
	}
}


/**
 * Expires a user by setting their expiration date to yesterday
 *
 * @since 0.3
 */
function wpmem_exp_users_page_load()
{	
	$wp_list_table = _get_list_table( 'WP_Users_List_Table' );
	$action = $wp_list_table->current_action();
	$sendback = '';
	
	switch( $action ) {
	case ( 'expire' ):	
		/** validate nonce */
		check_admin_referer( 'bulk-users' );
		
		/** get the users */
		$users = $_REQUEST['users'];
		
		/** update the users */
		$x = 0;
		foreach( $users as $user ) {
			// check to see if the user has an expiration date
			if( get_user_meta( $user, 'expires', true ) ) {
				// set expiration date to yesterday
				wpmem_exp_expire_user( $user );
				$x++;
			}
		}
		
		/** set the return message */
		$sendback = add_query_arg( array('expire' => $x . " users expired" ), $sendback );	

		wp_redirect( $sendback );
		exit();
		break;
		
	case( 'expire-single' ):
		/** validate nonce */
		check_admin_referer( 'expire-user' );
		
		/** get the user */
		$user = $_REQUEST['user'];

		// check to see if the user has an expiration date
		if( get_user_meta( $user, 'expires', true ) ) {
			
			wpmem_exp_expire_user( $user );
			
			/** get the user data */
			$user_info = get_userdata( $user );

			/** set the return message */
			$sendback = add_query_arg( array('expire' => "$user_info->user_login expired" ), $sendback );
		
		} else {

			/** get the return message */
			$sendback = add_query_arg( array('expire' => "That user has no expiration date yet or is already expired" ), $sendback );
		
		}
		
		break;
	
	default:
		return;
		break;

	}
}	

	
/**
 * Adds "Expire" option to bulk user management dropdown
 *
 * @since 0.3
 */
function wpmem_exp_bulk_user_action()
{ ?>
    <script type="text/javascript">
      jQuery(document).ready(function() {
		jQuery('<option>').val('expire').text('<?php _e('Expire')?>').appendTo("select[name='action']");
		jQuery('<option>').val('expire').text('<?php _e('Expire')?>').appendTo("select[name='action2']");
	  });
    </script>
    <?php
}


/**
 * Adds "Expire" hover link to the user mangement screen
 *
 * @since 0.3
 */
function wpmem_exp_insert_activate_link( $actions, $user_object )
{
    if( current_user_can( 'edit_users', $user_object->ID ) ) {
	
		$var = get_user_meta( $user_object->ID, 'active', true );
		
		if( $var != 1 ) {
			$url = "users.php?action=expire-single&amp;user=$user_object->ID";
			$url = wp_nonce_url( $url, 'expire-user' );
			$actions['expire'] = '<a href="' . $url . '">Expire</a>';
		}
	}
    return $actions;
}


/**
 * Function to echo admin update message
 *
 * @since 0.3
 */
function wpmem_exp_users_admin_notices()
{    
	global $pagenow, $user_action_msg;
	if( $pagenow == 'users.php' && isset( $_REQUEST['expire'] ) ) {
		$message = $_REQUEST['expire'];
		echo "<div class=\"updated\"><p>{$message}</p></div>";
	}

	if( $user_action_msg ) {
		echo "<div class=\"updated\"><p>{$user_action_msg}</p></div>";
	}
}
/** End of File **/