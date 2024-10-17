jQuery(document).ready(function($) {
    $('#connection-button').on('click', function(e) {
        if ($(this).val() === docswriteData.disconnectButtonText) {
            if (!confirm(docswriteData.disconnectConfirmMessage)) {
                e.preventDefault();
            }
        }
    });
});