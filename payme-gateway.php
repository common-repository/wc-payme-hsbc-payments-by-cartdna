<?php

/**
 * Plugin Name: CartDNA’s PayMe HSBC Gateway for WooCommerce
 * Description: Payme Payment gateway that will allow you to make your payment with woocommerce.
 * Version: 1.0
 * Author: CartDNA
 * Author URI: https://www.cartdna.com/
 */

//scriptFile.php
if (!defined('ABSPATH')) {
    die;
}

require __DIR__ . '/pluginFiles/payme.php';
require __DIR__ . '/pluginFiles/installationHooks.php';
require __DIR__ . '/pluginFiles/admin/top-menu.php';
require __DIR__ . '/pluginFiles/admin/kl-config-menu.php';
require __DIR__ . '/pluginFiles/scriptFile.php';
require __DIR__ . '/pluginFiles/shortCode.php';

register_activation_hook(__FILE__, 'activate_payme_payment_gateway_oganro');

//deactivation
register_deactivation_hook(__FILE__, 'uninstall_payme_payment_gateway_oganro');

add_action('plugins_loaded', 'payme_payment_gateway_init', 11);

function payme_payment_gateway_init()
{
    class WC_payme_payment_gateway extends WC_Payment_Gateway
    {
        public $clientDomainName;
        public function __construct()
        {
            $plugin_dir = plugin_dir_url(__FILE__);
            /**
             * Following detials will define the gateway and make it visible in the payment area
             */
            $this->id                 = 'payme_payment_gateway_method';
            $this->icon               = apply_filters('woocommerce_frist_payment_icon', '' . $plugin_dir . 'image/payme.png');
            $this->has_fields         = false;
            $this->method_title       = __('payme Payment Gateway', 'wc-payme-payment-gateway');
            $this->method_description = __('One of the fastest ways to pay your orders', 'wc-payme-payment-gateway');
            $this->clientDomainName = $_SERVER['HTTP_HOST'];



            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            //these are the values stored in the setting page(save chances)	  
            $this->title                     = $this->get_option('title');
            $this->description             = $this->get_option('description');
            $this->checkout_msg            = $this->get_option('checkout_msg');


            add_action('init', array(&$this, 'check_payme_gateway_transaction_response'));
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            //add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            // Customer Emails
            add_filter('woocommerce_ship_to_different_address_checked', '__return_true');
            //add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }
        function init_form_fields()
        {

            $this->form_fields = array(
                'enabled'     => array(
                    'title'         => __('Enable/Disable', 'wc-payme-payment-gateway'),
                    'type'             => 'checkbox',
                    'label'         => __('Enable payme Payment Gateway.', 'wc-payme-payment-gateway'),
                    'default'         => 'no'
                ),

                'title'     => array(
                    'title'         => __('Title:', 'wc-payme-payment-gateway'),
                    'type'            => 'text',
                    'description'     => __('This controls the title which the user sees during checkout.', 'wc-payme-payment-gateway'),
                    'default'         => __('Payme Payment Gateway', 'wc-payme-payment-gateway')
                ),

                'description' => array(
                    'title'         => __('Description:', 'wc-payme-payment-gateway'),
                    'type'            => 'textarea',
                    'description'     => __('This controls the description which the user sees during checkout.', 'wc-payme-payment-gateway'),
                    'default'         => __('payme Payment Gateway', 'wc-payme-payment-gateway')
                ),
                'checkout_msg' => array(
                    'title'         => __('Checkout Message:', 'wc-payme-payment-gateway'),
                    'type'            => 'textarea',
                    'description'     => __('Message display when checkout'),
                    'default'         => __('Thank you for your order, please click the button below to pay with the secured Payme payment gateway.', 'wc-payme-payment-gateway')
                ),
            );
        }
        function receipt_page($order)
        {
            global $woocommerce;
            $order_details = new WC_Order($order);

            echo $this->generate_ipg_form($order);
        }
        public function generate_ipg_form($order_id)
        {
            global $wpdb;
            global $woocommerce;
            $table_name = $wpdb->prefix . 'payme_payment_gateway_token_oganro';
            $payme = new payme();
            $gatewayCode = $payme->gatewayCode;
            $pageName = $payme->paymeIndex;
            // print_r($gatewayCode);
            // exit();
            $order = new WC_Order($order_id);
            $productinfo = "Order $order_id";
            $plugin_dir = plugin_dir_url(__FILE__);
            // $currency_code     = $this->currency_code;
            $curr_symbol     = get_woocommerce_currency();
            $paymentHtml = "";

            $check_token = $wpdb->get_results("SELECT * FROM $table_name WHERE gateway = '" . $gatewayCode . "'");

            if (empty($check_token[0]->token)) {
                print_r("Please use other available payment methods!!!");
                exit();
            } else {
                $mode = $check_token[0]->test_mode;
                if ($mode === 'test') {
                    echo '<div style="padding: 10px;background-color:#f44336;color:white;border-radius:5px;">
                                     You are in a test mode!!
                                 </div>';
                }

                $payload = [
                    "token" => $check_token[0]->token,
                    "testMode" => $mode,
                    "hostName" => $check_token[0]->domain,
                    "orderId" => $order_id,
                    "gatewayToken" => $gatewayCode,
                    "amount" => $order->get_total(),
                    "currency" => $curr_symbol,
                    "first_name" => $order->get_billing_first_name(),
                    "last_name" => $order->get_billing_last_name(),
                    "email" => $order->get_billing_email(),
                    "city" => $order->get_billing_city(),
                    "country" => $order->get_billing_country(),
                    "line1" => $order->get_billing_address_1(),
                    "postal_code" => $order->get_billing_postcode(),
                ];


                $form_args_array = array();
                foreach ($payload as $key => $value) {
                    $form_args_array[] = "<input type='hidden' name='" . esc_attr($key) . "' value='" . esc_attr($value) . "'/>";
                }

                return '</p>
                            <form action="' . esc_url($pageName) . '" method="post">
                            ' . implode('', $form_args_array) . ' 
                            
                            <br />
                             <p>' . esc_html($this->checkout_msg) . '</p>
                            <input type="submit" class="checkout-button button alt" id="submit_form" value="Make The Payment" /> 
                            <a class="button"  href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel', 'ognro') . '</a>  
                            </form>';
            }
        }

        //Once Customer chose the payment method, and click the place order
        //this function will be executed
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);

            return array(
                'result' => 'success', 'redirect' => add_query_arg(
                    'order',
                    $order->id,
                    add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))
                )
            );
        }
    }
    function wc_add_payme_payment_to_gateways($gateways)
    {
        $gateways[] = 'WC_payme_payment_gateway';
        return $gateways;
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_payme_payment_to_gateways');
}

