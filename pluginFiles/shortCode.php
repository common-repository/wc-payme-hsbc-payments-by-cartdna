<?php
function payme_gateway_store_token_oganro()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'payme_payment_gateway_token_oganro';
    $response = json_decode(file_get_contents('php://input'), true);
    $payme = new payme();
    $gatewayCode = $payme->gatewayCode;
    $token = $response['token'];
    $domain = $response['host_name'];
    $mode = $response['mode'];

    $check_token = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE gateway = '" . $gatewayCode . "'");
    if ($check_token == 0) {

        $insert = $wpdb->insert(
            $table_name,
            array(
                'gateway' => $gatewayCode,
                'domain' => $domain,
                'token' => $token,
                'test_mode' => $mode
            ),
            array('%s', '%s')
        );
        if ($insert) {
            $array = array(
                'msg' => 'success'
            );
            return wp_send_json($array, 200);
        } else {
            $array = array(
                'msg' => 'failed'
            );
            return wp_send_json($array, 500);
        }
    } elseif ($check_token == 1) {
        $update = $wpdb->update(
            $table_name,
            array(
                'test_mode' => $mode
            ),
            array('gateway' => $gatewayCode)
        );
        if ($update) {
            $array = array(
                'msg' => 'success'
            );
            return wp_send_json($array, 200);
        } else {
            $array = array(
                'msg' => 'failed'
            );
            return wp_send_json($array, 500);
        }
    } else {
        $array = array(
            'msg' => 'failed'
        );
        return wp_send_json($array, 500);
    }
}

function payme_payment_response_oganro()
{
    if (!isset($_POST['id']) or !isset($_POST['ors']) or !isset($_POST['msg'])) {
        return '<div style="text-align: center;">
              <h1>Something went wrong</h1>
        </div>';
    }
    $order_id = sanitize_text_field($_POST['id']);
    $orderResult = sanitize_text_field($_POST['ors']);
    $message = sanitize_text_field($_POST['msg']);
    $order     = new WC_Order((int)$order_id);
    $order_data = $order->get_data();
    $order_status = $order_data['status'];
    $payme = new payme();
    $hostName = $payme->hostName;

    if ($order_status == 'processing') {
        return '<div style="text-align: center;">
                <div style="text-align: center;">
                <h1>' . esc_html($message) . '</h1>

                                <p>Thank you for your order, we will love to have you with us again. Have a good day!!</p>
                <a class="button checkout-button alt"  href="' . esc_url($order->get_checkout_order_received_url()) . '">View Order Details</a>  
            </div>';
    } else {
        if ($orderResult == 'ss') {
            $order->update_status('processing');
            $order->add_order_note('Payment is Successful!!');
            $order->reduce_order_stock();
            return '<div style="text-align: center;">
            <div style="text-align: center;">
            <h1>' . esc_html($message) . '</h1>

                            <p>Thank you for your order, we will love to have you with us again. Have a good day!!</p>
            <a class="button checkout-button alt"  href="' . esc_url($order->get_checkout_order_received_url()) . '">View Order Details</a>  
        </div>';
        } else {
            if ($order_status == 'failed') {

                return '<div style="text-align: center;">

                <h1>' . esc_html($message) . '</h1>
                <a class="button checkout-button alt"  href="' . esc_url("https://" . $hostName . "/") . '">Return to Shop</a>  
            </div>';
            } else {

                $order->update_status('failed');
                $order->add_order_note($message);
                return '<div style="text-align: center;">
    
                                        <h1>' . esc_html($message) . '</h1>
                                        <a class="button checkout-button alt"  href="' . esc_url("https://" . $hostName . "/") . '">Return to Shop</a>  
                                    </div>';
            }
        }
    }
}

function payme_payment_callback_oganro()
{
    $response = json_decode(file_get_contents('php://input'), true);
    $order_id = $response['order_id'];
    $order     = new WC_Order($order_id);
    $order_data = $order->get_data();
    $order_status = $order_data['status'];
    if ($order_status != 'processing') {
        if ($response['order_redirect_status'] === 'succeeded') {
            $order->update_status('processing');
            $order->add_order_note("Payment is Successfull");
            $order->reduce_order_stock();
        } else {
            $order->update_status('failed');
            $order->add_order_note("Payment is unsuccessfull");
        }
    }
}
