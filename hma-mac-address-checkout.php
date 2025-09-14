<?php

/**
 * Plugin Name: HMA – WooCommerce Mac Address Field
 * Description: Adds a "Mac Address" field to WooCommerce checkout, saves to order meta, shows in admin, emails, and order details.
 * Version:     1.0.2
 * Author:      Haris Maqsood
 * Plugin URI:  https://harismaqsood.com
 * Author URI:  https://harismaqsood.com
 * License:     GPLv2 or later
 * Text Domain: hma-mac
 */

if (! defined('ABSPATH')) exit;

/**
 * Render a standalone Mac Address field on checkout (separate from Billing).
 * Placed under the "Additional information" section.
 */
function hma_render_mac_address_checkout_field($checkout)
{
	echo '<div class="hma-mac-address-field"><h3>' . esc_html__('Device Information', 'hma-mac') . '</h3>';

	woocommerce_form_field('hma_mac_address', array(
		'type'        => 'text',
		'label'       => __('Mac Address', 'hma-mac'),
		'placeholder' => __('e.g. AA:BB:CC:DD:EE:FF', 'hma-mac'),
		'required'    => true,
		'class'       => array('form-row-wide'),
		'priority'    => 5,
	), $checkout->get_value('hma_mac_address'));

	echo '</div>';
}
add_action('woocommerce_before_order_notes', 'hma_render_mac_address_checkout_field');
// If Order Notes are disabled, use this instead:
// add_action( 'woocommerce_before_order_notes', 'hma_render_mac_address_checkout_field' );

/**
 * Validate Mac Address input.
 */
function hma_validate_mac_address()
{
	if (isset($_POST['hma_mac_address'])) {
		$mac = trim(wp_unslash($_POST['hma_mac_address']));
		$mac = preg_replace('/\s+/', '', $mac); // remove spaces

		$valid =
			preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac) ||
			preg_match('/^[0-9A-Fa-f]{12}$/', $mac);

		if (! $valid) {
			wc_add_notice(__('Please enter a valid Mac Address (e.g. AA:BB:CC:DD:EE:FF).', 'hma-mac'), 'error');
		}
	} else {
		wc_add_notice(__('Mac Address is required.', 'hma-mac'), 'error');
	}
}
add_action('woocommerce_checkout_process', 'hma_validate_mac_address');

/**
 * Save to order meta (normalized to uppercase colon format).
 */
function hma_save_mac_address_to_order($order, $data)
{
	if (isset($_POST['hma_mac_address'])) {
		$mac = sanitize_text_field(wp_unslash($_POST['hma_mac_address']));
		$norm = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
		if (strlen($norm) === 12) {
			$norm = implode(':', str_split($norm, 2)); // AA:BB:CC:DD:EE:FF
		}
		$order->update_meta_data('_hma_mac_address', $norm);
	}
}
add_action('woocommerce_checkout_create_order', 'hma_save_mac_address_to_order', 10, 2);

/**
 * Admin: show editable field on the order screen.
 */
function hma_admin_display_mac_address($order)
{
	$mac = $order->get_meta('_hma_mac_address');
	echo '<div class="address"><p class="form-field form-field-wide">';
	echo '<label for="hma_mac_address"><strong>' . esc_html__('Mac Address', 'hma-mac') . '</strong></label><br />';
	printf(
		'<input type="text" class="short" style="width:100%%" name="hma_mac_address" id="hma_mac_address" value="%s" placeholder="%s" />',
		esc_attr($mac),
		esc_attr__('AA:BB:CC:DD:EE:FF', 'hma-mac')
	);
	echo '</p></div>';
}
add_action('woocommerce_admin_order_data_after_billing_address', 'hma_admin_display_mac_address', 10);

/**
 * Admin: save from order edit screen.
 */
function hma_admin_save_mac_address($post_id)
{
	if (isset($_POST['hma_mac_address'])) {
		$mac  = sanitize_text_field(wp_unslash($_POST['hma_mac_address']));
		$norm = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
		if (strlen($norm) === 12) $norm = implode(':', str_split($norm, 2));
		update_post_meta($post_id, '_hma_mac_address', $norm);
	}
}
add_action('woocommerce_process_shop_order_meta', 'hma_admin_save_mac_address', 10);

/**
 * Emails: include Mac Address.
 */
function hma_email_order_meta_fields($fields, $sent_to_admin, $order)
{
	$mac = $order->get_meta('_hma_mac_address');
	if ($mac) {
		$fields['hma_mac_address'] = array(
			'label' => __('Mac Address', 'hma-mac'),
			'value' => $mac,
		);
	}
	return $fields;
}
add_filter('woocommerce_email_order_meta_fields', 'hma_email_order_meta_fields', 10, 3);

/**
 * Frontend order details: show Mac Address.
 */
function hma_show_mac_on_order_details($order)
{
	$mac = $order->get_meta('_hma_mac_address');
	if ($mac) {
		echo '<section class="woocommerce-order-details"><h2 class="woocommerce-order-details__title">'
			. esc_html__('Additional Information', 'hma-mac') . '</h2>';
		echo '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details"><tbody>';
		echo '<tr><th scope="row">' . esc_html__('Mac Address', 'hma-mac') . '</th><td>' . esc_html($mac) . '</td></tr>';
		echo '</tbody></table></section>';
	}
}
add_action('woocommerce_order_details_after_order_table', 'hma_show_mac_on_order_details', 10);

/**
 * Make it searchable in admin orders list.
 */
function hma_order_search_meta_keys($keys)
{
	$keys[] = '_hma_mac_address';
	return $keys;
}
add_filter('woocommerce_shop_order_search_fields', 'hma_order_search_meta_keys');

// /**
//  * ===============================
//  * Express Pay restriction (Option 1)
//  * Keep Apple/Google Pay ONLY on Checkout page (shortcode/classic),
//  * so customers cannot bypass the required MAC field.
//  * ===============================
//  */

// /**
//  * WooCommerce Payments (WCPay) – limit payment request buttons to checkout only.
//  */
// function hma_limit_wcpay_express_to_checkout($locations)
// {
// 	// Valid values include: 'product', 'cart', 'checkout', 'mini_cart'
// 	return array('checkout');
// }
// add_filter('wcpay_payment_request_button_locations', 'hma_limit_wcpay_express_to_checkout');

// /**
//  * Stripe Gateway (separate Stripe plugin) – limit payment request buttons to checkout only.
//  */
// function hma_limit_stripe_express_to_checkout($locations)
// {
// 	// Valid values include: 'product', 'cart', 'checkout', 'mini_cart'
// 	return array('checkout');
// }
// add_filter('wc_stripe_payment_request_button_locations', 'hma_limit_stripe_express_to_checkout');
