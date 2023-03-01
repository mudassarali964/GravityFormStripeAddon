<?php  
  //////////////////// START ////////////////////////////
 ////////// Stripe Change Guest to Customer ////////////
///////////////////////////////////////////////////////

/**
 * setup_future_usage
 *
 * @param $data - The payment intent data.
 * @param $feed - The feed object currently being processed.
 *
 * Indicates that you intend to make future payments with this PaymentIntent’s payment method.
 * on_session - Use on_session if you intend to only reuse the payment method when your customer is present in your checkout flow.
 * off_session - Use off_session if your customer may or may not be present in your checkout flow.
 */
add_filter( 'gform_stripe_payment_intent_pre_create', function( $data, $feed ) {
    $data['setup_future_usage'] = 'off_session';
    return $data;
}, 10, 2 );

/**
 * Stripe Change Guest to Customer
 *
 * @param $customer_id - The Stripe customer id. Defaults to an empty string causing a new customer to be created when processing the subscription feed.
 * @param $feed - The Feed which is currently being processed.
 * @param $entry - The Entry which is currently being processed.
 * @param $form - The Form which is currently being processed.
 *
 * $metaData - Get form meta data values.
 * $response - Get stripe js response (For Payment Intent).
 * $customer - Create a new Customer.
 * $paymentIntent - Retrieve a Payment Intent object by passing Payment Intent id.
 */
add_filter( 'gform_stripe_customer_id',  function( $customer_id, $feed, $entry, $form ) {
    if ( rgars( $feed, 'meta/transactionType' ) == 'product' && rgars( $feed, 'meta/feedName' ) == 'Stripe Feed 1' ) {
        $customerMeta = [];
        $metaData = gf_stripe()->get_stripe_meta_data($feed, $entry, $form);
        $response = gf_stripe()->get_stripe_js_response();

        if ( !empty($metaData) ) {
            $customerMeta['email'] = $metaData['Email'];
            $customerMeta['name'] = $metaData['Name'];
        }

        $customer = gf_stripe()->create_customer( $customerMeta, $feed, $entry, $form );
        if ( !empty($response->id) && !empty($customer)) {
            // TODO: $customer_id - Update the functions @param $customer_id to the newly created $customer->id.
            $customer_id = $customer->id;
            attachPaymentMethodToCustomer($response, $customer_id, $entry);
        }
    }

    return $customer_id;

}, 10, 4 );

/**
 * Attaches a PaymentMethod object to a Customer
 *
 * @param $response - Get stripe js response (For Payment Intent).
 * @param $customer_id - Created Customer's Id.
 * @param $entry - The Entry which is currently being processed.
 *
 */
function attachPaymentMethodToCustomer($response, $customer_id, $entry) {
    $apiKey = gf_stripe()->get_secret_api_key();
    $stripe = new \Stripe\StripeClient($apiKey);
    $paymentIntent = $stripe->paymentIntents->retrieve($response->id);

    if ( !empty($paymentIntent) ) {
        $paymentMethodId = $paymentIntent->payment_method;
        if ( !empty($paymentMethodId) ) {
            // TODO: Attaches a PaymentMethod object to a Customer.
            $stripe->paymentMethods->attach(
                $paymentMethodId,
                ['customer' => $customer_id]
            );
            // TODO: To use this PaymentMethod as the default for invoice or subscription payments.
            $stripe->customers->update(
                $customer_id,
                ['invoice_settings' => ['default_payment_method' => $paymentMethodId]]
            );
        }
        return;
    }
    return;
}