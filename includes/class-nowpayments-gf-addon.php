<?php
/**
 * NOWPayments Gravity Forms Payment Add-On.
 *
 * @package NOWPayments_GF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NowPayments_GF_AddOn class.
 */
class NowPayments_GF_AddOn extends GFPaymentAddOn {

	/**
	 * Singleton instance.
	 *
	 * @var NowPayments_GF_AddOn|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$slug = 'nowpayments-for-gravity-forms';

		$this->_version                  = defined( 'NOWPAYMENTS_GF_VERSION' ) ? NOWPAYMENTS_GF_VERSION : '1.0.0';
		$this->_min_gravityforms_version = '2.5';
		$this->_slug                     = $slug;
		$this->_path                     = 'nowpayments-for-gravity-forms/nowpayments-for-gravity-forms.php';
		$this->_full_path                = defined( 'NOWPAYMENTS_GF_FILE' ) ? NOWPAYMENTS_GF_FILE : __FILE__;
		// Avoid early translation loading (WP 6.7+); translate in getters after init.
		$this->_title                = 'NOWPayments';
		$this->_short_title          = 'NOWPayments';
		$this->_supports_callbacks   = true;
		$this->_requires_credit_card = false;

		parent::__construct();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return NowPayments_GF_AddOn
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Title (translated only after init — WP 6.7+).
	 *
	 * @return string
	 */
	public function get_title() {
		return did_action( 'init' ) ? __( 'NOWPayments', 'nowpayments-for-gravity-forms' ) : 'NOWPayments';
	}

	/**
	 * Short title (translated only after init — WP 6.7+).
	 *
	 * @return string
	 */
	public function get_short_title() {
		return did_action( 'init' ) ? __( 'NOWPayments', 'nowpayments-for-gravity-forms' ) : 'NOWPayments';
	}

	/**
	 * Menu icon for Settings tab (NOWPayments logo).
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		$base = rtrim( NOWPAYMENTS_GF_URL, '/' );
		return $base . '/assets/nowpayments-logo.png';
	}

	/**
	 * Plugin settings fields (API keys, sandbox, currency).
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		$fields = array(
			array(
				'title'  => __( 'NOWPayments Settings', 'nowpayments-for-gravity-forms' ),
				'fields' => array(
					array(
						'name'          => 'gateway_environment',
						'label'         => __( 'Mode', 'nowpayments-for-gravity-forms' ),
						'type'          => 'radio',
						'choices'       => array(
							array(
								'label' => __( 'Live', 'nowpayments-for-gravity-forms' ),
								'value' => 'live',
							),
							array(
								'label' => __( 'Sandbox', 'nowpayments-for-gravity-forms' ),
								'value' => 'sandbox',
							),
						),
						'default_value' => 'sandbox',
					),
					array(
						'name'  => 'live_api_key',
						'label' => __( 'Live API Key', 'nowpayments-for-gravity-forms' ),
						'type'  => 'text',
						'input_type' => 'password',
						'class' => 'large',
					),
					array(
						'name'  => 'live_ipn_secret',
						'label' => __( 'Live IPN Secret Key', 'nowpayments-for-gravity-forms' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'  => 'sandbox_api_key',
						'label' => __( 'Sandbox API Key', 'nowpayments-for-gravity-forms' ),
						'type'  => 'text',
						'input_type' => 'password',
						'class' => 'large',
					),
					array(
						'name'  => 'sandbox_ipn_secret',
						'label' => __( 'Sandbox IPN Secret Key', 'nowpayments-for-gravity-forms' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'          => 'currency',
						'label'         => __( 'Currency', 'nowpayments-for-gravity-forms' ),
						'type'          => 'text',
						'default_value' => 'USD',
						'description'   => __( 'Default payment currency (e.g. USD, EUR).', 'nowpayments-for-gravity-forms' ),
					),
					array(
						'name'          => 'webhook_url',
						'label'         => __( 'Webhook URL', 'nowpayments-for-gravity-forms' ),
						'type'          => 'text',
						'description'   => __( 'Use this URL in your NOWPayments store IPN settings.', 'nowpayments-for-gravity-forms' ),
						'readonly'      => true,
						'default_value' => add_query_arg( 'action', 'nowpayments_gf_webhook', admin_url( 'admin-ajax.php' ) ),
					),
				),
			),
		);

		return $fields;
	}

	/**
	 * Feed settings fields. Remove subscription when not supported.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		$fields = parent::feed_settings_fields();

		// Remove subscription transaction type/settings if subscription handler is unavailable.
		if ( ! class_exists( 'NowPayments_GF_Subscription' ) ) {
			$fields = $this->remove_subscription_settings( $fields );
		}

		return $fields;
	}

	/**
	 * Remove subscription options from feed settings (when unsupported).
	 *
	 * @param array $fields Fields array.
	 * @return array
	 */
	protected function remove_subscription_settings( $fields ) {
		foreach ( $fields as $key => $field ) {
			if ( isset( $field['name'] ) && 'transactionType' === $field['name'] && ! empty( $field['choices'] ) ) {
				$fields[ $key ]['choices'] = array_filter(
					$field['choices'],
					function ( $choice ) {
						return rgar( $choice, 'value' ) !== 'subscription';
					}
				);
			}
			if ( isset( $field['title'] ) && __( 'Subscription Settings', 'gravityforms' ) === $field['title'] ) {
				unset( $fields[ $key ] );
				continue;
			}
			if ( ! empty( $field['fields'] ) ) {
				$fields[ $key ]['fields'] = $this->remove_subscription_settings( $field['fields'] );
			}
		}
		return array_values( $fields );
	}

