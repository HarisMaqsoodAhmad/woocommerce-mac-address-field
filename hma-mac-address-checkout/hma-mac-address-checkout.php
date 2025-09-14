<?php
/**
 * Plugin Name: HMA – WooCommerce Mac Address Field
 * Description: Adds a "Mac Address" field to WooCommerce checkout, saves to order meta, shows in admin, emails, and order details.
 * Version:     1.0.0
 * Author:      Haris Maqsood
 * Plugin URI:  https://harismaqsood.com
 * Author URI:  https://harismaqsood.com
 * License:     GPLv2 or later
 * Text Domain: hma-mac
 * WC requires at least: 4.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Mac Address field to checkout.
 */
function hma_add_mac_address_checkout_field( $fields ) {
	$fields['billing']['hma_mac_address'] = array(
		'type'        => 'text',
		'label'       => __( 'Mac Address', 'hma-mac' ),
		'placeholder' => __( 'e.g. AA:BB:CC:DD:EE:FF', 'hma-mac' ),
		'required'    => true,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 120,
	);
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'hma_add_mac_address_checkout_field' );

/**
 * Validate Mac Address on checkout submit.
 * Accepts formats:
 *  - AA:BB:CC:DD:EE:FF
 *  - AA-BB-CC-DD-EE-FF
 *  - AABBCCDDEEFF
 *  - Case-insensitive
 */
function hma_validate_mac_address() {
	if ( isset( $_POST['hma_mac_address'] ) ) {
		$mac = trim( wp_unslash( $_POST['hma_mac_address'] ) );

		// Normalize: remove spaces
		$mac_no_space = preg_replace( '/\s+/', '', $mac );

		$valid =
			preg_match( '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac_no_space ) || // colon or dash separated
			preg_match( '/^[0-9A-Fa-f]{12}$/', $mac_no_space );                            // contiguous 12 hex

		if ( ! $valid ) {
			wc_add_notice( __( 'Please enter a valid Mac Address (e.g. AA:BB:CC:DD:EE:FF).', 'hma-mac' ), 'error' );
		}
	}
}
add_action( 'woocommerce_checkout_process', 'hma_validate_mac_address' );

/**
 * Save Mac Address to order meta on creation.
 */
function hma_save_mac_address_to_order( $order, $data ) {
	if ( isset( $_POST['hma_mac_address'] ) ) {
		$mac = sanitize_text_field( wp_unslash( $_POST['hma_mac_address'] ) );
		// Store normalized uppercase with colons for consistency
		$normalized = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', $mac ) );
		if ( strlen( $normalized ) === 12 ) {
			$normalized = implode( ':', str_split( $normalized, 2 ) ); // AA:BB:...
		}
		$order->update_meta_data( '_hma_mac_address', $normalized );
	}
}
add_action( 'woocommerce_checkout_create_order', 'hma_save_mac_address_to_order', 10, 2 );

/**
 * Show editable Mac Address field in the admin order page (Billing panel).
 */
function hma_admin_display_mac_address( $order ) {
	$mac = $order->get_meta( '_hma_mac_address' );
	echo '<div class="address">';
	echo '<p class="form-field form-field-wide">';
	echo '<label for="hma_mac_address"><strong>' . esc_html__( 'Mac Address', 'hma-mac' ) . '</strong></label><br />';
	printf(
		'<input type="text" class="short" style="width:100%%" name="hma_mac_address" id="hma_mac_address" value="%s" placeholder="%s" />',
		esc_attr( $mac ),
		esc_attr__( 'AA:BB:CC:DD:EE:FF', 'hma-mac' )
	);
	echo '</p>';
	echo '</div>';
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'hma_admin_display_mac_address', 10, 1 );

/**
 * Save Mac Address when updating order from admin.
 */
function hma_admin_save_mac_address( $post_id ) {
	if ( isset( $_POST['hma_mac_address'] ) ) {
		$mac = sanitize_text_field( wp_unslash( $_POST['hma_mac_address'] ) );
		$normalized = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', $mac ) );
		if ( strlen( $normalized ) === 12 ) {
			$normalized = implode( ':', str_split( $normalized, 2 ) );
		}
		update_post_meta( $post_id, '_hma_mac_address', $normalized );
	}
}
add_action( 'woocommerce_process_shop_order_meta', 'hma_admin_save_mac_address', 10, 1 );

/**
 * Include Mac Address in customer & admin order emails.
 */
function hma_email_order_meta_fields( $fields, $sent_to_admin, $order ) {
	$mac = $order->get_meta( '_hma_mac_address' );
	if ( $mac ) {
		$fields['hma_mac_address'] = array(
			'label' => __( 'Mac Address', 'hma-mac' ),
			'value' => $mac,
		);
	}
	return $fields;
}
add_filter( 'woocommerce_email_order_meta_fields', 'hma_email_order_meta_fields', 10, 3 );

/**
 * Show Mac Address on the order details page (Thank you / My Account).
 */
function hma_show_mac_on_order_details( $order ) {
	$mac = $order->get_meta( '_hma_mac_address' );
	if ( $mac ) {
		echo '<section class="woocommerce-order-details">';
		echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Additional Information', 'hma-mac' ) . '</h2>';
		echo '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">';
		echo '<tbody>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Mac Address', 'hma-mac' ) . '</th>';
		echo '<td>' . esc_html( $mac ) . '</td>';
		echo '</tr>';
		echo '</tbody>';
		echo '</table>';
		echo '</section>';
	}
}
add_action( 'woocommerce_order_details_after_order_table', 'hma_show_mac_on_order_details', 10, 1 );

/**
 * (Optional) Make it searchable in admin Orders list by keyword.
 */
function hma_order_search_meta_keys( $meta_keys ) {
	$meta_keys[] = '_hma_mac_address';
	return $meta_keys;
}
add_filter( 'woocommerce_shop_order_search_fields', 'hma_order_search_meta_keys' );
