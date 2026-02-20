/*+***********************************************************************************
 * WordExport Injection Script
 * Injected into Quotes Detail View via HEADERSCRIPT link
 *************************************************************************************/

// Ensure we only bind this once
if (typeof WordExportInitialized === 'undefined') {
    var WordExportInitialized = true;

    jQuery(document).ready(function () {
        // Function to inject button if it doesn't exist
        function injectWordExportButton() {
            var module = app.getModuleName();
            var view = app.getViewName();
            var targetModules = ['Quotes', 'SalesOrder', 'Invoice', 'PurchaseOrder'];

            if (targetModules.indexOf(module) !== -1 && view === 'Detail') {
                // Prevent Duplicate Button
                if (jQuery('#WordExportBtn').length > 0) {
                    return;
                }

                // Button Definition
                var buttonHtml = '<div class="btn-group">' +
                    '<button class="btn btn-default" id="WordExportBtn" title="Word Export">' +
                    '<i class="fa fa-file-word-o"></i> <strong>Word Export</strong>' +
                    '</button>' +
                    '</div>';

                var toolbar = jQuery('.detailview-header .btn-group').last();
                if (toolbar.length) {
                    toolbar.before(buttonHtml + '&nbsp;');
                } else {
                    jQuery('.detailview-action-links').prepend(buttonHtml);
                }
            }
        }

        // Try injecting on initial load
        setTimeout(injectWordExportButton, 500);

        // Also hook into Vtiger's AJAX page load events (for V7)
        app.listenPostAjaxReady(function () {
            injectWordExportButton();
        });

        // Handle Click via Event Delegation (fixes broken buttons if DOM is recreated)
        jQuery(document).on('click', '#WordExportBtn', function (e) {
            e.preventDefault();
            var module = app.getModuleName();
            var detailInstance = Vtiger_Detail_Js.getInstance();
            var recordId = detailInstance.getRecordId();

            var params = {
                'module': 'WordExport',
                'view': 'Popup',
                'source_module': module,
                'record': recordId
            };

            // Vtiger 7 Modal Handling Pattern
            if (typeof app.request !== 'undefined' && typeof app.helper !== 'undefined') {
                app.request.post({ 'data': params }).then(function (error, data) {
                    if (error === null) {
                        app.helper.showModal(data);
                    }
                });
            } else {
                // Fallback for older Vtiger versions if app.request is not available
                var popupUrl = 'index.php?' + jQuery.param(params);
                window.open(popupUrl, '_blank', 'width=600,height=400');
            }
        });
    });
}
