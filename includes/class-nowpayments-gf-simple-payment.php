<?php
/**
 * NOWPayments simple (one-time) payment handler for Gravity Forms.
 *
 * @package NOWPayments_GF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NowPayments_GF_Simple_Payment class.
 */
class NowPayments_GF_Simple_Payment {

	/**
	 * Build redirect URL for one-time payment.
	 *
	 * @param array  $feed Active payment feed.
	 * @param array  $submission_data Submission data.
	 * @param array  $form Form object.
	 * @param array  $entry Entry (may not have ID yet).
	 * @param object $api  NowPayments_GF_API instance.
	 * @return string|false Redirect URL or false on failure.
	 */
	public static function get_redirect_url( $feed, $submission_data, $form, $entry, $api ) {
		$amount = rgar( $submission_data, 'payment_amount', 0 );
		if ( (float) $amount <= 0 ) {
			return false;
		}

		$currency = self::get_currency( $feed );
		if ( rgar( $submission_data, 'line_items' ) ) {
			$product_name = self::format_product_name( $submission_data['line_items'] );
		} else {
			/* translators: %d is the Gravity Forms form ID. */
			$product_name = sprintf( __( 'Form #%d', 'nowpayments-for-gravity-forms' ), (int) $form['id'] );
		}

		$order_id = rgar( $entry, 'id' ) ? $entry['id'] : ( 'gf_nowpayments_' . uniqid( '', true ) );

		$parameters = array(
			'dataSource'      => 'gravity-forms',
			'ipnURL'          => self::get_webhook_url(),
			'paymentCurrency' => $currency,
			'successURL'      => self::get_success_url( $entry, $form ),
			'cancelURL'       => self::get_cancel_url( $form ),
			'orderID'         => $order_id,
			'customerName'    => self::get_customer_name( $submission_data ),
			'customerEmail'   => self::get_customer_email( $submission_data ),
			'paymentAmount'   => number_format( (float) $amount, 8, '.', '' ),
			'productName'     => $product_name,
		);

		$parameters = apply_filters( 'nowpayments_gf_simple_payment_parameters', $parameters, $feed, $submission_data, $form, $entry );

		return $api->off_page_checkout( $parameters );
	}

	/**
	 * Get currency from feed or plugin settings.
	 *
	 * @param array $feed Feed object.
	 * @return string
	 */
	public static function get_currency( $feed ) {
		unset( $feed ); // Intentionally unused.
		$addon    = NowPayments_GF_AddOn::get_instance();
		$settings = $addon->get_plugin_settings();
		$currency = rgar( $settings, 'currency' );
		if ( empty( $currency ) ) {
			$currency = 'USD';
		}
		return $currency;
	}

	/**
	 * Format product name from line items.
	 *
	 * @param array $line_items Line items.
	 * @return string
	 */
	protected static function format_product_name( $line_items ) {
		$names = array();
		foreach ( $line_items as $item ) {
			if ( ! empty( $item['name'] ) ) {
				$names[] = $item['name'];
			}
		}
		return $names ? implode( ', ', $names ) : __( 'Order', 'nowpayments-for-gravity-forms' );
	}

	/**
	 * Get webhook URL.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg( 'action', 'nowpayments_gf_webhook', admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Get success URL (return from NOWPayments).
	 *
	 * @param array $entry Entry.
	 * @param array $form Form.
	 * @return string
	 */
	protected static function get_success_url( $entry, $form ) {
		$confirmation_url = GFCommon::replace_variables( '{embed_url}', $form, $entry );
		if ( empty( $confirmation_url ) ) {
			$confirmation_url = home_url( '/' );
		}
		return add_query_arg(
			array(
				'nowpayments_gf_return' => 1,
				'entry_id'              => rgar( $entry, 'id' ),
				'form_id'               => rgar( $form, 'id' ),
			),
			$confirmation_url
		);
	}

	/**
	 * Get cancel URL.
	 *
	 * @param array $form Form.
	 * @return string
	 */
	protected static function get_cancel_url( $form ) {
		unset( $form ); // Intentionally unused.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		return $referer ? $referer : home_url( '/' );
	}

	/**
	 * Get customer name from submission data.
	 *
	 * @param array $submission_data Submission data.
	 * @return string
	 */
	public static function get_customer_name( $submission_data ) {
		$billing = rgar( $submission_data, 'billing', array() );
		$first   = rgar( $billing, 'first_name', '' );
		$last    = rgar( $billing, 'last_name', '' );
		return trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : __( 'Customer', 'nowpayments-for-gravity-forms' );
	}

	/**
	 * Get customer email from submission data.
	 *
	 * @param array $submission_data Submission data.
	 * @return string
	 */
	public static function get_customer_email( $submission_data ) {
		$billing = rgar( $submission_data, 'billing', array() );
		$email   = rgar( $billing, 'email', '' );
		if ( empty( $email ) && ! empty( $submission_data['email'] ) ) {
			$email = $submission_data['email'];
		}
		return $email ? $email : '';
	}
}
