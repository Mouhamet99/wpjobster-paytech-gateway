<?php
/**
 * Plugin Name: WPJobster Sample Gateway
 * Plugin URI: http://wpjobster.com/
 * Description: This plugin extends Jobster Theme to accept payments with Sample.
 * Author: WPJobster
 * Author URI: http://wpjobster.com/
 * Version: 3.0.3
 *
 * Copyright (c) 2022 WPJobster
 *
 */

// Exit if the file is accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Defines
define( 'WPJ_SAMPLE_VERSION'               , '3.0.3' );
define( 'WPJ_SAMPLE_REQUIRED_THEME_VERSION', '6.0.9' );

if ( ! class_exists( "WPJobster_Sample_Loader" ) ) {

	class WPJobster_Sample_Loader {

		public function __construct() {
			// Define gateway unique slug
			$this->unique_id = 'sample';


/* ADMIN */

			// Add gateway options to Admin > Jobster Settings > Payment Gateways
			add_action( 'wpj_after_admin_paypal_settings_fields', function() {
				if ( class_exists( 'Redux' ) ) {
					Redux::setSection( 'jobster_settings', array(
						'id'         => 'sample-settings', // settings option name
						'title'      => __( 'Sample', 'wpjobster-sample' ), // gateway settings page title
						'desc'       => __( 'Sample Settings', 'wpjobster-sample' ), // gateway settings page description
						'subsection' => true, // subsection of Payment Gateways section
						'fields'     => wpj_get_gateway_default_fields(
							array(
								'gateway_id'           => $this->unique_id, // gateway id
								'gateway_name'         => 'Sample', // gateway name
								'gateway_version'      => WPJ_SAMPLE_VERSION, // gateway version
								'gateway_instructions' => array( // gateway instructions
									'Do you have any special instructions for your gateway?',
									'You can put them here.',
								),
								'license'              => true, // include license field
								'enable'               => true, // include enable field
								'enable_sandbox'       => true, // include enable sandbox field
								'exclude_payment_type' => array( 'withdraw' ), // exclude payment types from options; accepted values: job_purchase, 'topup', 'featured', 'withdraw', 'tips', 'subscription', 'custom_extra'
								'button_name'          => true, // include button name field
								'public_key'           => false, // include public key field
								'secret_key'           => false, // include secret key field
								'succes_page_url'      => true, // include transaction success page field
								'fail_page_url'        => true, // include transaction failure page field
								'new_fields'           => array( // extra fields (optional fields)
									array(
										'unique_id' => 'sample-settings-section',
										'type'      => 'section',
										'title'     => esc_html__( 'Keys', 'wpjobster-sample' ),
										'indent'    => true,
									),
									array(
										'unique_id' => 'wpjobster_sample_id',
										'type'      => 'text',
										'title'     => __( 'Sample ID', 'wpjobster-sample' ),
									),
									array(
										'unique_id' => 'wpjobster_sample_key',
										'type'      => 'text',
										'title'     => __( 'Sample Key', 'wpjobster-sample' )
									),

									array(
										'unique_id' => 'sample-withdrawal-settings-section',
										'type'      => 'section',
										'title'     => esc_html__( 'Withdrawals', 'wpjobster-sample' ),
										'indent'    => true,
									),
									array(
										'unique_id'   => 'wpjobster_sample_withdrawal_enable',
										'type'        => 'switch',
										'title'       => __( 'Enable', 'wpjobster-sample' ),
										'description' => __( 'Enable/Disable Sample withdrawal payment gateway', 'wpjobster-sample' )
									),
									array(
										'unique_id'   => 'wpjobster_sample_withdraw_enablesandbox',
										'type'        => 'switch',
										'title'       => __( 'Enable test mode', 'wpjobster-sample' ),
										'description' => __( 'Enable/Disable sample withdrawal test mode.', 'wpjobster-sample' )
									),
									array(
										'unique_id' => 'wpjobster_sample_client_id',
										'type'      => 'text',
										'title'     => __( 'Sample client ID', 'wpjobster-sample' ),
									),
									array(
										'unique_id' => 'wpjobster_sample_secret_key',
										'type'      => 'text',
										'title'     => __( 'Sample secret key', 'wpjobster-sample' )
									)
								)
							)
						)
					) );
				}
			});


/* PAYMENT - remove this section if your gateway doesn't support payments (withdrawal only) */

			// Add gateway (button) to payment methods list - add button to checkout page
			add_filter( 'wpj_payment_gateways_filter', function ( $payment_gateways_list ) {
				$payment_gateways_list[$this->unique_id] = __( 'Sample', 'wpjobster-sample' );
				return $payment_gateways_list;
			}, 10, 1 );

			// Add gateway to payment process flow - send and receive the payment info
			add_action( 'wpjobster_taketo_' . $this->unique_id . '_gateway', array( $this, 'initializePayment' ), 10, 2 );
			add_action( 'wpjobster_processafter_' . $this->unique_id . '_gateway', array( $this, 'processPayment' ), 10, 2 );


/* WITHDRAWAL - remove this section if your gateway doesn't support widthdrawal */

			// Add gateway to withdrawal gateways list
			add_filter( 'wpj_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'sample' ); return $gateways; }, 10, 1 );
			add_filter( 'wpj_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'sample_automatic' ); return $gateways; }, 10, 1 );

			// Use this filters if the gateways is made only for withdrawal
			// add_filter( 'wpj_only_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'sample' ); return $gateways; }, 10, 1 );
			// add_filter( 'wpj_only_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'sample_automatic' ); return $gateways; }, 10, 1 );

			// Set gateway withdrawal name to database
			add_filter( 'wpjobster_withdraw_method_filter', array( $this, 'changeWithdrawMethod' ) );


/* WITHDRAWAL ADMIN - remove this section if your gateway doesn't support widthdrawal */

			// Save options for manual and automatic
			add_action( 'redux/options/jobster_settings/saved', array( $this, 'saveExtraAdminOptions' ) );

			// Show 'Mark automatically as completed' button to admin orders - pending tab only
			add_action( 'wpj_show_hide_automatic_withdrawal_button_filter', array( $this, 'displayAdminAutomaticWithdrawalButton' ), 10, 2 );

			// Show 'Process withdrawal' button to admin orders - pending tab only
			add_action( 'wpj_after_admin_orders_tfoot_buttons', array( $this, 'displayProcessPaymentRequestButton' ) );

			// Process withdrawal order
			add_action( 'wpj_before_admin_orders_content', array( $this, 'processPaymentRequest' ), 11 );


/* WITHDRAWAL FRONT - remove this section if your gateway doesn't support widthdrawal */

			// Add gateway sample email or sample sample_automatic_payee_id or sample_email to Settings page
			// This step should be added to Gutenberg in Admin > Pages> Settings > Edit, using shortcodes.

			// Add gateway to payments page > request withdrawal list
			add_filter( 'wpj_withdrawals_gateways_info_filter', array( $this, 'addGatewayToPaymentsWithdrawalList' ) );

			// Add details input to payments page > request withdrawal list
			add_action( 'wpj_after_withdrawal_gateways_list_details_input', array( $this, 'addGatewayDetailsInputToPaymentsWithdrawalList' ), 10, 1 );

			// Gateway inline styles - use this action if you need to add small styles for your gateway
			add_action( 'wp_enqueue_scripts', function() {
				$style = '.your-class{display:block;}';
				wp_add_inline_style( 'semantic-ui-css', $style );
			});


/* SETTINGS */

			// Set plugin compatibility - prevents the plugin from activating on other themes
			add_action( 'admin_notices', array( $this, 'checkCompatibility' ) );

			// Set plugin action link - show 'Settings' link to plugins page
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
				return array_merge( array( wpj_generate_settings_link( $this->unique_id ) ), $links );
			});

			// Set plugin textdomain
			add_action( 'plugins_loaded', function () {
				load_plugin_textdomain( 'wpjobster-sample', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
			}, 0 );

			// Gateway name translatable - allows the Gateway name to be translated into other languages
			add_filter( 'wpjobster_database_strings_filter', array( $this, 'translateGatewayName' ), 10, 1 );

			// Use this filter if your gateway works with a specific currency only
			add_filter( 'wpjobster_take_allowed_currency_' . $this->unique_id, array( $this, 'setGatewayCurrency' ) );

		}

		public static function init() { $class = __CLASS__; new $class; }


		/* PAYMENT - remove this section if your gateway doesn't support payments (withdrawal only) */

		public function initializePayment( $payment_type, $order_details ) { // params from gateways/init.php

			// Payment ROW
			$payment_row = wpj_get_payment( array( 'payment_type_id' => $order_details['id'], 'payment_type' => $payment_type ) );

			// Callback URL
			$callback_url = get_bloginfo( 'url' ) . '/?payment_response=' . $this->unique_id . '&payment_id=' . $payment_row->id;

			if ( wpj_get_option( 'wpjobster_sample_id' ) && wpj_get_option( 'wpjobster_sample_key' ) ) {

				// User info
				$uid       = get_current_user_id();
				$user_info = get_userdata( $uid );

				// Action URL
				if ( wpj_get_option( 'wpjobster_' . $this->unique_id . '_enable_sandbox' ) == 'yes' ) $payment_url = 'https://sample.url';
				else $payment_url = 'https://test.sample.url';

				// Send data to Sample
				$fields = array();

				$fields['merchant_id']    = wpj_get_option( 'wpjobster_sample_id' );
				$fields['merchant_key']   = wpj_get_option( 'wpjobster_sample_key' );

				$fields['payment_id']     = $payment_row->id;
				$fields['order_id']       = $order_details['id'];

				$fields['job_title']      = apply_filters( 'wpj_gateway_order_title_filter', wpj_get_title_by_payment_type( $payment_type, $order_details ), $order_details, $payment_type, $this->unique_id );
				$fields['payable_amount'] = $payment_row->final_amount_exchanged;
				$fields['currency']       = $payment_row->final_amount_currency;

				$fields['success_url']    = $callback_url . '&action=paid';
				$fields['cancel_url']     = $callback_url . '&action=cancel';
				$fields['notify_url']     = $callback_url . '&action=notify';

				$fields['firstname']      = wpj_user( $uid, 'first_name' );
				$fields['email']          = wpj_user( $uid, 'user_email' );
				$fields['phone']          = wpj_user( $uid, 'cell_number' );
				$fields['lastname']       = wpj_user( $uid, 'last_name' );
				$fields['address']        = wpj_user( $uid, 'address' );
				$fields['city']           = wpj_user( $uid, 'city' );
				$fields['country']        = wpj_user( $uid, 'country_name' );
				$fields['zipcode']        = wpj_user( $uid, 'zip' );

				// Redirect to payment page
				wpj_display_gateway_form( $fields, $payment_url );

			} else { wpj_display_order_errors( $payment_type, __( 'Please fill Sample ID and Sample Key fields', 'wpjobster-sample' ) ); }
		}

		public function processPayment( $payment_type, $payment_type_class ) { // params from gateways/init.php
			if ( isset( $_REQUEST['payment_id'] ) ) {
				$payment_id  = $_REQUEST['payment_id'];
				$payment_row = wpj_get_payment( array( 'id' => $payment_id ) );

				$order_id     = $payment_row->payment_type_id;
				$payment_type = $payment_row->payment_type;

				$payment_response = json_encode( $_REQUEST );
				$response_decoded = json_decode( $payment_response );

				// Save response
				$webhook = wpj_save_webhook( array(
					'webhook_id'       => $response_decoded->id,
					'payment_id'       => $payment_id,
					'status'           => $response_decoded->status,
					'type'             => $response_decoded->type,
					'description'      => $response_decoded->description,
					'amount'           => $response_decoded->total_amount,
					'amount_currency'  => $response_decoded->currency,
					'fees'             => 0,
					'fees_currency'    => $response_decoded->currency,
					'create_time'      => current_time( 'timestamp', 1 ),
					'payment_response' => $payment_response,
					'payment_type'     => $payment_type,
					'order_id'         => $order_id
				) );

				// Apply response to order
				if ( WPJ_Form::get( 'action' ) == 'cancelled' ) { // mark order as cancelled
					do_action( "wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->unique_id, 'Buyer clicked cancel', $payment_response );

				} elseif ( WPJ_Form::get( 'action' ) == 'paid' && $_POST['status'] == 'success' ) { // mark order as paid
					do_action( "wpjobster_" . $payment_type . "_payment_success", $order_id, $this->unique_id, 'Transaction ID: ' . WPJ_Form::post( 'order_number' ), $payment_response );

				} else { // ipn
					if ( $_POST['status'] == 'success' )
						do_action( "wpjobster_" . $payment_type . "_payment_success", $order_id, $this->unique_id, 'Transaction ID: ' . WPJ_Form::post( 'order_number' ), $payment_response );

					elseif ( $_POST['status'] == 'fail' )
						do_action( "wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->unique_id, 'Transaction ID: ' . WPJ_Form::post( 'order_number' ), $payment_response );

					else
						do_action( "wpjobster_" . $payment_type . "_payment_other", $order_id, $this->unique_id, WPJ_Form::post( 'pg_txnid' ), $payment_response, $payment_status );

				}

			} else wpj_display_order_errors( $payment_type, __( 'Something went wrong! Payment ID is undefined!', 'wpjobster-sample' ) );
		}


		/* WITHDRAWAL - remove this section if your gateway doesn't support widthdrawal */

		public function changeWithdrawMethod( $method ) {
			if ( $_POST['method'] == 'sample_withdraw' ) $method = "Sample";
			return $method;
		}


		/* WITHDRAWAL ADMIN - remove this section if your gateway doesn't support widthdrawal */

		public function saveExtraAdminOptions( $options ) {
			if ( ! empty( $options['wpjobster_sample_withdrawal_enable'] ) ) {
				if ( $options['wpjobster_sample_withdrawal_enable'] == 'both' ) {
					update_option( 'wpjobster_sample_automatic_enable_withdraw', 'yes' );
					update_option( 'wpjobster_sample_enable_withdraw', 'yes' );

				} elseif ( $options['wpjobster_sample_withdrawal_enable'] == 'automatic' ) {
					update_option( 'wpjobster_sample_automatic_enable_withdraw', 'yes' );
					update_option( 'wpjobster_sample_enable_withdraw', 'no' );

				} elseif ( $options['wpjobster_sample_withdrawal_enable'] == 'manual' ) {
					update_option( 'wpjobster_sample_automatic_enable_withdraw', 'no' );
					update_option( 'wpjobster_sample_enable_withdraw', 'yes' );

				} else {
					update_option( 'wpjobster_sample_automatic_enable_withdraw', 'no' );
					update_option( 'wpjobster_sample_enable_withdraw', 'no' );

				}
			}
		}

		public function displayAdminAutomaticWithdrawalButton( $default, $row ) {
			if ( strtolower( $row->methods ) == 'sample' ) {
				$wpjobster_sample_client_id  = wpj_get_option( 'wpjobster_sample_client_id' );
				$wpjobster_sample_secret_key = wpj_get_option( 'wpjobster_sample_secret_key' );

				if ( ! empty( $wpjobster_sample_client_id ) && ! empty( $wpjobster_sample_secret_key ) )
					return wpj_is_payment_type_enabled( 'sample_automatic', 'withdraw' ) ? true : false;

				return false;
			}

			return $default;
		}

		public function displayProcessPaymentRequestButton( $payment_type ) {
			$wpjobster_sample_withdrawal_enable = wpj_get_option( 'wpjobster_sample_automatic_enable_withdraw' );
			if ( $payment_type == 'withdrawal' && WPJ_Form::get( 'status', '' ) == 'pending' && wpj_is_payment_type_enabled( 'sample_automatic', 'withdraw' ) ) { ?>

				<input class="button-secondary" type="submit" value="<?php echo __( 'Process Sample Requests', 'wpjobster-sample' ); ?>" name="processSamplePayRequest" id="processSamplePayRequest" />

				<script>
					jQuery( document ).ready( function( $ ) {

						$( 'a.mark-order-completed' ).on( 'click', function( e ) {

							if ( $( this ).hasClass( 'sample' ) ) {
								e.preventDefault();

								$( this ).parents( 'tr' ).find( 'input[type="checkbox"]' ).prop( "checked", true );

								$( '#processSamplePayRequest' ).trigger( 'click' );
							}
						});

					});
				</script>

			<?php }
		}

		public function processPaymentRequest() {

			if ( ! empty( $_POST['processSamplePayRequest'] ) ) {

				$wpjobster_sample_client_id  = wpj_get_option( 'wpjobster_sample_client_id' );
				$wpjobster_sample_secret_key = wpj_get_option( 'wpjobster_sample_secret_key' );

				if ( $wpjobster_sample_client_id && $wpjobster_sample_secret_key ) {

					global $wpdb;

					$payout_info = array();

					if ( isset( $_POST['requests'] ) ) {
						foreach ( $_POST['requests'] as $id ) {
							$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}job_withdraw WHERE id = %d AND methods LIKE '%Sample%'", $id ) );

							if ( ! empty( $row ) && $row->done == 0 ) {
								$sample_payee_id = wpj_user( $row->uid, 'sample_automatic_payee_id' ) ? wpj_user( $row->uid, 'sample_automatic_payee_id' ) : $row->payeremail;

								$payout_info[$id]['payee_id'] = $sample_payee_id;
								$payout_info[$id]['amount']   = $row->amount;
								$payout_info[$id]['userid']   = $row->uid;
								$payout_info[$id]['uniqueid'] = $id;

							}
						}
					}

					if ( $payout_info ) {

						// Process the payment
						foreach ( $payout_info as $key => $info ) {

						}

					} else {

						echo '
							<div class="notice notice-error is-dismissible">
								<p>' . __( 'No Sample order selected!', 'wpjobster-sample' ) . '</p>
							</div>
						';

					}

				} else {

					echo '
						<div class="notice notice-error is-dismissible">
							<p>' . __( 'Please fill Client ID and Secret Key fields!', 'wpjobster-sample' ) . '</p>
						</div>
					';

				}

			}

		}


		/* WITHDRAWAL FRONT - remove this section if your gateway doesn't support widthdrawal */

		public function addGatewayToPaymentsWithdrawalList( $default_gateways ) {
			$uid = get_current_user_id();

			if ( ! empty ( wpj_get_option( 'wpjobster_sample_withdrawal_enable' ) ) && wpj_get_option( 'wpjobster_sample_withdrawal_enable' ) != "disabled" ) {
				if ( get_user_meta( $uid, 'sample_email', true ) || get_user_meta( $uid, 'sample_automatic_payee_id', true ) ) $meta = true;
				else $meta = false;

				$default_gateways['sample'] = array(
					'name'  => 'sample',
					'label' => __( 'Sample', 'wpjobster' ),
					'meta'  => $meta
				);
			}

			return $default_gateways;
		}

		public function addGatewayDetailsInputToPaymentsWithdrawalList( $gateway ) {
			if ( $gateway['name'] == 'sample' ) {

				$uid = get_current_user_id();

				if ( ! empty( get_user_meta( $uid, 'sample_email', true ) ) ) { ?>

					<input value="<?php echo __( 'Sample Email', 'wpjobster-sample' ) . ': ' . get_user_meta( $uid, 'sample_payment_email', true ); ?>" type="hidden" size="30" name="details" />

				<?php } elseif ( ! empty( get_user_meta( $uid, 'sample_automatic_payee_id', true ) ) ) { ?>

					<input value="<?php echo __( 'Sample Payee ID','wpjobster-sample' ) . ': ' . get_user_meta( $uid, 'sample_automatic_payee_id', true ); ?>" type="hidden" size="30" name="details" />

				<?php }

			}
		}


		/* SETTINGS */

		public function checkCompatibility() {
			if ( ! function_exists( 'wpj_get_wpjobster_plugins_list' ) && ! defined( 'wpjobster_VERSION' ) )
				$error = sprintf( __( 'The current theme is not compatible with the %s gateway. Activate the WPJobster theme before installing this plugin.', 'wpjobster' ), $this->unique_id );

			if ( ! empty( $error ) ) wpj_deactivate_plugin( plugin_basename( __FILE__ ), $error );

			do_action( 'wpj_after_plugin_compatibility_errors', WPJ_SAMPLE_VERSION, 'Sample', plugin_basename( __FILE__ ), WPJ_SAMPLE_REQUIRED_THEME_VERSION );
		}

		public function translateGatewayName( $strings ) {
			$strings['sample'] = _x( 'Sample', 'Sample gateway', 'wpjobster' );
			return $strings;
		}

		public function setGatewayCurrency( $currency ) {
			// if the gateway requires a specific currency you can declare it there
			// currency conversions are done automatically
			$currency = 'USD'; // delete this line if the gateway works with any currency
			return $currency;
		}

	} // END CLASS

} // END IF CLASS EXIST

add_action( 'after_setup_theme', array( 'WPJobster_Sample_Loader', 'init' ) );