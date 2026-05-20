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
						'name'       => 'live_api_key',
						'label'      => __( 'Live API Key', 'nowpayments-for-gravity-forms' ),
						'type'       => 'text',
						'input_type' => 'password',
						'class'      => 'large',
					),
					array(
						'name'  => 'live_ipn_secret',
						'label' => __( 'Live IPN Secret Key', 'nowpayments-for-gravity-forms' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'       => 'sandbox_api_key',
						'label'      => __( 'Sandbox API Key', 'nowpayments-for-gravity-forms' ),
						'type'       => 'text',
						'input_type' => 'password',
						'class'      => 'large',
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
						'default_value' => $this->get_ipn_webhook_url(),
					),
				),
			),
		);

		$fields = apply_filters( 'nowpayments_gf_plugin_settings_fields', $fields, $this );

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
		nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] redirect_url: transaction_type=' . $transaction_type . ' form_id=' . ( isset( $form['id'] ) ? $form['id'] : '' ) );
		if ( 'subscription' === $transaction_type ) {
			$api = $this->get_api();
			if ( ! $api ) {
				nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] redirect_url: subscription but get_api() null -> error=api_config' );
				return add_query_arg( 'error', 'api_config', home_url( '/' ) );
			}

			$url = apply_filters( 'nowpayments_gf_subscription_redirect_url', '', $feed, $submission_data, $form, $entry, $api, $this );
			if ( $url ) {
				nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] redirect_url: subscription URL from filter: ' . $url );
				return $url;
			}

			if ( class_exists( 'NowPayments_GF_Subscription' ) && method_exists( 'NowPayments_GF_Subscription', 'get_redirect_url' ) ) {
				$url = NowPayments_GF_Subscription::get_redirect_url( $feed, $submission_data, $form, $entry, $api, $this );
				nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] redirect_url: subscription get_redirect_url returned: ' . ( is_string( $url ) ? $url : wp_json_encode( $url ) ) );
				return $url;
			}
			nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] redirect_url: subscription class/method not found -> error=subscription_not_supported' );
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

		// Add a pending note so the entry has a record immediately.
		$amount           = rgar( $submission_data, 'payment_amount', 0 );
		$currency         = NowPayments_GF_Simple_Payment::get_currency( $feed );
		$amount_formatted = $amount ? GFCommon::to_money( $amount, $entry['currency'] ) : ( $currency ? $currency : '' );
		$order_id         = rgar( $entry, 'id' ) ? (string) $entry['id'] : '';
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
	 * Create a subscription during validation (GF payment add-on flow).
	 *
	 * @param array $feed Active payment feed.
	 * @param array $submission_data Submission data.
	 * @param array $form Form object.
	 * @param array $entry Entry object (not saved yet).
	 * @return array
	 */
	public function subscribe( $feed, $submission_data, $form, $entry ) {
		nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] subscribe() called. form_id=' . ( isset( $form['id'] ) ? $form['id'] : '' ) . ' payment_amount=' . rgar( $submission_data, 'payment_amount', 0 ) );
		$result = apply_filters( 'nowpayments_gf_subscription_request', null, $feed, $submission_data, $form, $entry, $this );
		if ( is_array( $result ) ) {
			nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] subscribe() returning from filter: ' . wp_json_encode( $result ) );
			return $result;
		}

		if ( class_exists( 'NowPayments_GF_Subscription' ) && method_exists( 'NowPayments_GF_Subscription', 'subscribe' ) ) {
			$result = NowPayments_GF_Subscription::subscribe( null, $feed, $submission_data, $form, $entry, $this );
			nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] subscribe() Subscription::subscribe result: ' . wp_json_encode( $result ) );
			return $result;
		}

		nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] subscribe() subscriptions not enabled' );
		return array(
			'is_success'    => false,
			'error_message' => __( 'Subscriptions are not enabled for NOWPayments.', 'nowpayments-for-gravity-forms' ),
		);
	}

	/**
	 * For off-site subscription we must set redirect_url here; GF only calls redirect_url() for one-time payments.
	 * confirmation() uses $this->redirect_url to send the user to NOWPayments.
	 *
	 * @param array $authorization   Authorization from subscribe().
	 * @param array $feed            Active payment feed.
	 * @param array $submission_data Submission data.
	 * @param array $form            Form object.
	 * @param array $entry           Entry object.
	 * @return array Entry.
	 */
	public function process_subscription( $authorization, $feed, $submission_data, $form, $entry ) {
		if ( ! empty( $authorization['subscription']['is_success'] ) ) {
			$api = $this->get_api();
			if ( $api ) {
				$this->redirect_url = $this->redirect_url( $feed, $submission_data, $form, $entry );
				nowpayments_gf_debug_log( '[NOWPayments-GF-DEBUG] process_subscription set redirect_url: ' . ( $this->redirect_url ? $this->redirect_url : '(empty)' ) );
			}
		}
		return parent::process_subscription( $authorization, $feed, $submission_data, $form, $entry );
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

		$key = $is_live ? rgar( $settings, 'live_api_key' ) : rgar( $settings, 'sandbox_api_key' );
		if ( rgblank( $key ) ) {
			$key = $this->get_plugin_setting( $is_live ? 'live_api_key' : 'sandbox_api_key' );
		}
		$key = is_string( $key ) ? trim( $key ) : '';
		if ( '' === $key ) {
			return null;
		}

		/**
		 * Filter NOWPayments API credentials.
		 *
		 * @param array $credentials Keys: api_key, is_live.
		 */
		$credentials = apply_filters(
			'nowpayments_gf_api_credentials',
			array(
				'api_key' => $key,
				'is_live' => $is_live,
			),
			$this
		);

		return new NowPayments_GF_API( $credentials['is_live'], $credentials['api_key'] );
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
	 * Bootstrap hooks after add-on init.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();
		add_action( 'rest_api_init', array( $this, 'register_ipn_rest_route' ) );
		add_action( 'template_redirect', array( $this, 'maybe_sync_payment_on_return' ), 5 );
	}

	/**
	 * Public webhook URL (REST API) for NOWPayments IPN.
	 *
	 * @return string
	 */
	public function get_ipn_webhook_url() {
		return rest_url( 'nowpayments-gf/v1/ipn' );
	}

	/**
	 * Register IPN route. Authorization is IPN HMAC (see process handler), not a logged-in nonce.
	 *
	 * @return void
	 */
	public function register_ipn_rest_route() {
		register_rest_route(
			'nowpayments-gf/v1',
			'/ipn',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_ipn_rest_request' ),
				'permission_callback' => array( $this, 'ipn_rest_permission_check' ),
			)
		);
	}

	/**
	 * IPN is server-to-server; capability checks are not applicable. Access is gated by verify_ipn_signature().
	 *
	 * @param WP_REST_Request $request Request (unused; required by REST API).
	 * @return true
	 */
	public function ipn_rest_permission_check( $request ) {
		unset( $request );
		return true;
	}

	/**
	 * REST handler for NOWPayments IPN webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_ipn_rest_request( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return new WP_Error(
				'nowpayments_ipn_error',
				'Invalid request',
				array( 'status' => 400 )
			);
		}

		$raw    = $request->get_body();
		$result = $this->process_nowpayments_ipn( $raw );

		if ( $result['http_status'] >= 400 ) {
			return new WP_Error(
				'nowpayments_ipn_failed',
				$result['body'],
				array( 'status' => $result['http_status'] )
			);
		}

		return new WP_REST_Response( $result['body'], $result['http_status'] );
	}

	/**
	 * Show payment/subscription error on the NOWPayments field (GF default looks for creditcard only).
	 *
	 * @param array $validation_result    Contains the form validation results.
	 * @param array $authorization_result Contains the form authorization results.
	 * @return array
	 */
	public function get_validation_result( $validation_result, $authorization_result ) {
		$credit_card_page = 0;
		$error_message    = rgar( $authorization_result, 'error_message', '' );
		if ( empty( $error_message ) ) {
			$error_message = __( 'There was a problem with your payment. Please try again.', 'nowpayments-for-gravity-forms' );
		}
		foreach ( $validation_result['form']['fields'] as &$field ) {
			if ( 'nowpayments' === $field->type ) {
				$field->failed_validation  = true;
				$field->validation_message = $error_message;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms core property name.
				$credit_card_page = (int) $field->pageNumber;
				break;
			}
		}
		$validation_result['credit_card_page'] = $credit_card_page;
		$validation_result['is_valid']         = false;
		return $validation_result;
	}

	/**
	 * Gravity Forms payment framework callback (?callback=slug). IPN is handled via REST only.
	 *
	 * @return array|false Always false; do not process gateway IPN here.
	 */
	public function callback() {
		return false;
	}

	/**
	 * Process NOWPayments IPN body (JSON). Authorization: IPN secret + X-NOWPayments-Sig HMAC on raw payload.
	 *
	 * @param string $raw Raw request body (JSON).
	 * @return array{http_status:int,body:string}
	 */
	protected function process_nowpayments_ipn( $raw ) {
		if ( ! is_string( $raw ) ) {
			$raw = '';
		}

		$max_len = (int) apply_filters( 'nowpayments_gf_ipn_max_body_length', 1048576 );
		if ( $max_len > 0 && strlen( $raw ) > $max_len ) {
			return array(
				'http_status' => 413,
				'body'        => 'Payload too large',
			);
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) || empty( $data ) ) {
			return array(
				'http_status' => 400,
				'body'        => 'Invalid JSON',
			);
		}

		$settings   = $this->get_plugin_settings();
		$is_live    = rgar( $settings, 'gateway_environment' ) !== 'sandbox';
		$ipn_secret = $is_live ? rgar( $settings, 'live_ipn_secret' ) : rgar( $settings, 'sandbox_ipn_secret' );
		$ipn_secret = is_string( $ipn_secret ) ? trim( $ipn_secret ) : '';

		if ( '' === $ipn_secret ) {
			return array(
				'http_status' => 401,
				'body'        => 'IPN secret not configured',
			);
		}

		if ( ! $this->verify_ipn_signature( $raw, $data, $ipn_secret ) ) {
			nowpayments_gf_debug_log( '[NOWPayments-GF] IPN rejected: invalid signature for entry order_id=' . ( isset( $data['order_id'] ) ? $data['order_id'] : '' ) );
			return array(
				'http_status' => 401,
				'body'        => 'Invalid signature',
			);
		}

		nowpayments_gf_debug_log( '[NOWPayments-GF] IPN received: payment_status=' . ( isset( $data['payment_status'] ) ? $data['payment_status'] : '' ) . ' order_id=' . ( isset( $data['order_id'] ) ? $data['order_id'] : '' ) . ' payment_id=' . ( isset( $data['payment_id'] ) ? $data['payment_id'] : '' ) );

		$entry_id = $this->resolve_ipn_entry_id( $data );

		if ( $entry_id <= 0 ) {
			return array(
				'http_status' => 400,
				'body'        => 'Missing order_id',
			);
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || empty( $entry ) ) {
			return array(
				'http_status' => 404,
				'body'        => 'Entry not found',
			);
		}

		if ( ! $this->is_payment_gateway( $entry['id'] ) && ! $this->entry_uses_nowpayments( $entry ) ) {
			return array(
				'http_status' => 400,
				'body'        => 'Not a NOWPayments entry',
			);
		}

		if ( ! $this->is_payment_gateway( $entry['id'] ) ) {
			gform_update_meta( $entry['id'], 'payment_gateway', $this->get_slug() );
		}

		$subscription_handled = apply_filters( 'nowpayments_gf_handle_subscription_webhook', false, $data, $entry, $this );
		if ( $subscription_handled ) {
			return array(
				'http_status' => 200,
				'body'        => 'OK',
			);
		}

		$this->apply_ipn_payment_update( $entry, $data );

		return array(
			'http_status' => 200,
			'body'        => 'OK',
		);
	}

	/**
	 * Resolve Gravity Forms entry ID from IPN payload.
	 *
	 * @param array $data IPN payload.
	 * @return int
	 */
	protected function resolve_ipn_entry_id( $data ) {
		$order_keys = array( 'order_id', 'orderID' );

		foreach ( $order_keys as $key ) {
			if ( ! isset( $data[ $key ] ) || $data[ $key ] === null || $data[ $key ] === '' ) {
				continue;
			}

			$raw = sanitize_text_field( (string) $data[ $key ] );
			if ( is_numeric( $raw ) ) {
				return absint( $raw );
			}

			$entry_id = $this->find_entry_id_by_nowpayments_order_id( $raw );
			if ( $entry_id > 0 ) {
				return $entry_id;
			}
		}

		return 0;
	}

	/**
	 * Find entry by stored NOWPayments order id meta.
	 *
	 * @param string $order_id Order id from gateway.
	 * @return int
	 */
	protected function find_entry_id_by_nowpayments_order_id( $order_id ) {
		global $wpdb;

		if ( '' === $order_id ) {
			return 0;
		}

		$table = GFFormsModel::get_entry_meta_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT entry_id FROM %i WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$table,
				'nowpayments_order_id',
				$order_id
			)
		);

		return $entry_id ? absint( $entry_id ) : 0;
	}

	/**
	 * Whether the entry's form uses a NOWPayments field.
	 *
	 * @param array $entry Entry.
	 * @return bool
	 */
	protected function entry_uses_nowpayments( $entry ) {
		$form_id = absint( rgar( $entry, 'form_id' ) );
		if ( $form_id <= 0 ) {
			return false;
		}

		$form = GFAPI::get_form( $form_id );
		if ( empty( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( is_object( $field ) && isset( $field->type ) && 'nowpayments' === $field->type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize payment status from IPN payload.
	 *
	 * @param array $data IPN payload.
	 * @return string Uppercase status or empty string.
	 */
	protected function get_ipn_payment_status( $data ) {
		$status = '';

		if ( isset( $data['payment_status'] ) && $data['payment_status'] !== null && $data['payment_status'] !== '' ) {
			$status = (string) $data['payment_status'];
		} elseif ( isset( $data['status'] ) && $data['status'] !== null && $data['status'] !== '' ) {
			$status = (string) $data['status'];
		}

		return $status ? strtoupper( sanitize_text_field( $status ) ) : '';
	}

	/**
	 * Apply IPN status to a Gravity Forms entry.
	 *
	 * @param array $entry Entry (updated in place when marked paid).
	 * @param array $data  IPN or API payment payload.
	 * @return void
	 */
	protected function apply_ipn_payment_update( &$entry, $data ) {
		$payment_status = $this->get_ipn_payment_status( $data );

		$pay_currency = isset( $data['pay_currency'] ) ? sanitize_text_field( (string) $data['pay_currency'] ) : '';
		$payment_id   = isset( $data['payment_id'] ) ? sanitize_text_field( (string) $data['payment_id'] ) : '';
		$payment_id   = substr( $payment_id, 0, 191 );

		if ( $payment_id && rgar( $entry, 'id' ) ) {
			$form_id = absint( rgar( $entry, 'form_id' ) );
			if ( $form_id > 0 ) {
				gform_update_meta( $entry['id'], 'nowpayments_payment_id', $payment_id, $form_id );
			}
		}

		$actually_paid = isset( $data['actually_paid'] ) ? floatval( $data['actually_paid'] ) : 0.0;
		if ( $actually_paid <= 0 && isset( $data['actually_paid_amount'] ) ) {
			$actually_paid = floatval( $data['actually_paid_amount'] );
		}
		if ( $actually_paid <= 0 && isset( $data['price_amount'] ) ) {
			$actually_paid = floatval( $data['price_amount'] );
		}

		$price_amount = isset( $data['price_amount'] ) ? sanitize_text_field( (string) $data['price_amount'] ) : '';

		$result = array(
			'id'               => $payment_id,
			'transaction_id'   => $payment_id ? $payment_id : rgar( $entry, 'transaction_id' ),
			'amount'           => $actually_paid,
			'entry_id'         => $entry['id'],
			'payment_status'   => 'Paid',
			'payment_date'     => gmdate( 'Y-m-d H:i:s' ),
			'type'             => 'complete_payment',
			'transaction_type' => 'payment',
		);

		$amount_formatted = $result['amount'] > 0
			? GFCommon::to_money( $result['amount'], $entry['currency'] )
			: ( $price_amount && $pay_currency ? $price_amount . ' ' . $pay_currency : '' );
		$amount_formatted = $amount_formatted ? wp_strip_all_tags( (string) $amount_formatted ) : '';

		$status_label    = $payment_status ? $payment_status : 'UNKNOWN';
		$payment_id_note = $payment_id ? $payment_id : __( 'N/A', 'nowpayments-for-gravity-forms' );

		$completed_statuses = array( 'CONFIRMED', 'COMPLETED', 'FINISHED' );
		$completed_statuses = apply_filters( 'nowpayments_gf_completed_payment_statuses', $completed_statuses, $data, $entry, $this );

		if ( in_array( $payment_status, $completed_statuses, true ) ) {
			if ( 'Paid' === rgar( $entry, 'payment_status' ) ) {
				$this->sync_nowpayments_entry_meta( $entry, $data, 'Paid' );
				return;
			}

			$result['note'] = sprintf(
				/* translators: 1: amount 2: transaction id 3: status */
				__( 'Payment completed. Amount: %1$s. Transaction Id: %2$s. Status: %3$s.', 'nowpayments-for-gravity-forms' ),
				$amount_formatted ? $amount_formatted : __( 'N/A', 'nowpayments-for-gravity-forms' ),
				$payment_id_note,
				$status_label
			);
			$this->complete_payment( $entry, $result );
			$this->sync_nowpayments_entry_meta( $entry, $data, 'Paid' );
			nowpayments_gf_debug_log( '[NOWPayments-GF] Entry ' . $entry['id'] . ' marked Paid (status=' . $payment_status . ').' );
			return;
		}

		switch ( $payment_status ) {
			case 'FAILED':
			case 'EXPIRED':
				$fail_msg = __( 'Payment failed or expired.', 'nowpayments-for-gravity-forms' );
				if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
					$fail_msg = sanitize_textarea_field( $data['message'] );
				}
				$result['type'] = 'fail_payment';
				$result['note']  = $fail_msg;
				$result['note'] .= $payment_id ? ' ' . sprintf(
					/* translators: %s: transaction id */
					__( 'Transaction Id: %s.', 'nowpayments-for-gravity-forms' ),
					$payment_id
				) : '';
				$this->fail_payment( $entry, $result );
				$this->sync_nowpayments_entry_meta( $entry, $data, 'Failed' );
				break;
			case 'REFUNDED':
				$result['type'] = 'refund_payment';
				$result['note'] = sprintf(
					/* translators: 1: amount 2: transaction id */
					__( 'Payment refunded. Amount: %1$s. Transaction Id: %2$s.', 'nowpayments-for-gravity-forms' ),
					$amount_formatted ? $amount_formatted : __( 'N/A', 'nowpayments-for-gravity-forms' ),
					$payment_id_note
				);
				$this->refund_payment( $entry, $result );
				$this->sync_nowpayments_entry_meta( $entry, $data, 'Refunded' );
				break;
			default:
				if ( $payment_status ) {
					$this->sync_nowpayments_entry_meta( $entry, $data, $status_label );
				}
				break;
		}
	}

	/**
	 * Keep NOWPayments entry meta in sync with Gravity Forms payment status.
	 *
	 * @param array  $entry     Entry.
	 * @param array  $data      Payment payload.
	 * @param string $gf_status Display status for meta.
	 * @return void
	 */
	protected function sync_nowpayments_entry_meta( $entry, $data, $gf_status ) {
		$entry_id = absint( rgar( $entry, 'id' ) );
		$form_id  = absint( rgar( $entry, 'form_id' ) );
		if ( $entry_id <= 0 || $form_id <= 0 ) {
			return;
		}

		gform_update_meta( $entry_id, 'nowpayments_payment_status', $gf_status, $form_id );

		if ( isset( $data['payment_id'] ) && $data['payment_id'] !== '' && $data['payment_id'] !== null ) {
			gform_update_meta( $entry_id, 'nowpayments_payment_id', sanitize_text_field( (string) $data['payment_id'] ), $form_id );
		}

		$paid = isset( $data['actually_paid'] ) ? floatval( $data['actually_paid'] ) : 0.0;
		if ( $paid <= 0 && isset( $data['actually_paid_amount'] ) ) {
			$paid = floatval( $data['actually_paid_amount'] );
		}
		if ( $paid > 0 ) {
			gform_update_meta( $entry_id, 'nowpayments_amount', $paid, $form_id );
		}

		if ( ! empty( $data['pay_currency'] ) ) {
			gform_update_meta( $entry_id, 'nowpayments_pay_currency', sanitize_text_field( (string) $data['pay_currency'] ), $form_id );
		}

		gform_update_meta( $entry_id, 'nowpayments_payment_date', gmdate( 'Y-m-d H:i:s' ), $form_id );
	}

	/**
	 * After customer returns from NOWPayments, sync payment status via API when IPN is delayed.
	 *
	 * @return void
	 */
	public function maybe_sync_payment_on_return() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public return URL; entry id validated below.
		if ( empty( $_GET['nowpayments_gf_return'] ) || empty( $_GET['entry_id'] ) ) {
			return;
		}

		$entry_id = absint( wp_unslash( $_GET['entry_id'] ) );
		if ( $entry_id <= 0 ) {
			return;
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || empty( $entry ) ) {
			return;
		}

		if ( ! $this->is_payment_gateway( $entry['id'] ) && ! $this->entry_uses_nowpayments( $entry ) ) {
			return;
		}

		$current_status = rgar( $entry, 'payment_status' );
		if ( in_array( $current_status, array( 'Paid', 'Active' ), true ) ) {
			return;
		}

		// NOWPayments may append payment_status to the success redirect URL.
		$return_payload = $this->get_return_url_payment_payload();
		if ( ! empty( $return_payload ) ) {
			$this->apply_ipn_payment_update( $entry, $return_payload );
			$entry = GFAPI::get_entry( $entry_id );
			if ( ! is_wp_error( $entry ) && 'Paid' === rgar( $entry, 'payment_status' ) ) {
				return;
			}
		}

		$api = $this->get_api();
		if ( ! $api ) {
			nowpayments_gf_debug_log( '[NOWPayments-GF] Return sync skipped: API key not configured.' );
			return;
		}

		$payment_id = $this->get_return_payment_id( $entry );
		if ( $payment_id > 0 ) {
			$payment = $api->get_payment( $payment_id );
			if ( ! is_wp_error( $payment ) && is_array( $payment ) ) {
				$this->apply_ipn_payment_update( $entry, $payment );
				$entry = GFAPI::get_entry( $entry_id );
				if ( ! is_wp_error( $entry ) && 'Paid' === rgar( $entry, 'payment_status' ) ) {
					return;
				}
			} else {
				nowpayments_gf_debug_log( '[NOWPayments-GF] Return sync get_payment error: ' . ( is_wp_error( $payment ) ? $payment->get_error_message() : 'invalid response' ) );
			}
		}

		$response = $api->list_payments(
			array(
				'order_id' => (string) $entry_id,
				'limit'    => 20,
				'sortBy'   => 'created_at',
				'orderBy'  => 'desc',
			)
		);

		if ( is_wp_error( $response ) ) {
			nowpayments_gf_debug_log( '[NOWPayments-GF] Return sync API error: ' . $response->get_error_message() );
			return;
		}

		$payments = $this->normalize_payments_list( $response );

		$completed_statuses = apply_filters(
			'nowpayments_gf_completed_payment_statuses',
			array( 'finished', 'confirmed', 'completed' ),
			array(),
			$entry,
			$this
		);

		foreach ( $payments as $payment ) {
			if ( ! is_array( $payment ) ) {
				continue;
			}

			$status = isset( $payment['payment_status'] ) ? strtolower( (string) $payment['payment_status'] ) : '';
			if ( ! in_array( $status, array_map( 'strtolower', $completed_statuses ), true ) ) {
				continue;
			}

			$this->apply_ipn_payment_update( $entry, $payment );
			break;
		}
	}

	/**
	 * Build payment payload from query string on NOWPayments success redirect.
	 *
	 * @return array
	 */
	protected function get_return_url_payment_payload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public return URL from payment provider.
		$status = '';
		foreach ( array( 'payment_status', 'status', 'NP_status' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$status = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
				break;
			}
		}

		if ( '' === $status ) {
			return array();
		}

		$payload = array( 'payment_status' => $status );

		foreach ( array( 'payment_id', 'paymentId', 'NP_id', 'iid' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$payload['payment_id'] = absint( wp_unslash( $_GET[ $key ] ) );
				break;
			}
		}

		foreach ( array( 'actually_paid', 'pay_amount', 'price_amount' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && $_GET[ $key ] !== '' ) {
				$payload[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		if ( ! empty( $_GET['pay_currency'] ) ) {
			$payload['pay_currency'] = sanitize_text_field( wp_unslash( $_GET['pay_currency'] ) );
		}

		return $payload;
	}

	/**
	 * Resolve NOWPayments payment ID from return URL or entry meta.
	 *
	 * @param array $entry Entry.
	 * @return int
	 */
	protected function get_return_payment_id( $entry ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public return URL from payment provider.
		foreach ( array( 'payment_id', 'paymentId', 'NP_id', 'iid' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$id = absint( wp_unslash( $_GET[ $key ] ) );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		$stored = gform_get_meta( $entry['id'], 'nowpayments_payment_id' );
		return $stored ? absint( $stored ) : 0;
	}

	/**
	 * Normalize list payments API response to an array of payments.
	 *
	 * @param array $response API response.
	 * @return array
	 */
	protected function normalize_payments_list( $response ) {
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		if ( isset( $response['payments'] ) && is_array( $response['payments'] ) ) {
			return $response['payments'];
		}
		if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
			return $response['result'];
		}
		if ( array_values( $response ) === $response ) {
			return $response;
		}
		return array();
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

		$received = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ) );
		$secret   = trim( $ipn_secret );

		// Official NOWPayments PHP example: recursive key sort + json_encode( JSON_UNESCAPED_SLASHES ).
		$sorted     = $this->sort_array_recursive( $data );
		$payload    = json_encode( $sorted, JSON_UNESCAPED_SLASHES );
		$calculated = hash_hmac( 'sha512', $payload, $secret );

		if ( hash_equals( $calculated, $received ) ) {
			return true;
		}

		// Legacy top-level sort only (older integrations).
		$legacy = $data;
		ksort( $legacy );
		$legacy_payload = json_encode( $legacy, JSON_UNESCAPED_SLASHES );
		$legacy_hmac    = hash_hmac( 'sha512', $legacy_payload, $secret );

		return hash_equals( $legacy_hmac, $received );
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

	public function init_ajax() {
		parent::init_ajax();
	}
}
