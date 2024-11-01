<?php
function payme_enqueue_admin_scripts($hooks)
{

    if ($hooks === 'toplevel_page_paymeadminmenu') {
        $plugin_dir_path = plugin_dir_url(__FILE__);
        wp_enqueue_script('payme-admin-main-menu-page-jquery', plugin_dir_url(__FILE__) . '/js/adminMenupage.js', array('jquery'));
        wp_localize_script(
            'payme-admin-main-menu-page-jquery',
            'payme_plugin_ajax_object_verify_client',
            array('ajax_url' => admin_url('admin-ajax.php'))
        );
        wp_enqueue_style('payme-bootstrap-css-file', $plugin_dir_path . 'css/bootstrap.min.css');
        // wp_enqueue_script('payme-qr-js-file', $plugin_dir_path . 'js/bootstrap.min.js', array('jquery'));
    }
    if ($hooks === 'payme_page_paymeadminsubmenu') {
        $plugin_dir_path = plugin_dir_url(__FILE__);
        wp_enqueue_script('payme-admin-main-menu-page-jquery', plugin_dir_url(__FILE__) . '/js/adminSubMenupage.js', array('jquery'));
        wp_enqueue_style('payme-bootstrap-css-file', $plugin_dir_path . 'css/bootstrap.min.css');
        wp_localize_script(
            'payme-admin-main-menu-page-jquery',
            'payme_plugin_ajax_object_configuration',
            array('ajax_url' => admin_url('admin-ajax.php'))
        );
    }
}

function verifyPaymeClientSecurityToken_callback()
{
    $payme = new payme();
    $pageUrl = $payme->verifyUrl;
    $data = array();
    // print_r($_POST);
    // print_r($pageUrl);
    // exit();
    if (!isset($_POST['return_url']) or !isset($_POST['home_url']) or !isset($_POST['gateway_code']) or !isset($_POST['verify_token'])) {
        $data = array('res' => "error");
        echo json_encode($data);
        wp_die();
        exit();
    }
    $returnUrl = sanitize_text_field($_POST['return_url']);
    $homeUrl = sanitize_text_field($_POST['home_url']);
    $gatewayCode = sanitize_text_field($_POST['gateway_code']);
    $verifyToken = sanitize_text_field($_POST['verify_token']);

    $getHostName = payme::getDomainName($returnUrl);
    $getClientDomain = payme::getHomeDomainName($homeUrl);

    if ((strpos($getClientDomain, $getHostName) !== 0)) {
        $data = array('res' => "error");
        echo json_encode($data);
        wp_die();
        exit();
    }
    $requestData = array(
        'return_url' => $returnUrl,
        'gateway_code' => $gatewayCode,
        'verify_token' => $verifyToken,
        'host_name' => $getClientDomain
    );

    $args = array(
        'body' => $requestData,
        'headers' => array(
            'Content-Type: application/json'
        ),
    );

    $response = wp_remote_post($pageUrl, $args);
    // print_r($response);
    // exit();
    if ($response['response']['code'] === 200) {
        $responseData = json_decode($response['body'], true);
        if ($responseData['result'] === 'failed') {
            $data = array('res' => "error");
            echo json_encode($data);
            wp_die();
        } elseif ($responseData['result'] === 'success') {
            $clientDetails = $responseData['detailsData'];
            $formData = $responseData['data'];

            $htmlData = $payme->constructConfigForm($clientDetails, $formData);
            $data = array(
                'res' => "success",
                'html' => $htmlData
            );
            echo json_encode($data);
            wp_die();
        } elseif ($responseData['result'] === 'duplicate') {
            $data = array(
                'res' => "duplicate",

            );
            echo json_encode($data);
            wp_die();
        } elseif ($responseData['result'] === 'reinstall') {
            $data = array(
                'res' => "reinstall",

            );
            echo json_encode($data);
            wp_die();
        }
    }
}

function storePaymeConfigData_callback()
{
    $data = array();
    $payme = new payme();
    $pageUrl = $payme->storeConfig;
    if (!isset($_POST['formData'])) {
        $data = array('res' => "error");
        echo json_encode($data);
        wp_die();
        exit();
    }

    $requestData = payme::sanitizeInput($_POST['formData']);
    $args = array(
        'body' => $requestData,
        'headers' => array(
            'Content-Type: application/json'
        ),
    );
    $response = wp_remote_post($pageUrl, $args);
    if ($response['response']['code'] === 200) {
        $responseData = json_decode($response['body'], true);
        if ($responseData['result'] === 'success') {
            $data = array(
                'res' => "success",
            );
        } else {
            $data = array(
                'res' => "error",
            );
        }
    } else {
        $data = array(
            'res' => "error",
        );
    }
    echo json_encode($data);
    wp_die();
}

function showPaymeClientConfigRecords_callback()
{
    global $wpdb;
    $payme = new payme();
    $table_name = $wpdb->prefix . 'payme_payment_gateway_token_oganro';
    $gatewayCode = $payme->gatewayCode;
    $pageUrl = $payme->getConfigData;
    if (!isset($_POST['gateway_code'])) {
        $data = array('res' => "error");
        echo json_encode($data);
        wp_die();
        exit();
    }
    $gatewayCode = sanitize_text_field($_POST['gateway_code']);
    $getHostName = $wpdb->get_results("SELECT * FROM $table_name WHERE gateway = '" . $gatewayCode . "'");
    $hostName = $getHostName[0]->domain;
    $requestData = array(
        'host_name' => $hostName,
        'gateway_code' => $gatewayCode
    );

    $args = array(
        'body' => $requestData,
        'headers' => array(
            'Content-Type: application/json'
        ),
    );
    $data = array();
    $response = wp_remote_post($pageUrl, $args);
    if ($response['response']['code'] === 200) {
        $responseData = json_decode($response['body'], true);

        if ($responseData['result'] === 'error') {
            $data = array('res' => "error");
        } elseif ($responseData['result'] === 'success') {
            $htmlData = $payme->constructUpdateConfigForm($responseData);

            $data = array(
                'res' => "success",
                'html' => $htmlData,
                'provider' => $responseData['provider'],
                'testMode' => $responseData['testMode'],
                'isLiveActivated' => $responseData['isLiveActivated']
            );
        }
    }
    echo json_encode($data);
    wp_die();
}

function updatePaymeClientConfigRecords_callback()
{
    $data = array();
    $payme = new payme();
    $pageUrl = $payme->updateConfigData;

    if (!isset($_POST['formData'])) {
        $data = array('res' => "error");
        echo json_encode($data);
        wp_die();
        exit();
    }
    $requestData = payme::sanitizeInput($_POST['formData']);
    $args = array(
        'body' => $requestData,
        'headers' => array(
            'Content-Type: application/json'
        ),
    );

    $response = wp_remote_post($pageUrl, $args);
    if ($response['response']['code'] === 200) {
        $responseData = json_decode($response['body'], true);
        if ($responseData['result'] === 'success') {
            $data = array(
                'res' => "success",
                'msg' => $responseData['message']
            );
            echo json_encode($data);
            wp_die();
        }
    }
}


    //  wp_enqueue_style('payme-bootstrap-css-file', $plugin_dir_path . 'css/option.css');