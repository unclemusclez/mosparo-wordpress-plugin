<?php

namespace MosparoIntegration\Module\BBPress;

use MosparoIntegration\Helper\ConfigHelper;
use MosparoIntegration\Helper\FrontendHelper;
use MosparoIntegration\Helper\VerificationHelper;
use MosparoIntegration\Module\AbstractModule;

class BBPressModule extends AbstractModule
{
    protected $key = 'bbpress';

    public function __construct()
    {
        $this->name = __('bbPress', 'mosparo-integration');
        $this->description = __('Protects bbPress topic and reply forms with mosparo.', 'mosparo-integration');
        $this->dependencies = [
            'bbpress' => ['name' => __('bbPress', 'mosparo-integration'), 'url' => 'https://wordpress.org/plugins/bbpress/']
        ];
    }

    public function canInitialize()
    {
        return class_exists('bbPress');
    }

    public function initializeModule($pluginDirectoryPath, $pluginDirectoryUrl)
    {
        // Add mosparo field to topic and reply forms
        add_action('bbp_topic_form_fields', [$this, 'renderMosparoField']);
        add_action('bbp_reply_form_fields', [$this, 'renderMosparoField']);

        // Verify submissions
        add_filter('bbp_new_topic_pre_insert', [$this, 'validateTopic'], 10, 1);
        add_filter('bbp_new_reply_pre_insert', [$this, 'validateReply'], 10, 1);
    }

    public function renderMosparoField()
    {
        $connection = ConfigHelper::getInstance()->getConnectionFor($this->key, true);
        if ($connection === false) {
            echo '<p>' . __('No mosparo connection available. Please configure it in the mosparo settings.', 'mosparo-integration') . '</p>';
            return;
        }

        $options = [
            'inputFieldSelector' => 'textarea.bbp-the-content, input#bbp_topic_title',
        ];

        $frontendHelper = FrontendHelper::getInstance();
        echo '<div class="bbp-mosparo-row">';
        echo $frontendHelper->generateField($connection, $options);
        echo '</div>';
    }

    public function validateTopic($topic_data)
    {
        if (!function_exists('bbp_is_topic')) {
            return $topic_data;
        }

        $errors = $this->verifySubmission($topic_data);
        if (!empty($errors)) {
            bbp_add_error('mosparo_spam', $errors[0]);
        }

        return $topic_data;
    }

    public function validateReply($reply_data)
    {
        if (!function_exists('bbp_is_reply')) {
            return $reply_data;
        }

        $errors = $this->verifySubmission($reply_data);
        if (!empty($errors)) {
            bbp_add_error('mosparo_spam', $errors[0]);
        }

        return $reply_data;
    }

    protected function verifySubmission($data)
    {
        $errors = [];

        $connection = ConfigHelper::getInstance()->getConnectionFor($this->key, true);
        if ($connection === false) {
            $errors[] = __('A general error occurred: no available connection', 'mosparo-integration');
            return $errors;
        }

        $submitToken = trim(sanitize_text_field($_POST['_mosparo_submitToken'] ?? ''));
        $validationToken = trim(sanitize_text_field($_POST['_mosparo_validationToken'] ?? ''));

        if (empty($submitToken) || empty($validationToken)) {
            $errors[] = __('Verification failed: missing tokens.', 'mosparo-integration');
            return $errors;
        }

        $formData = apply_filters('mosparo_integration_bbpress_form_data', [
            'post_content' => $data['post_content'],
            'post_title'   => $data['post_title'] ?? '',
            'post_author'  => $data['post_author'],
        ]);

        $verificationHelper = VerificationHelper::getInstance();
        $verificationResult = $verificationHelper->verifySubmission($connection, $submitToken, $validationToken, $formData);

        if ($verificationResult === null) {
            $errors[] = sprintf(
                __('A general error occurred: %s', 'mosparo-integration'),
                $verificationHelper->getLastException()->getMessage()
            );
            return $errors;
        }

        $verifiedFields = array_keys($verificationResult->getVerifiedFields());
        $fieldDifference = array_diff(array_keys($formData), $verifiedFields);

        if (!$verificationResult->isSubmittable() || !empty($fieldDifference)) {
            $errors[] = __('Verification failed which means the form contains spam.', 'mosparo-integration');
        }

        return $errors;
    }
}