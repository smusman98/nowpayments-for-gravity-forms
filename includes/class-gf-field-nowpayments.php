<?php
/**
 * Gravity Forms NOWPayments field (Pricing Fields).
 *
 * @package NOWPayments_GF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFForms' ) ) {
	return;
}

/**
 * NOWPayments payment field — appears in Pricing Fields; payment is handled by the add-on on submit.
 */
class GF_Field_NowPayments extends GF_Field {

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'nowpayments';

	/**
	 * Indicates the field is used for payment.
	 *
	 * @var bool
	 */
	public $is_payment = true;

	/**
	 * Form editor: which settings to show.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	/**
	 * Form editor button: group and label (puts field in Pricing Fields).
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'pricing_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Form editor field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'NOWPayments', 'nowpayments-for-gravity-forms' );
	}

	/**
	 * Form editor field description.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Accept cryptocurrency payments via NOWPayments. Add this field and configure a NOWPayments feed for the form.', 'nowpayments-for-gravity-forms' );
	}

	/**
	 * Form editor field icon (NOWPayments logo).
	 * Uses plugins_url() so the icon loads on any domain (e.g. InstaWP).
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		if ( defined( 'NOWPAYMENTS_GF_FILE' ) ) {
			return plugins_url( 'assets/nowpayments-logo.png', NOWPAYMENTS_GF_FILE );
		}
		// Fallback if loaded before main plugin.
		$addon = NowPayments_GF_AddOn::get_instance();
		return $addon ? $addon->get_base_url() . 'assets/nowpayments-logo.png' : '';
	}

	/**
	 * Frontend/entry input markup. Display-only; payment is handled by add-on on submit.
	 *
	 * @param array      $form  Form object.
	 * @param string     $value Field value.
	 * @param array|null $entry Entry (optional).
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$id        = (int) $this->id;
		$form_id   = absint( $form['id'] );
		$is_editor = $this->is_form_editor();
		$field_id  = $is_editor || 0 === $form_id ? "input_{$id}" : 'input_' . $form_id . "_$id";

		$message = esc_html__( 'Payment will be processed via NOWPayments (cryptocurrency).', 'nowpayments-for-gravity-forms' );
		$hidden  = sprintf( '<input type="hidden" name="input_%d" id="%s" value="nowpayments" />', $id, esc_attr( $field_id ) );

		if ( $is_editor ) {
			/* Compact one-line preview in form editor to avoid UI overflow. */
			return '<div class="ginput_container ginput_container_nowpayments"><span class="gfield_description">' . esc_html__( 'Pay with crypto (NOWPayments)', 'nowpayments-for-gravity-forms' ) . '</span>' . $hidden . '</div>';
		}

		return '<div class="ginput_container ginput_container_nowpayments"><p class="gfield_description" role="status">' . $message . '</p>' . $hidden . '</div>';
	}
}

GF_Fields::register( new GF_Field_NowPayments() );