	/**
	 * Get redirect URL for off-site payment (Products and Services only for now).
	 *
	 * @param array $feed Active payment feed.
	 * @param array $submission_data Submission data.
	 * @param array $form Form object.
	 * @param array $entry Entry object.
	 * @return string
	 */
	public function redirect_url( $feed, $submission_data, $form, $entry ) {
		$transaction_type = rgar( $feed, 'meta/transactionType', 'product' );
		if ( 'subscription' === $transaction_type ) {
			if ( class_exists( 'NowPayments_GF_Subscription' ) && method_exists( 'NowPayments_GF_Subscription', 'get_redirect_url' ) ) {
				return NowPayments_GF_Subscription::get_redirect_url( $feed, $submission_data, $form, $entry, $this->get_api() );
			}
			return add_query_arg( 'error', 'subscription_not_supported', $this->get_cancel_url( $form ) );
		}

		$api = $this->get_api();
		if ( ! $api ) {
			return add_query_arg( 'error', 'api_config', home_url( '/' ) );
		}

		$url = NowPayments_GF_Simple_Payment::get_redirect_url( $feed, $submission_data, $form, $entry, $api );
		if ( ! $url ) {
			return add_query_arg( 'error', 'payment_create', home_url( '/' ) );
		}

		// Add a pending note so the entry has a record immediately (Square-like notes).
		$amount           = rgar( $submission_data, 'payment_amount', 0 );
		$currency         = NowPayments_GF_Simple_Payment::get_currency( $feed );
		$amount_formatted = $amount ? GFCommon::to_money( $amount, $entry['currency'] ) : ( $currency ? $currency : '' );
		$order_id         = rgar( $entry, 'id' ) ? $entry['id'] : '';
		$note             = sprintf(
			/* translators: 1: amount 2: order id */
			__( 'Payment initiated. Amount: %1$s. Order Id: %2$s. Status: Pending.', 'nowpayments-for-gravity-forms' ),
			$amount_formatted ? $amount_formatted : __( 'N/A', 'nowpayments-for-gravity-forms' ),
			$order_id ? $order_id : __( 'N/A', 'nowpayments-for-gravity-forms' )
		);
		$this->add_note( $entry['id'], $note );
		if ( $order_id ) {
			gform_update_meta( $entry['id'], 'nowpayments_order_id', $order_id, $form['id'] );
		}
		gform_update_meta( $entry['id'], 'nowpayments_payment_status', 'Pending', $form['id'] );
		if ( $amount ) {
			gform_update_meta( $entry['id'], 'nowpayments_amount', (float) $amount, $form['id'] );
		}
		if ( $currency ) {
			gform_update_meta( $entry['id'], 'nowpayments_pay_currency', $currency, $form['id'] );
		}
		gform_update_meta( $entry['id'], 'nowpayments_payment_date', gmdate( 'Y-m-d H:i:s' ), $form['id'] );

		// Update entry properties so GF shows Payment Details section on entry view.
		if ( rgar( $entry, 'id' ) ) {
			GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Pending' );
			if ( $amount ) {
				GFAPI::update_entry_property( $entry['id'], 'payment_amount', (float) $amount );
			}
			if ( $order_id ) {
				GFAPI::update_entry_property( $entry['id'], 'transaction_id', (string) $order_id );
			}
			GFAPI::update_entry_property( $entry['id'], 'payment_date', gmdate( 'Y-m-d H:i:s' ) );
		}

		return $url;
	}

