<?php
/*
Plugin Name: WP-Members Expirations Extension (Stripe)
Plugin URI:  https://kineticpulse.co.uk
Description: Provides subscriptions and trials with expiration dates for the WP-Members plugin (WP-Members must be installed and activated)
Version:     1.0
Author:      KineticPulse
*/
/**********************************
* constants and globals
**********************************/
 
if(!defined('STRIPE_BASE_URL')) {
	define('STRIPE_BASE_URL', plugin_dir_url(__FILE__));
}
if(!defined('STRIPE_BASE_DIR')) {
	define('STRIPE_BASE_DIR', dirname(__FILE__));
}


define( 'WPMEM_EXP_DIR',  plugin_dir_url ( __FILE__ ) );
define( 'WPMEM_EXP_PATH', plugin_dir_path( __FILE__ ) );


/**********************************
* includes
**********************************/
 
if(is_admin()) {

} else {
	// load front-end includes
	include(STRIPE_BASE_DIR . '/includes/scripts.php');
	include(STRIPE_BASE_DIR . '/includes/shortcodes.php');
	include(STRIPE_BASE_DIR . '/includes/process-payment.php');	
}

/**
 * Filters and actions
 */
add_action( 'init', 'wpmem_exp_chk_admin' );
add_action( 'wpmem_post_register_data', 'wpmem_snag_id' );
//add_filter( 'wpmem_payment_button', 'wpmem_adjust_id' );
//add_filter( 'wpmem_msg_dialog', 'wpmem_insert_payment_button' );


function add_stripe_styles(){
    wp_register_style( 'custom-style', plugins_url( '/css/stripe_exp.css', __FILE__ ), array(), '201708', 'all' );

    wp_enqueue_style( 'custom-style' );
}
add_action( 'admin_print_styles', 'add_stripe_styles' );
add_action( 'wp_enqueue_scripts', 'add_stripe_styles' , 50);


function wpmem_adjust_payment_options(){
	$exp_arr = get_option( 'wpmembers_experiod' );

	// set the options for the filters
	$arr = array(
		/*
		For the defaults, set the array value
		Do not change the array keys

		array key => array value
		*/
		'option_name' => 'subscription_type',      // field option name
		'tiers' => array(
			array(
				'val'  => 'student',
				'name' => $exp_arr['subscription_name_1'],
				'cost' => $exp_arr['subscription_cost_1'],
				'per' => $exp_arr['subscription_per_1'],
				'num' => $exp_arr['subscription_num_1']
			),
			array(
				'val'  => 'band_1',        // option value
				'name' => $exp_arr['subscription_name_2'], // subscription title
				'cost' => $exp_arr['subscription_cost_2'],
				'per' => $exp_arr['subscription_per_2'],
				'num' => $exp_arr['subscription_num_2']
			),
			array(
				'val'  => 'band_2',
				'name' => $exp_arr['subscription_name_3'],
				'cost' => $exp_arr['subscription_cost_3'],
				'per' => $exp_arr['subscription_per_3'],
				'num' => $exp_arr['subscription_num_3']
			),
			array(
				'val'  => 'band_3',
				'name' => $exp_arr['subscription_name_4'],
				'cost' => $exp_arr['subscription_cost_4'],
				'per' => $exp_arr['subscription_per_4'],
				'num' => $exp_arr['subscription_num_4']
			),
		),
	);

	return $arr;

}

add_filter( 'wpmem_payment_form', 'wpmem_adjust_payment_form' );

function wpmem_adjust_payment_form( $args ){

	extract( wpmem_adjust_payment_options() );
	$sub_type = get_user_meta(get_current_user_id(), $option_name, true);

	foreach( $tiers as $tier ) {
		if( $sub_type == $tier['val'] ) {
			$args['subscription_name'] = $tier['name'];
			$args['subscription_cost'] = $tier['cost'];
			$args['subscription_num'] = $tier['num'];
			$args['subscription_per'] = $tier['per'];
			$args['subscription_id'] = $tier['val'];
			return $args;
		}
	}

	return $args;
}

/**
 * Is the user an admin?
 */
function wpmem_exp_chk_admin() {
	if( current_user_can( 'activate_plugins' ) ) { include_once( 'admin/admin.php' ); }
}


/**
 * Utility function to get the user id
 *
 * @uses get_currentuserinfo http://codex.wordpress.org/Function_Reference/get_currentuserinfo
 * @return int the user ID
 */
function wpmem_get_user_id()
{
	global $current_user;
	get_currentuserinfo();
	return $current_user->ID;
}
	

