<?php
class MMG_Checkout_Payment_Rewrites {
    public static function init() {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^wc-api/mmg-checkout/([^/]+)/?',
            'index.php?mmg-checkout=1&callback_key=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^wc-api/mmg-checkout/([^/]+)/errorpayment/?',
            'index.php?mmg-checkout=errorpayment&callback_key=$matches[1]',
            'top'
        );
        add_rewrite_tag('%mmg-checkout%', '([^&]+)');
    }
}