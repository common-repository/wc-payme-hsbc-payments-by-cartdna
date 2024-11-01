<?php

function activate_payme_payment_gateway_oganro()
{
    $plugin_path = plugin_dir_path(__FILE__);

    global $wpdb;
    global $jal_db_version;

    $charset_collate = '';

    if (!empty($wpdb->charset)) {
        $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
    }

    if (!empty($wpdb->collate)) {
        $charset_collate .= " COLLATE {$wpdb->collate}";
    }
    //callback_response

    add_option('jal_db_version', $jal_db_version);
    //create a table for pages
    $table_name_page = $wpdb->prefix . 'payme_payment_gateway_pages_oganro';
    $sql_page = "CREATE TABLE $table_name_page ( 
        `id` INT NOT NULL AUTO_INCREMENT,
        `page_title` VARCHAR(256) NOT NULL ,
        `page_content` TEXT NOT NULL ,
        `status` INT NOT NULL ,
        
         UNIQUE KEY id (id) ) $charset_collate; ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_page);


    $table_name_page = $wpdb->prefix . 'payme_payment_gateway_token_oganro';
    $sql_page = "CREATE TABLE $table_name_page ( 
        `id` INT NOT NULL AUTO_INCREMENT,
        `gateway` VARCHAR(256) NOT NULL ,
        `domain` VARCHAR(256) NOT NULL ,
        `token` TEXT NOT NULL ,
        `test_mode` VARCHAR(256) NOT NULL,
       
         UNIQUE KEY id (id) ) $charset_collate; ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_page);

    //insert the page data
    insert_payme_payment_gateway_page_contents_oganro();
    //create the pages
    add_payme_payment_gateway_pages_oganro();

    // exit(wp_redirect(admin_url('admin.php?page=paymeadminmenu')));
}
function insert_payme_payment_gateway_page_contents_oganro()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'payme_payment_gateway_pages_oganro';
    $plugin_dir = plugin_dir_url(__FILE__);
    $array = array(

        'Pay with Payme' => '[shortcode-payme-payment-gateway-paypage]',
        'Payme Response' => '[shortcode-payme-payment-gateway-response]',
        'Payme Callback' => '[shortcode-payme-payment-gateway-callback]',
        'Payme gateway installation' => '[shortcode-payme-payment-gateway-store-token]',

    );
    foreach ($array as $key => $value) {
        $wpdb->insert(
            $table_name,
            array(
                'page_title' => $key,
                'page_content' =>  $value,
                'status' => 0
            )
        );
    }
}

function add_payme_payment_gateway_pages_oganro()
{
    // Create post object
    global $wpdb;
    $table_name = $wpdb->prefix . 'payme_payment_gateway_pages_oganro';
    $content = $wpdb->get_results("SELECT * FROM $table_name");

    for ($i = 0; $i <= 3; $i++) {
        $page_id = $content[$i]->id;
        $page_title = $content[$i]->page_title;
        $page_content = $content[$i]->page_content;
        $page_status = $content[$i]->status;
        //check if the status == 0
        if ($page_status === '0') {
            //create a page
            $my_post = array(
                'post_title'    => wp_strip_all_tags($page_title),
                'post_content'  => $page_content,
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_type'     => 'page',
            );
            wp_insert_post($my_post);

            //update status
            $wpdb->update(
                $table_name,
                array(
                    'status' => '1'
                ),
                array('id' => $page_id)
            );
        }
    }
}

function uninstall_payme_payment_gateway_oganro()
{
    global $wpdb;
    $payme = new payme();
    $gatewayCode = $payme->gatewayCode;
    $pageUrl = $payme->unInstallUrl;

    $table = $wpdb->prefix . 'posts';
    $wpdb->query(
        "DELETE FROM $table
         WHERE post_title IN('Pay with Payme', 'Payme Response' , 'Payme Callback','Payme gateway installation')
        "
    );

    $table_name = $wpdb->prefix . 'payme_payment_gateway_pages_oganro';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    $table_plugin = $wpdb->prefix . 'payme_payment_gateway_token_oganro';
    //get host name brfore delete
    $getHostName = $wpdb->get_results("SELECT * FROM $table_plugin WHERE gateway = '" . $gatewayCode . "'");
    $hostName = $getHostName[0]->domain;
    $wpdb->query("DROP TABLE IF EXISTS $table_plugin");


    $requestData = array(
        'gateway_code' => $gatewayCode,
        'host_name' => $hostName
    );
    $args = array(
        'body' => $requestData,
        'headers' => array(
            'Content-Type: application/json'
        ),
    );

    $response = wp_remote_post($pageUrl, $args);
}

function oganro_register_admin_menu_payme()
{
    // print_r(plugin_dir_path(__FILE__) . 'admin/top-menu.php');
    // exit();
    add_menu_page(
        'PaymePage',
        'payme',
        'manage_options',
        'paymeadminmenu',
        'sd_display_top_level_menu_page_payme',
        '',
        6
    );
    add_submenu_page(
        'paymeadminmenu',
        'Custom Submenu Page Title',
        'Configuration',
        'manage_options',
        'paymeadminsubmenu',
        'sd_display_sub_menu_page_payme'
    );
}


add_action('admin_menu', 'oganro_register_admin_menu_payme');
