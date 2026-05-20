<?php
/**
 * NOWPayments API class for Gravity Forms.
 *
 * @package NOWPayments_GF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NowPayments_GF_API class.
 */
class NowPayments_GF_API {

	/**
	 * Is Live mode.
	 *
	 * @var bool
	 */
	private $is_live;

	/**
	 * Payment URL base.
	 *
	 * @var string
	 */
	private $payment_url;

	/**
	 * REST API base.
	 *
	 * @var string
	 */
	private $api_base;

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param bool   $is_live Is live mode.
	 * @param string $api_key API key.
	 */
	public function __construct( $is_live = true, $api_key = '' ) {
		$this->is_live     = (bool) $is_live;
		$this->api_key     = is_string( $api_key ) ? trim( $api_key ) : '';
		$this->payment_url = $this->is_live ? 'https://nowpayments.io' : 'https://sandbox.nowpayments.io';
		$this->api_base    = $this->is_live ? 'https://api.nowpayments.io/v1' : 'https://api-sandbox.nowpayments.io/v1';
	}

	/**
	 * Perform an authenticated API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path under /v1 (e.g. payment or payment/123).
	 * @param array  $query  Query parameters.
	 * @return array|WP_Error Decoded JSON body or error.
	 */
	public function api_request( $method, $path, $query = array() ) {
		if ( '' === $this->api_key ) {
			return new WP_Error( 'nowpayments_api_error', __( 'NOWPayments API key is not configured.', 'nowpayments-for-gravity-forms' ) );
		}

		$url = trailingslashit( $this->api_base ) . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => array(
					'X-API-KEY' => $this->api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : wp_remote_retrieve_response_message( $response );
			return new WP_Error( 'nowpayments_api_error', $message ? $message : 'API request failed', array( 'status' => $code ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Get a single payment by NOWPayments payment ID.
	 *
	 * @param int|string $payment_id Payment ID.
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		$payment_id = absint( $payment_id );
		if ( $payment_id <= 0 ) {
			return new WP_Error( 'nowpayments_api_error', __( 'Invalid payment ID.', 'nowpayments-for-gravity-forms' ) );
		}

		return $this->api_request( 'GET', 'payment/' . $payment_id );
	}

	/**
	 * List payments (filter by order_id, payment_status, etc.).
	 *
	 * @param array $params Query parameters.
	 * @return array|WP_Error
	 */
	public function list_payments( $params = array() ) {
		return $this->api_request( 'GET', 'payment', $params );
	}

	/**
	 * Create off-page checkout URL.
	 *
	 * @param array $parameters Checkout parameters.
	 * @return string
	 */
	public function off_page_checkout( $parameters = array() ) {
		$parameters['apiKey'] = $this->api_key;
		$encoded_parameters   = rawurlencode( wp_json_encode( $parameters ) );
		return $this->payment_url . '/payment/?data=' . $encoded_parameters;
	}
}
