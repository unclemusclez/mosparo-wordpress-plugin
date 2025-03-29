<?php

namespace MosparoIntegration\ModuleForm;

use MosparoIntegration\Helper\VerificationHelper;

class CheckoutForm extends AbstractForm // Correct class name
{
    public function displayMosparoField()
    {
        $this->loadResources();

        $connection = $this->getConnection();
        if (!$connection) {
            return;
        }

        $host = $connection->getHost();
        $uuid = $connection->getUuid();
        $publicKey = $connection->getPublicKey();

        ?>
        <script type="text/javascript" src="<?php echo esc_url($host . '/mosparo.js'); ?>" async></script>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                new mosparo('mosparo-box', '<?php echo esc_js($uuid); ?>', '<?php echo esc_js($publicKey); ?>', {
                    loadCssResource: true,
                    onSuccess: function(token) {
                        document.getElementById('mosparo_token').value = token;
                    }
                });
            });
        </script>
        <div id="mosparo-box"></div>
        <input type="hidden" id="mosparo_token" name="mosparo_token" value="" />
        <?php
    }

    public function verifyCheckoutForm($continue)
    {
        if (!$continue || is_user_logged_in()) {
            return $continue;
        }

        $connection = $this->getConnection();
        if (!$connection || empty($_POST['mosparo_token'])) {
            pmpro_setMessage(__('Spam protection failed. Please try again.', 'mosparo-integration'), 'pmpro_error');
            return false;
        }

        $verificationHelper = VerificationHelper::getInstance();
        $result = $verificationHelper->verifySubmission(sanitize_text_field($_POST['mosparo_token']));

        if (!$result || !$result['valid']) {
            pmpro_setMessage(__('Your submission was flagged as suspicious. Please try again.', 'mosparo-integration'), 'pmpro_error');
            return false;
        }

        return true;
    }
}