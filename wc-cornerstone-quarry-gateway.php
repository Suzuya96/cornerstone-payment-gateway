<?php
/**
 * Plugin Name: WooCommerce Cornerstone Quarry API Gateway
 * Description: Custom payment gateway integration for Cornerstone Payment Systems via the Quarry REST API.
 * Version:     1.1.0
 * Author:      Osama Bukhari
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Initialize the gateway after plugins are loaded
add_action( 'plugins_loaded', 'init_wc_cornerstone_quarry_gateway' );

function init_wc_cornerstone_quarry_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Cornerstone_Quarry_Gateway extends WC_Payment_Gateway {
		public string $client_id = '';
        public string $client_key = '';

        public function __construct() {
            $this->id                 = 'cornerstone_quarry';
            $this->icon               = '';
            $this->has_fields         = true;
            $this->method_title       = 'Cornerstone Quarry API';
            $this->method_description = 'Process credit card payments securely using Cornerstone\'s Quarry REST API.';

            // Tell WooCommerce this gateway supports refunds
            $this->supports = array(
                'products',
                'refunds',
            );

            // Load the settings schema
            $this->init_form_fields();
            $this->init_settings();

            // Define user-set variables from the settings page
            $this->title       = $this->get_option( 'title', 'Credit Card' );
            $this->description = $this->get_option( 'description', 'Pay securely with your credit card.' );
            $this->client_id   = $this->get_option( 'client_id' );
            $this->client_key  = $this->get_option( 'client_key' );

            // Hook to save settings in the admin panel
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Design the Admin Settings Configuration Panel
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable Cornerstone Quarry Gateway',
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description the user sees during checkout.',
                    'default'     => 'Pay securely with your credit card.',
                    'desc_tip'    => true,
                ),
                'client_id' => array(
                    'title'       => 'Client ID',
                    'type'        => 'text',
                    'description' => 'Provided by Cornerstone support.',
                ),
                'client_key' => array(
                    'title'       => 'Client Key',
                    'type'        => 'password',
                    'description' => 'Provided by Cornerstone support.',
                ),
            );
        }

        /**
         * Render Form Fields for Credit Card details on Checkout page
         */
        public function payment_fields() {
            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }
            ?>
            <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
                <p class="form-row form-row-first">
                    <label>Card Number <span class="required">*</span></label>
                    <input type="text" id="cornerstone_card_num" name="cornerstone_card_num" autocomplete="off" maxlength="16" />
                </p>
                <p class="form-row form-row-last">
                    <label>Card Code (CVC/CVV) <span class="required">*</span></label>
                    <input type="password" id="cornerstone_card_cvc" name="cornerstone_card_cvc" autocomplete="off" maxlength="4" />
                </p>
                <p class="form-row form-row-first">
                    <label>Expiry Month (MM) <span class="required">*</span></label>
                    <input type="text" id="cornerstone_exp_month" name="cornerstone_exp_month" placeholder="MM" maxlength="2" />
                </p>
                <p class="form-row form-row-last">
                    <label>Expiry Year (YY) <span class="required">*</span></label>
                    <input type="text" id="cornerstone_exp_year" name="cornerstone_exp_year" placeholder="YY" maxlength="2" />
                </p>
                <div class="clear"></div>
            </fieldset>
            <?php
        }

        /**
         * Validate card fields before WooCommerce attempts to process the order.
         * Runs on the 'woocommerce_checkout_process' hook (added in process_payment via
         * validate_fields(), called automatically by WooCommerce before process_payment()).
         */
        public function validate_fields() {
            $card_num   = isset( $_POST['cornerstone_card_num'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( $_POST['cornerstone_card_num'] ) ) : '';
            $card_cvc   = isset( $_POST['cornerstone_card_cvc'] ) ? sanitize_text_field( $_POST['cornerstone_card_cvc'] ) : '';
            $card_month = isset( $_POST['cornerstone_exp_month'] ) ? sanitize_text_field( $_POST['cornerstone_exp_month'] ) : '';
            $card_year  = isset( $_POST['cornerstone_exp_year'] ) ? sanitize_text_field( $_POST['cornerstone_exp_year'] ) : '';

            $valid = true;

            if ( '' === $card_num || ! ctype_digit( $card_num ) || strlen( $card_num ) < 13 || strlen( $card_num ) > 16 ) {
                wc_add_notice( 'Please enter a valid card number.', 'error' );
                $valid = false;
            } elseif ( ! $this->luhn_check( $card_num ) ) {
                wc_add_notice( 'The card number entered does not appear to be valid.', 'error' );
                $valid = false;
            }

            if ( '' === $card_cvc || ! ctype_digit( $card_cvc ) || strlen( $card_cvc ) < 3 || strlen( $card_cvc ) > 4 ) {
                wc_add_notice( 'Please enter a valid CVC/CVV code.', 'error' );
                $valid = false;
            }

            if ( '' === $card_month || ! ctype_digit( $card_month ) || (int) $card_month < 1 || (int) $card_month > 12 ) {
                wc_add_notice( 'Please enter a valid expiry month (01-12).', 'error' );
                $valid = false;
            }

            if ( '' === $card_year || ! ctype_digit( $card_year ) || strlen( $card_year ) !== 2 ) {
                wc_add_notice( 'Please enter a valid 2-digit expiry year.', 'error' );
                $valid = false;
            } elseif ( $valid && ! $this->is_card_not_expired( $card_month, $card_year ) ) {
                wc_add_notice( 'The card expiry date entered has already passed.', 'error' );
                $valid = false;
            }

            return $valid;
        }

        /**
         * Standard Luhn algorithm check for card number validity.
         */
        private function luhn_check( $number ) {
            $sum     = 0;
            $alt     = false;
            for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {
                $n = (int) $number[ $i ];
                if ( $alt ) {
                    $n *= 2;
                    if ( $n > 9 ) {
                        $n -= 9;
                    }
                }
                $sum += $n;
                $alt = ! $alt;
            }
            return ( $sum % 10 === 0 );
        }

        /**
         * Confirm MM/YY expiry hasn't already passed (assumes 20YY).
         */
        private function is_card_not_expired( $month, $year ) {
            $full_year     = 2000 + (int) $year;
            $expiry_ts     = mktime( 0, 0, 0, (int) $month + 1, 1, $full_year ) - 1; // last second of expiry month
            return $expiry_ts >= time();
        }

        /**
         * Handle processing of the actual payment
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Idempotency guard: if a request is already in flight or already succeeded
            // for this order, don't fire a second API call (covers double-submit / back-button retries).
            $lock_key = '_cornerstone_payment_lock';
            $existing_lock = $order->get_meta( $lock_key );

            if ( $existing_lock === 'in_progress' ) {
                wc_add_notice( 'Your payment is already being processed. Please wait and check your order status before retrying.', 'error' );
                return;
            }

            $order->update_meta_data( $lock_key, 'in_progress' );
            $order->save();

            // Stable, per-order request ID so retries of THIS attempt are recognizable to Cornerstone
            // as the same request rather than a fresh one each time.
            $request_id = $order->get_meta( '_cornerstone_request_id' );
            if ( ! $request_id ) {
                $request_id = 'WOO-' . $order_id . '-' . wp_generate_password( 12, false );
                $order->update_meta_data( '_cornerstone_request_id', $request_id );
                $order->save();
            }

            $card_num   = isset( $_POST['cornerstone_card_num'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( $_POST['cornerstone_card_num'] ) ) : '';
            $card_cvc   = isset( $_POST['cornerstone_card_cvc'] ) ? sanitize_text_field( $_POST['cornerstone_card_cvc'] ) : '';
            $card_month = isset( $_POST['cornerstone_exp_month'] ) ? sanitize_text_field( $_POST['cornerstone_exp_month'] ) : '';
            $card_year  = isset( $_POST['cornerstone_exp_year'] ) ? sanitize_text_field( $_POST['cornerstone_exp_year'] ) : '';

            // NOTE: confirm with Cornerstone support whether `expyear` should be sent as
            // 2-digit (YY) or 4-digit (YYYY). Sending 2-digit here to match the form field;
            // change to (2000 + (int) $card_year) below if Cornerstone's API requires 4-digit.
            $api_url = 'https://api.cornerstone.cc/v1/transactions';

//             $payload = array(
//                 'amount'     => $order->get_total(),
//                 'request_id' => $request_id,
//                 'customer'   => array(
//                     'firstname' => $order->get_billing_first_name(),
//                     'lastname'  => $order->get_billing_last_name(),
//                     'email'     => $order->get_billing_email(),
//                 ),
//                 'billing_details' => array(
//                     'address' => array(
//                         'street'  => $order->get_billing_address_1(),
//                         'street2' => $order->get_billing_address_2(),
//                         'city'    => $order->get_billing_city(),
//                         'state'   => $order->get_billing_state(),
//                         'zip'     => $order->get_billing_postcode(),
//                         'country' => $order->get_billing_country(),
//                     ),
//                 ),
//                 'card' => array(
//                     'number'   => $card_num,
//                     'expmonth' => $card_month,
//                     'expyear'  => $card_year,
//                     'cvv'      => $card_cvc,
//                 ),
//             );

//             $response = wp_remote_post( $api_url, array(
//                 'method'  => 'POST',
//                 'headers' => array(
//                     'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_key ),
//                     'Content-Type'  => 'application/x-www-form-urlencoded',
//                 ),
//                 'body'    => http_build_query( $payload ),
//                 'timeout' => 45,
//             ) );
			$payload = array(
                'amount'     => $order->get_total(),
                'request_id' => $request_id,
                'customer'   => array(
                    'firstname' => $order->get_billing_first_name(),
                    'lastname'  => $order->get_billing_last_name(),
                    'email'     => $order->get_billing_email(),
                    'address'   => $order->get_billing_address_1(), // Maps billing street address
                    'company'   => $order->get_billing_address_2(), // Maps secondary address line (optional)
                    'city'      => $order->get_billing_city(),
                    'state'     => $order->get_billing_state(),
                    'zip'       => $order->get_billing_postcode(),
                    'country'   => $order->get_billing_country(),
                ),
                'card' => array(
                    'number'   => $card_num,
                    'expmonth' => $card_month,
                    'expyear'  => $card_year,
                    'cvv'      => $card_cvc,
                    'currency' => strtolower( $order->get_currency() ), // <--- Moved inside the card array
                ),
            );
            $response = wp_remote_post( $api_url, array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_key ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => http_build_query( $payload ),
                'timeout' => 45,
            ) );

            // Always release the in-progress lock once we have a definite outcome from this attempt.
            $order->update_meta_data( $lock_key, 'complete' );
            $order->save();

            if ( is_wp_error( $response ) ) {
                $order->add_order_note( 'Cornerstone payment attempt failed: connection error - ' . $response->get_error_message() );
                wc_add_notice( 'Connection error encountered while processing your payment. Please try again.', 'error' );
                return;
            }

            $http_code = wp_remote_retrieve_response_code( $response );
            $body      = json_decode( wp_remote_retrieve_body( $response ), true );
            $order->add_order_note( 'Cornerstone HTTP code: ' . $http_code );
            $order->add_order_note( 'Cornerstone raw body: ' . wp_remote_retrieve_body( $response ) );
            $order->add_order_note( 'Cornerstone payload sent: ' . json_encode( $payload ) );

            if ( isset( $body['approved'] ) ) {
                $transaction_id = isset( $body['approved']['id'] ) ? $body['approved']['id'] : '';

                // Structured, queryable meta -- not just a human-readable note.
                $order->update_meta_data( '_cornerstone_transaction_id', $transaction_id );
                $order->update_meta_data( '_cornerstone_payment_status', 'approved' );
                $order->save();

                $order->payment_complete( $transaction_id );
                $order->add_order_note( sprintf( 'Cornerstone payment approved. Transaction ID: %s', $transaction_id ) );

                WC()->cart->empty_cart();

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),
                );
            }

            // Distinguish credential/config errors from genuine customer-facing declines,
            // so order notes tell you at a glance which side the problem is on.
            $reason = isset( $body['reason'] ) ? $body['reason'] : 'Transaction declined.';

            if ( 401 === (int) $http_code || false !== stripos( (string) $reason, 'credentials' ) || false !== stripos( (string) $reason, 'auth' ) ) {
                $order->update_meta_data( '_cornerstone_payment_status', 'config_error' );
                $order->save();
                $order->add_order_note( 'Cornerstone payment failed due to a credential/configuration error: ' . $reason );
                wc_add_notice( 'Payment could not be processed due to a store configuration issue. Please contact us, or try again shortly.', 'error' );
            } else {
                $order->update_meta_data( '_cornerstone_payment_status', 'declined' );
                $order->save();
                $order->add_order_note( 'Cornerstone payment declined: ' . $reason );
                wc_add_notice( 'Payment Error: ' . esc_html( $reason ), 'error' );
            }

            return;
        }

        /**
         * Process a refund via the Cornerstone API. Hooked in via $this->supports = array('refunds').
         * WooCommerce calls this automatically when an admin clicks "Refund" on the Orders screen.
         *
         * @param int    $order_id
         * @param float  $amount
         * @param string $reason
         * @return bool|WP_Error
         */
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                return new WP_Error( 'cornerstone_refund_error', 'Order not found.' );
            }

            $transaction_id = $order->get_meta( '_cornerstone_transaction_id' );

            if ( ! $transaction_id ) {
                return new WP_Error( 'cornerstone_refund_error', 'No Cornerstone transaction ID found on this order; cannot process refund automatically.' );
            }

            // NOTE: confirm exact refund endpoint shape against the Cornerstone Quarry API docs --
            // this assumes a POST to /v1/transactions/{id}/refund with an amount field.
            $api_url = 'https://api.cornerstone.cc/v1/transactions/' . rawurlencode( $transaction_id ) . '/refund';

            $payload = array(
                'amount' => $amount !== null ? $amount : $order->get_total(),
                'reason' => $reason,
            );

            $response = wp_remote_post( $api_url, array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_key ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => http_build_query( $payload ),
                'timeout' => 45,
            ) );

            if ( is_wp_error( $response ) ) {
                $order->add_order_note( 'Cornerstone refund attempt failed: connection error - ' . $response->get_error_message() );
                return new WP_Error( 'cornerstone_refund_error', 'Connection error while contacting Cornerstone for refund.' );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
$order->add_order_note( 'Cornerstone raw response: ' . print_r( $body, true ) );

            if ( isset( $body['approved'] ) ) {
                $order->add_order_note( sprintf( 'Cornerstone refund approved for amount %s. Refund reference: %s', $amount, isset( $body['approved']['id'] ) ? $body['approved']['id'] : 'n/a' ) );
                return true;
            }

            $reason_msg = isset( $body['reason'] ) ? $body['reason'] : 'Refund declined by Cornerstone.';
            $order->add_order_note( 'Cornerstone refund failed: ' . $reason_msg );
            return new WP_Error( 'cornerstone_refund_error', $reason_msg );
        }
    }
}

// Add custom class to WooCommerce list outside of the class initialization scope
add_filter( 'woocommerce_payment_gateways', 'add_cornerstone_quarry_gateway_class' );
function add_cornerstone_quarry_gateway_class( $methods ) {
    if ( class_exists( 'WC_Payment_Gateway' ) ) {
        $methods[] = 'WC_Cornerstone_Quarry_Gateway';
    }
    return $methods;
}