/**
 * Displays the user's expiration date on the user detail page
 *
 * @return string $str the html to output
 *
 * @todo need to add something to show when it's expired
 * @todo maybe change the text that says "renew" to "extend"
 * @todo do we need get_currentuserinfo if we have the $current_user global?
 */
function wpmem_user_page_detail()
{	
	$user_id = wpmem_get_user_id();
	$expires = get_user_meta( $user_id, 'expires', true );
	$str = '';
	
	if( $expires ) {
		$parts   = explode( '/', $expires );
		$expires = $parts[0] . '/' . $parts [1] . '/' . $parts[2];	
	
		$type = get_user_meta( $user_id, 'exp_type', true );
		$link = wpmem_chk_qstr();
		
		if( $type == 'pending' ) {
			$link_txt = '<a href="' . $link . 'a=renew">Complete Payment</a>';
		} else {
			$link_txt = '<a href="' . $link . 'a=renew">Renew ' 
				. ucfirst( $type ) . '</a> [ expires: ' . $expires . ' ]';
		}
		$str  = '<li>' . $link_txt . '</li>';
	
	}

	return $str;
}


/**
 * Records the transaction data
 */
function wpmem_record_transaction( $details ) {
	// the user
	$user_id = $details['custom'];
	
	// get transactions for user
	$trans = get_user_meta( $user_id, 'wpmem_exp_transactions', false );
	$time  = current_time( 'timestamp' );
	$trans[$time] = $details;
	update_user_meta( $user_id, 'wpmem_exp_transactions', $details );
	
	return;
}

/**
 * Sets the expiration information for a user
 *
 * @param string $user_id
 * @param string $renew
 */
function wpmem_set_exp( $user_id, $renew = '' )
{
	// get the expiration periods and Stripe settings
	$exp_arr = get_option( 'wpmembers_experiod' );
	$stripe_arr  = get_option( 'wpmembers_stripe' );
	
	/**
	 * Filter the expiration settings array.
	 *
	 * @since 0.3
	 *
	 * @param array $exp_arr The expiration settings.
	 * @param bool  $renew   Is this a renewal?
	 */
	$exp_arr = apply_filters( 'wpmem_exp_experiod', $exp_arr, $renew );
	
	/**
	 * Filter the Stripe settings array.
	 *
	 * @since 0.3
	 *
	 * @param array $stripe_arr The Stripe settings.
	 * @param bool  $renew  Is this a renewal?
	 */
	$stripe_arr = apply_filters( 'wpmem_exp_stripe', $stripe_arr, $renew );
	
	// are we set for recurring payments?
	$recurring = ( $stripe_arr['stripe_cmd'] == 'recurring' ) ? true : false;

	if( WPMEM_USE_TRL == 1 && ( ! $renew ) && ( ! $recurring ) ) {

		// if there is a trial period, use that
		$exp_num = $exp_arr['trial_num'];
		$exp_per = $exp_arr['trial_per'];
		
		update_user_meta( $user_id, 'exp_type', 'trial' );

	} elseif( $renew ) {
	
		if( $recurring ) {
			if( get_user_meta( $user_id, 'exp_type', true ) == 'trial' && WPMEM_USE_TRL == 1 ) {
				$exp_type = 'subscription';
			} elseif( WPMEM_USE_TRL == 1 ) {
				$exp_type = 'trial';
			} else {
				$exp_type = 'subscription';
			}
		} else {
			$exp_type = 'subscription';
		}
		extract( wpmem_adjust_payment_options() );
		$sub_type = get_user_meta(get_current_user_id(), $option_name, true);

	foreach( $tiers as $tier ) {
		if( $sub_type == $tier['val'] ) {
			$exp_num = $tier['num'];
			$exp_per = $tier['per'];
		}
	}

		update_user_meta( $user_id, 'exp_type', $exp_type );

	} else {
	
		$exp_num = '';
		$exp_per = '';
		
		update_user_meta( $user_id, 'exp_type', 'pending' );
	
	}

	// if expiration is in the past, extend from today
	// otherwise extend from expiration date
	$tmpdate   = get_user_meta( $user_id, 'expires', true );
	$exp_from  = ( strtotime( $tmpdate ) > strtotime( date( "m/d/Y" ) ) ) ? $tmpdate : date( "m/d/Y" );
	$wpmem_exp = wpmem_exp_date( $exp_num, $exp_per, $exp_from ); 

	// set the user expiration
	update_user_meta( $user_id, 'expires', $wpmem_exp );
	
	/**
	 * Action after the user expiration date is set.
	 *
	 * @since 0.4
	 *
	 * @param int  $user_id The user ID.
	 * @param bool $renew   Is this a new subscription or a renewal.
	 */
	do_action( 'wpmem_exp_after_set_exp', $user_id, $renew );
	
	return $wpmem_exp;
	
}


