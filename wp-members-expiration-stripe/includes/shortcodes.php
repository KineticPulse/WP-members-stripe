<?php
function stripe_payment_form() {

	if(isset($_GET['payment']) && $_GET['payment'] == 'paid') {
		echo '<p class="success">Thank you for your payment.</p>';
	} else { 


	?>
		<h2>Submit a test payment of &pound;10</h2>
		<form action="" method="POST" id="stripe-payment-form">
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
			</div>
			<input type="hidden" name="action" value="stripe"/>
			<input type="hidden" name="redirect" value="<?php echo get_permalink(); ?>"/>
			<input type="hidden" name="stripe_nonce" value="<?php echo wp_create_nonce('stripe-nonce'); ?>"/>
			<button type="submit" id="stripe-submit">Submit Payment</button>
		</form>
		<div class="payment-errors"></div>
		<?php
	}
}
add_shortcode('payment_form', 'stripe_payment_form');