	/**
	 * Get API instance based on plugin settings.
	 *
	 * @return NowPayments_GF_API|null
	 */
	public function get_api() {
		$settings = $this->get_plugin_settings();
		if ( empty( $settings ) ) {
			return null;
		}
		$is_live = rgar( $settings, 'gateway_environment' ) !== 'sandbox';
		$key     = $is_live ? rgar( $settings, 'live_api_key' ) : rgar( $settings, 'sandbox_api_key' );
		if ( empty( $key ) ) {
			return null;
		}
		return new NowPayments_GF_API( $is_live, $key );
	}

	/**
	 * Get cancel/return URL for form.
	 *
	 * @param array $form Form.
	 * @return string
	 */
	protected function get_cancel_url( $form ) {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		return $referer ? $referer : home_url( '/' );
	}

	/**
	 * Handle IPN callback (webhook).
	 *
	 * @return void
	 */
	public function callback() {
		$raw  = file_get_contents( 'php://input' );
		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
			status_header( 400 );
			echo 'Invalid JSON';
			exit;
		}

		$settings   = $this->get_plugin_settings();
		$is_live    = rgar( $settings, 'gateway_environment' ) !== 'sandbox';
		$ipn_secret = $is_live ? rgar( $settings, 'live_ipn_secret' ) : rgar( $settings, 'sandbox_ipn_secret' );
		if ( ! empty( $ipn_secret ) && ! $this->verify_ipn_signature( $raw, $data, $ipn_secret ) ) {
			status_header( 401 );
			echo 'Invalid signature';
			exit;
		}

		$payment_status = isset( $data['payment_status'] ) ? strtoupper( $data['payment_status'] ) : '';
		$order_id       = isset( $data['order_id'] ) ? $data['order_id'] : 0;

		// Resolve entry from order_id (we send entry id or a temporary id).
		$entry_id = is_numeric( $order_id ) ? (int) $order_id : 0;
		if ( $entry_id <= 0 ) {
			status_header( 400 );
			echo 'Missing order_id';
			exit;
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || empty( $entry ) ) {
			status_header( 404 );
			echo 'Entry not found';
			exit;
		}

		if ( ! $this->is_payment_gateway( $entry['id'] ) ) {
			status_header( 400 );
			echo 'Not a NOWPayments entry';
			exit;
		}

		$result = array(
			'id'               => isset( $data['payment_id'] ) ? $data['payment_id'] : '',
			'transaction_id'   => isset( $data['payment_id'] ) ? $data['payment_id'] : '',
			'amount'           => isset( $data['actually_paid'] ) ? (float) $data['actually_paid'] : 0,
			'entry_id'         => $entry['id'],
			'payment_status'   => 'Paid',
			'payment_date'     => gmdate( 'Y-m-d H:i:s' ),
			'type'             => 'complete_payment',
			'transaction_type' => 'payment',
		);

