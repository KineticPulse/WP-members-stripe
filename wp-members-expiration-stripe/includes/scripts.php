<?php

function load_stripe_scripts() {
 
	$stripe_arr  = get_option( 'wpmembers_stripe' );
 
	// check to see if we are in test mode
	if($stripe_arr['stripe_live']=='0') {
		$publishable = $stripe_arr['stripe_test_publishable'];
	} else {
		$publishable = $stripe_arr['stripe_live_publishable'];
	}
 
	wp_enqueue_script('stripe', 'https://js.stripe.com/v2/');
	wp_enqueue_script('stripe-processing', plugin_dir_url(__FILE__) . 'js/stripe-processing.js');
	wp_localize_script('stripe-processing', 'stripe_vars', array(
			'publishable_key' => $publishable,
		)
	);
}
add_action('wp_enqueue_scripts', 'load_stripe_scripts');