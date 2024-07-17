<?php
/*
Plugin Name: MMG Checkout Payment
Description: Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
Version: 1.0
Author: Kalpa Services Inc.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MMG_Checkout_Payment {
    private $base_url;
    private $client_id;
    private $merchant_id;
    private $secret_key;
    private $rsa_public_key;

    public function __construct() {
        // Initialize plugin
        $this->base_url = home_url('/'); // Set the base URL to the site's domain
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('mmg_checkout_button', array($this, 'checkout_button_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_action('wp_ajax_nopriv_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('plugins_loaded', array($this, 'init_gateway_class'));
        add_action('wp_ajax_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));
        add_action('wp_ajax_nopriv_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));
    }

    public function add_admin_menu() {
        add_options_page('MMG Checkout Settings', 'MMG Checkout', 'manage_options', 'mmg-checkout-settings', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('mmg_checkout_settings', 'mmg_client_id');
        register_setting('mmg_checkout_settings', 'mmg_merchant_id');
        register_setting('mmg_checkout_settings', 'mmg_secret_key');
        register_setting('mmg_checkout_settings', 'mmg_rsa_public_key');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>MMG Checkout Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mmg_checkout_settings');
                do_settings_sections('mmg_checkout_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Base URL</th>
                        <td><input type="text" value="<?php echo esc_attr($this->base_url); ?>" readonly /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Client ID</th>
                        <td><input type="text" name="mmg_client_id" value="<?php echo esc_attr(get_option('mmg_client_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Merchant ID</th>
                        <td><input type="text" name="mmg_merchant_id" value="<?php echo esc_attr(get_option('mmg_merchant_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Secret Key</th>
                        <td><input type="password" name="mmg_secret_key" value="<?php echo esc_attr(get_option('mmg_secret_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">RSA Public Key</th>
                        <td><textarea name="mmg_rsa_public_key"><?php echo esc_textarea(get_option('mmg_rsa_public_key')); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function checkout_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '',
            'description' => '',
        ), $atts);

        return '<button class="mmg-checkout-button" data-amount="' . esc_attr($atts['amount']) . '" data-description="' . esc_attr($atts['description']) . '">Pay with MMG</button>';
    }

    public function enqueue_scripts() {
        wp_enqueue_script('mmg-checkout', plugin_dir_url(__FILE__) . 'js/mmg-checkout.js', array('jquery'), '1.0', true);
        wp_localize_script('mmg-checkout', 'mmg_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function generate_checkout_url() {
        // Verify nonce and user capabilities here

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Invalid order');
        }

        $amount = $order->get_total();
        $description = 'Order #' . $order->get_order_number();

        $token_data = array(
            'secretKey' => get_option('mmg_secret_key'),
            'amount' => $amount,
            'merchantId' => get_option('mmg_merchant_id'),
            'merchantTransactionId' => $order_id,
            'productDescription' => $description,
            'requestInitiationTime' => (string) round(microtime(true) * 1000),
            'merchantName' => get_bloginfo('name'),
            'returnUrl' => add_query_arg('wc-api', 'mmg_payment_confirmation', home_url('/')),
        );

        $token = $this->encrypt_and_encode($token_data);

        $checkout_url = add_query_arg(array(
            'X-Client-ID' => get_option('mmg_client_id'),
            'token' => $token,
            'merchantId' => get_option('mmg_merchant_id'),
        ), $this->base_url);

        wp_send_json_success(array('checkout_url' => $checkout_url));
    }

    private function encrypt_and_encode($data) {
        $json = json_encode($data);
        $public_key = openssl_pkey_get_public(get_option('mmg_rsa_public_key'));
        openssl_public_encrypt($json, $encrypted, $public_key);
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_MMG_Gateway';
        return $gateways;
    }

    public function init_gateway_class() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-mmg-gateway.php';
    }

    public function handle_payment_confirmation() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Invalid order', 'MMG Checkout Error', array('response' => 400));
        }

        // Verify the payment status with MMG API here
        // This is a placeholder for the actual verification process
        $payment_verified = $this->verify_payment_with_mmg($order_id, $status);

        if ($payment_verified) {
            $order->payment_complete();
            $order->add_order_note('Payment completed via MMG Checkout.');
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            $order->update_status('failed', 'Payment failed or was cancelled.');
            wp_redirect($order->get_checkout_payment_url());
        }
        exit;
    }

    private function verify_payment_with_mmg($order_id, $status) {
        // Implement the actual verification process here
        // This should involve making an API call to MMG to confirm the payment status
        // For now, we'll just check if the status is 'success'
        return $status === 'success';
    }
}

new MMG_Checkout_Payment();