		$currency         = isset( $data['pay_currency'] ) ? sanitize_text_field( $data['pay_currency'] ) : '';
		$amount_formatted = $result['amount'] > 0
			? GFCommon::to_money( $result['amount'], $entry['currency'] )
			: ( isset( $data['price_amount'] ) && $currency ? $data['price_amount'] . ' ' . $currency : '' );
		$payment_id       = isset( $data['payment_id'] ) ? $data['payment_id'] : '';
		$status_label     = $payment_status ? $payment_status : 'UNKNOWN';

		switch ( $payment_status ) {
			case 'CONFIRMED':
			case 'COMPLETED':
				$result['note'] = sprintf(
					/* translators: 1: amount 2: transaction id 3: status */
					__( 'Payment completed. Amount: %1$s. Transaction Id: %2$s. Status: %3$s.', 'nowpayments-for-gravity-forms' ),
					$amount_formatted ? $amount_formatted : __( 'N/A', 'nowpayments-for-gravity-forms' ),
					$payment_id ? $payment_id : __( 'N/A', 'nowpayments-for-gravity-forms' ),
					$status_label
				);
				$this->complete_payment( $entry, $result );
				break;
			case 'FAILED':
			case 'EXPIRED':
				$result['type']  = 'fail_payment';
				$result['note']  = isset( $data['message'] ) ? $data['message'] : __( 'Payment failed or expired.', 'nowpayments-for-gravity-forms' );
				$result['note'] .= $payment_id ? sprintf( ' Transaction Id: %s.', $payment_id ) : '';
				$this->fail_payment( $entry, $result );
				break;
			case 'REFUNDED':
				$result['type'] = 'refund_payment';
				$result['note'] = sprintf(
					/* translators: 1: amount 2: transaction id */
					__( 'Payment refunded. Amount: %1$s. Transaction Id: %2$s.', 'nowpayments-for-gravity-forms' ),
					$amount_formatted ? $amount_formatted : __( 'N/A', 'nowpayments-for-gravity-forms' ),
					$payment_id ? $payment_id : __( 'N/A', 'nowpayments-for-gravity-forms' )
				);
				$this->refund_payment( $entry, $result );
				break;
		}

		status_header( 200 );
		echo 'OK';
		exit;
	}

	/**
	 * Verify IPN signature (X-NOWPayments-Sig).
	 *
	 * @param string $raw Raw JSON body.
	 * @param array  $data Decoded data.
	 * @param string $ipn_secret IPN secret.
	 * @return bool
	 */
	protected function verify_ipn_signature( $raw, $data, $ipn_secret ) {
		if ( empty( $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ) ) {
			return false;
		}
		$received   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ) );
		$sorted     = $this->sort_array_recursive( $data );
		$calculated = hash_hmac( 'sha512', wp_json_encode( $sorted, JSON_UNESCAPED_SLASHES ), trim( $ipn_secret ) );
		return hash_equals( $calculated, $received );
	}

	/**
	 * Recursively sort array by keys.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	protected function sort_array_recursive( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		ksort( $data );
		foreach ( $data as $k => $v ) {
			$data[ $k ] = $this->sort_array_recursive( $v );
		}
		return $data;
	}

	/**
	 * Register AJAX handler for webhook (no-priv and priv).
	 */
	public function init_ajax() {
		parent::init_ajax();
		add_action( 'wp_ajax_nopriv_nowpayments_gf_webhook', array( $this, 'handle_webhook_ajax' ) );
		add_action( 'wp_ajax_nowpayments_gf_webhook', array( $this, 'handle_webhook_ajax' ) );
	}

	/**
	 * Handle webhook via admin-ajax (callback URL).
	 */
	public function handle_webhook_ajax() {
		$this->callback();
	}
}