/**
 * Checks the expiration date of a user
 *
 * @param int $admin_table
 * @return bool 
 */
function wpmem_chk_exp( $admin_table = '' )
{
	$user_id = ( $admin_table != '' ) ? $admin_table : wpmem_get_user_id();
	$wpmem_expires = get_user_meta( $user_id, 'expires', 'true' );
	
	// Check to see if the user is activated
	if( $admin_table != '' ) {
		$chk_active = get_user_meta( $user_id, 'active', 'true' );
		if( !$chk_active ) { return false; }
	}
	
	// FOR DEBUGGING
	if( WPMEM_DEBUG == 'true' ) { echo 'expires: ' . $wpmem_expires . '<br />time: ' . time(); }

	// if the user is not an admin, check expiration
	if( ! current_user_can( 'edit_users' ) ) {
		return ( strtotime( $wpmem_expires ) < time() ) ? true : false;
	} else {
		return false;
	}
}


/**
 * Extends WP's is_user_logged_in() function
 *
 * @since 0.1
 *
 * @uses is_user_logged_in
 * @return bool
 */
function is_user_expired() {
	return ( is_user_logged_in() && ( ! wpmem_chk_exp() ) ) ? true : false;
}


/**
 * Structures the expiration date
 *
 * @param int $exp_num the number of units in the expiration period
 * @param string $exp_per the expiration period (day, week, month)
 * @param date $exp_from the date of origin in determing the future expiration date
 * @return date the date the user will expire
 */
function wpmem_exp_date( $exp_num, $exp_per, $exp_from = '' )
{	
	$exp_from = ( $exp_from == '' ) ? date( "d/m/Y" ) : $exp_from;
	$wpmem_exp = strtotime( date( "d/m/Y", strtotime( $exp_from ) ) . " + $exp_num $exp_per" );
	$wpmem_exp = date( "d/m/Y", $wpmem_exp );
	
	return $wpmem_exp;
}


/**
 * The renewal process
 */
function wpmem_renew()
{
	/*
	$user_id = wpmem_get_user_id();
	$expires = get_user_meta( $user_id, 'expires', true );
	*/
	
	$p = ( isset( $_GET['payment'] ) ) ? $_GET['payment'] : '';
	
	if( $p == 'paid' ) { //&& ( preg_match( '~^(?:.+[.])?paypal[.]com$~', gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ) > 0 ) ) {
	
		$str = 'Thank you for your payment, your account will be automatically activated.';
	
	} elseif( $p == 'failed' ) {
	
	    $str = 'There was an error with your payment.';
	
	} elseif( ! $p ) {

		$str = wpmem_payment_form();
	
	}
	
	return $str;
}


function wpmem_payment_form()
{
	$defaults = array( 
		'wrapper_before' => '<p>',
		'wrapper_after'  => '</p>',
	);

	$arr = wp_parse_args($defaults, get_option( 'wpmembers_experiod' ), get_option( 'wpmembers_stripe' ) );
		

	/**
	 * Filter the payment form.
	 *
	 * @since 0.4
	 *
	 * @param array $arr The experiod settings combined with wrapper defaults.
	 */
	$arr = apply_filters( 'wpmem_payment_form', $arr );
	extract( $arr );

	$currency_sym=($currency=='GBP'?'£':'');

	$msg = "Subscription Rate: $currency_sym$subscription_cost / $subscription_num $subscription_per";
	$msg = ( $subscription_num > 1 ) ? $msg .= 's' : $msg;
	$cost = $subscription_cost * 100;
	if( $stripe_cmd == 'single' ) {
	
		$str.= '
			<input type="hidden" name="amount" value="' . $cost . '">';
		
	} else {

		$str.= '
			<input type="hidden" name="amount" value="' . $cost . '">
			<input type="hidden" name="plan_id" value="' . $subscription_id . '">
			<input type="hidden" name="src" value="1">
			<input type="hidden" name="sra" value="1">';	

		if( $trial_num ) {
			
			$str.= '
			<input type="hidden" name="amount" value="' . ($trial_cost*100) . '">
			<input type="hidden" name="plan_id" value="' . $trial_num . '">';
		
		}
	}

	$form = '<form action="" method="POST" id="stripe-payment-form">
			<div class="form-row">
				<label>Card Number</label>
				<input type="text" size="20" autocomplete="off" class="card-number"/>
			</div>
			<div class="form-row">
				<label>CVC</label>
				<input type="text" size="4" autocomplete="off" class="card-cvc"/>
			</div>
			<div class="form-row">
				<label>Expiration (MM/YYYY)</label>
				<input type="text" size="2" class="card-expiry-month"/>
				<span> / </span>
				<input type="text" size="4" class="card-expiry-year"/>
			</div>'.
			$str.
			'<input type="hidden" name="action" value="stripe"/>
			<input type="hidden" name="redirect" value="'.get_permalink().'?a=renew"/>
			<input type="hidden" name="stripe_nonce" value="'.wp_create_nonce('stripe-nonce').'"/>
			<button type="submit" id="stripe-submit">Submit Payment</button>
		</form>';
	
	return $wrapper_before . $msg . $form. $wrapper_after;
}



