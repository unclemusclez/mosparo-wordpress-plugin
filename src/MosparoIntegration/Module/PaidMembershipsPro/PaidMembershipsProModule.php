<?php

namespace MosparoIntegration\Module\PaidMembershipsPro;

use MosparoIntegration\Module\AbstractModule;
use MosparoIntegration\Module\ModuleSettings;
use MosparoIntegration\ModuleForm\CheckoutForm;

class PaidMembershipsProModule extends AbstractModule
{
    protected $key = 'paidmembershipspro';

    public function __construct()
    {
        $this->name = __('Paid Memberships Pro', 'mosparo-integration');
        $this->description = __('Protects the Paid Memberships Pro checkout form with Mosparo.', 'mosparo-integration');
        $this->dependencies = [
            'paid-memberships-pro' => ['name' => __('Paid Memberships Pro', 'mosparo-integration'), 'url' => 'https://wordpress.org/plugins/paid-memberships-pro/']
        ];
        $this->settings = new ModuleSettings(
            [
                'checkout_form' => [
                    'label' => __('Checkout Form', 'mosparo-integration'),
                    'description' => __('Protect the PMPro checkout form with Mosparo', 'mosparo-integration'),
                    'type' => 'boolean',
                    'value' => true,
                ],
            ],
            [
                'header' => __('Please choose whether to protect the Paid Memberships Pro checkout form with Mosparo.', 'mosparo-integration'),
            ]
        );
    }

    public function canInitialize()
    {
        return function_exists('pmpro_getLevelAtCheckout');
    }

    public function initializeModule($pluginDirectoryPath, $pluginDirectoryUrl)
    {
        // Filter to prepare checkout form data for Mosparo verification
        add_filter('mosparo_integration_' . $this->getKey() . '_checkout_form_data', function() {
            $data = [
                'username' => isset($_POST['username']) ? sanitize_user($_POST['username']) : '',
                'bemail' => isset($_POST['bemail']) ? sanitize_email($_POST['bemail']) : '',
            ];
            return $data;
        }, 1, 0);

        if ($this->getSettings()->getFieldValue('checkout_form')) {
            $checkoutForm = new CheckoutForm($this);
            add_action('pmpro_checkout_before_submit_button', [$checkoutForm, 'displayMosparoField']);
            add_filter('pmpro_registration_checks', [$checkoutForm, 'verifyCheckoutForm'], 10, 1);
        }
    }
}