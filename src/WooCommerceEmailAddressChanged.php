<?php

namespace Netzstrategen\Ssofact;

/**
 * Email sent to the admin when a customer updates their address.
 */
class WooCommerceEmailAddressChanged extends \WC_Email {

	public function __construct() {
		$this->id             = 'address_changed';
		$this->title          = __('Address changed', Plugin::L10N);
		$this->description    = __('Address change emails are sent to the chosen recipient(s) when a customer updates their billing or shipping address.', Plugin::L10N);
		$this->template_html  = 'emails/admin-address-changed.php';
		$this->template_plain = 'emails/plain/admin-address-changed.php';
		$this->default_path   = Plugin::getBasePath() . '/templates/';
		$this->placeholders   = array(
			'{site_title}' => $this->get_blogname(),
			'{site_url}' => '',
			'{customer}' => '',
			'{address}' => '',
			'{address_type}' => '',
		);

		add_action('woocommerce_customer_save_address_notification', array($this, 'trigger'), 10, 2);

		parent::__construct();

		$this->recipient = $this->get_option('recipient', get_option('admin_email'));
	}

	public function get_default_subject() {
		return __('[{site_title}] Address changed: {customer}', Plugin::L10N);
	}

	public function get_default_heading() {
		return __('Address changed', Plugin::L10N);
	}

	/**
	 * @implements woocommerce_customer_save_address
	 */
	public function trigger($user_id, $address_type) {
		$this->setup_locale();

		$account = get_user_by('id', $user_id);
		$this->account = $account;
		$this->customer = new \WC_Customer($user_id);
		$this->placeholders['{customer}'] = get_user_meta($user_id, 'subscriber_id', TRUE) ?: $this->customer->get_email();

		// @see WC_Order::get_formatted_billing_address()
		$address = $address_type === 'shipping' ? $this->customer->get_shipping() : $this->customer->get_billing();
		$address['salutation'] = get_user_meta($user_id, 'billing_salutation', TRUE);
		$address['house_number'] = get_user_meta($user_id, 'billing_house_number', TRUE);
		$address['subscriber_id'] = get_user_meta($user_id, 'subscriber_id', TRUE);

		$address_formatted = WC()->countries->get_formatted_address($address);
		$address_formatted .= "\n\n" . sprintf(__('Phone: %s', Plugin::L10N), $address['phone_prefix'] . '-' . $this->customer->get_billing_phone());

		$this->placeholders['{address}'] = strtr($address_formatted, [
			"\n" => "<br>\n",
			"<br/>" => "<br>\n",
		]);
		$this->placeholders['{address_type}'] = $address_type === 'shipping' ? __('Shipping address', 'woocommerce') : __('Billing address', 'woocommerce');
		$this->placeholders['{site_url}'] = site_url();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html() {
		$message = wc_get_template_html($this->template_html, array(
			'account'       => $this->account,
			'customer'      => $this->customer,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => false,
			'email'         => $this,
		), '', $this->default_path);
		$message = $this->format_string($message);
		return $message;
	}

	public function get_content_plain() {
		$message = wc_get_template_html($this->template_plain, array(
			'account'       => $this->account,
			'customer'      => $this->customer,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'         => $this,
		), '', $this->default_path);
		$message = $this->format_string($message);
		return $message;
	}

	/**
	 * Initialise settings form fields.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['recipient'] = array(
			'title'       => __( 'Recipient(s)', 'woocommerce' ),
			'type'        => 'text',
			/* translators: %s: admin email */
			'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
			'placeholder' => '',
			'default'     => '',
			'desc_tip'    => true,
		);
	}

}