/** DIALOG FUNCTIONS */


/**
 * Creates the expiration message
 *
 * @param string $content
 * @global int $user_ID
 * @return string $content
 */
function wpmem_do_expmessage( $content )
{
	if( wpmem_chk_exp() == true ){
	
		global $post;
		$post->post_password = wp_generate_password();
		$content = wpmem_inc_expmessage();
		
	}

	return $content;
}


/**
 * Generates the expired account error message
 *
 * @var string $link
 * @var string $str
 * @return string $str
 */
function wpmem_inc_expmessage()
{ 
	$user_id = wpmem_get_user_id();
	
	$str = '<div class="wpmem_msg" align="center">
		<p><b>';
	
	if( get_user_meta( $user_id, 'exp_type', true ) === 'pending' ) {
	
		$str.= __( 'This content is for members only.', 'wp-members' ) . "</b>\r\n\r\n"
			. __( 'It appears that you have registered, but have not completed payment.  Once your payment is received, you will be able to access all of the premium members-only content on the site.', 'wp-members' )
			. '</p>';
		//$str.= wpmem_payment_button(); 
				/*<input type="hidden" name="return" value=" ... ?a=renew&p=thankyou"> */

	} else {
	
		$str.= __( 'Your account has expired.', 'wp-members' ) . '</b></p>';
		$str.= '<p><a href="' .  wpmem_chk_qstr( WPMEM_MSURL ) . 'a=renew">' . __( 'renew now', 'wp-members' ) . '</a></p>';
	}
	
	$str.= '</div>';
	
	/**
	 * Filter the user expired message.
	 *
	 * @since 0.1
	 *
	 * @param string $str The default message.
	 */
	$str = apply_filters( 'wpmem_exp_expired_msg', $str );
	
	return $str;
}


/**
 * Expiration message for status shortcode content
 *
 * @return string Content restricted message for shortcode protected content
 */
function wpmem_sc_expmessage()
{
	$msg = '<em>Subscription content restricted. Please <a href="' 
		.  wpmem_chk_qstr( WPMEM_MSURL ) . 'a=renew">' 
		. __( 'renew now', 'wp-members' ) . '</a>.</em>';
	
	/**
	 * Filter message for shortcode restricted content.
	 *
	 * @since 0.1
	 *
	 * @param string $msg The default message.
	 */
	$msg = apply_filters( 'wpmem_exp_sc_expmsg', $msg );
	
	return $msg;
}


if ( ! function_exists( 'wpmem_insert_payment_button' ) ):
/**
 * Inserts the payment form in the successful registration message.
 *
 * @since 0.2
 */
function wpmem_insert_payment_button( $str )
{
	global $wpmem_regchk;
	if( $wpmem_regchk == 'success' ) {
	
		$stripe_arr  = get_option( 'wpmembers_stripe' );
		
		//if( $stripe_arr['show_button'] == 1 ) {
		$msg = '<p>Login then go to your profile to set up the subscription</p>';
			// adds the payment form to the end of the registration success message
			$str = str_replace( '</div>', $msg
				. '<br /></div>', $str );
		
			/**
			 * Filter the successful registration message w/ payment button.
			 *
			 * @since 0.2
			 *
			 * @param string $str The successful registration message including the PayPal button.
			 */
			$str = apply_filters( 'wpmem_exp_success_msg', $str );
		//}
	}
	
	return $str;
}
endif;


// /** End of File **/