add_shortcode('shortcode-payme-payment-gateway-response', 'payme_payment_response_oganro');
add_shortcode('shortcode-payme-payment-gateway-callback', 'payme_payment_callback_oganro');
add_shortcode('shortcode-payme-payment-gateway-store-token', 'payme_gateway_store_token_oganro');

//applying custom pages
add_action('admin_enqueue_scripts', 'payme_enqueue_admin_scripts');

//verify client
add_action('wp_ajax_verifyPaymeClientSecurityToken', 'verifyPaymeClientSecurityToken_callback');
add_action('wp_ajax_nopriv_verifyPaymeClientSecurityToken', 'verifyPaymeClientSecurityToken_callback');

//store merchant details
add_action('wp_ajax_storePaymeConfigData', 'storePaymeConfigData_callback');
add_action('wp_ajax_nopriv_storePaymeConfigData', 'storePaymeConfigData_callback');

//show saved config data
add_action('wp_ajax_showPaymeClientConfigRecords', 'showPaymeClientConfigRecords_callback');
add_action('wp_ajax_nopriv_showPaymeClientConfigRecords', 'showPaymeClientConfigRecords_callback');

//update config data
add_action('wp_ajax_updatePaymeClientConfigRecords', 'updatePaymeClientConfigRecords_callback');
add_action('wp_ajax_nopriv_updatePaymeClientConfigRecords', 'updatePaymeClientConfigRecords_callback');

//woocommerce hooks
// add_action('woocommerce_review_order_after_payment', 'klana_show_test_mode_checkout');
