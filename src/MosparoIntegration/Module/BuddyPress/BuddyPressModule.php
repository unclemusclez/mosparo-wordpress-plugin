<?php

namespace MosparoIntegration\Module\BuddyPress;

use MosparoIntegration\Helper\ConfigHelper;
use MosparoIntegration\Helper\FrontendHelper;
use MosparoIntegration\Helper\VerificationHelper;
use MosparoIntegration\Module\AbstractModule;

class BuddyPressModule extends AbstractModule
{
    protected $key = 'buddypress';

    public function __construct()
    {
        $this->name = __('BuddyPress', 'mosparo-integration');
        $this->description = __('Protects BuddyPress registration forms with mosparo.', 'mosparo-integration');
        $this->dependencies = [
            'buddypress' => ['name' => __('BuddyPress', 'mosparo-integration'), 'url' => 'https://wordpress.org/plugins/buddypress/']
        ];
    }

    public function canInitialize()
    {
        return class_exists('BuddyPress');
    }

    public function initializeModule($pluginDirectoryPath, $pluginDirectoryUrl)
    {
        // Add mosparo field to the registration form
        add_action('bp_before_registration_submit_buttons', [$this, 'renderMosparoField']);

        // Validate registration submission
        add_action('bp_signup_validate', [$this, 'validateSignup']);
    }

    public function renderMosparoField()
    {
        $connection = ConfigHelper::getInstance()->getConnectionFor($this->key, true);
        if ($connection === false) {
            echo '<p>' . __('No mosparo connection available. Please configure it in the mosparo settings.', 'mosparo-integration') . '</p>';
            return;
        }

        $options = [
            'inputFieldSelector' => '#signup_username, #signup_email, #signup_password', // Target BuddyPress registration fields
        ];

        $frontendHelper = FrontendHelper::getInstance();
        echo '<div class="register-section" id="mosparo-section">';
        echo '<div class="editfield">';
        echo '<label>' . __('Spam Protection', 'mosparo-integration') . '</label>';
        
        // Display error if validation failed
        global $bp;
        if (!empty($bp->signup->errors['mosparo_validation'])) {
            echo '<div class="error">';
            echo esc_html($bp->signup->errors['mosparo_validation']);
            echo '</div>';
        }

        echo $frontendHelper->generateField($connection, $options);
        echo '</div>';
        echo '</div>';
    }

    public function validateSignup()
    {
        global $bp;

        $connection = ConfigHelper::getInstance()->getConnectionFor($this->key, true);
        if ($connection === false) {
            $bp->signup->errors['mosparo_validation'] = __('A general error occurred: no available connection', 'mosparo-integration');
            return;
        }

        $submitToken = trim(sanitize_text_field($_POST['_mosparo_submitToken'] ?? ''));
        $validationToken = trim(sanitize_text_field($_POST['_mosparo_validationToken'] ?? ''));

        if (empty($submitToken) || empty($validationToken)) {
            $bp->signup->errors['mosparo_validation'] = __('Verification failed: missing tokens.', 'mosparo-integration');
            return;
        }

        // Prepare form data from BuddyPress registration fields
        $formData = apply_filters('mosparo_integration_buddypress_form_data', [
            'signup_username' => sanitize_user($_POST['signup_username'] ?? ''),
            'signup_email'    => sanitize_email($_POST['signup_email'] ?? ''),
            'signup_password' => $_POST['signup_password'] ?? '', // Passwords are typically not sanitized for verification
        ]);

        $verificationHelper = VerificationHelper::getInstance();
        $verificationResult = $verificationHelper->verifySubmission($connection, $submitToken, $validationToken, $formData);

        if ($verificationResult === null) {
            $bp->signup->errors['mosparo_validation'] = sprintf(
                __('A general error occurred: %s', 'mosparo-integration'),
                $verificationHelper->getLastException()->getMessage()
            );
            return;
        }

        $verifiedFields = array_keys($verificationResult->getVerifiedFields());
        $fieldDifference = array_diff(array_keys($formData), $verifiedFields);

        if (!$verificationResult->isSubmittable() || !empty($fieldDifference)) {
            $bp->signup->errors['mosparo_validation'] = __('Verification failed which means the form contains spam.', 'mosparo-integration');
        }
    }
}