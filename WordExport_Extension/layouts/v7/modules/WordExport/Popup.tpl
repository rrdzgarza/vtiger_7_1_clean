{* License Text *}
<div class="modal-dialog model-container">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h3 class="modal-title">{vtranslate('LBL_EXPORT_TO_WORD', $MODULE)}</h3>
        </div>
        <div class="modal-body">
            <form id="wordExportForm" class="form-horizontal">
                <input type="hidden" name="module" value="WordExport" />
                <input type="hidden" name="action" value="Export" />
                <input type="hidden" name="record" value="{$RECORD}" />
                <input type="hidden" name="source_module" value="{$SOURCE_MODULE}" />

                <div class="form-group">
                    <label class="control-label col-sm-3">{vtranslate('LBL_SELECT_TEMPLATE', $MODULE)}</label>
                    <div class="col-sm-8">
                        <select name="template" class="form-control" style="width: 100%;">
                            {foreach from=$TEMPLATES item=TEMPLATE}
                                <option value="{$TEMPLATE}">{$TEMPLATE}</option>
                            {/foreach}
                        </select>
                        <div style="margin-top: 5px; text-align: right;">
                            <a href="index.php?module=WordExport&view=ListTemplates" target="_blank" style="font-size: 11px;">
                                <i class="fa fa-cog"></i> Manage Templates
                            </a>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="format" value="pdf" />

                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-8">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="save_to_docs" value="1"> {vtranslate('LBL_SAVE_TO_DOCUMENTS', $MODULE)}
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" data-dismiss="modal">{vtranslate('LBL_CANCEL', $MODULE)}</button>
            <button class="btn btn-info" id="btnPreview">{vtranslate('LBL_PREVIEW', $MODULE)}</button>
            <button class="btn btn-primary" id="btnExportConfirm">{vtranslate('LBL_EXPORT', $MODULE)}</button>
        </div>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function() {
        // Export Action
        jQuery('#btnExportConfirm').on('click', function(e) {
            e.preventDefault();
            var form = jQuery('#wordExportForm');
            var params = form.serializeFormData();
            
            // Validate
            if (!params.template) {
                Vtiger_Helper_Js.showPnotify("Please select a template");
                return;
            }

            // Execute Export
            var url = 'index.php?' + jQuery.param(params);
            window.location.href = url;
            
            app.hideModalWindow();
        });

        // Preview Action
        jQuery('#btnPreview').on('click', function(e) {
            e.preventDefault();
            var form = jQuery('#wordExportForm');
            var params = form.serializeFormData();
            
            // Validate
            if (!params.template) {
                Vtiger_Helper_Js.showPnotify("Please select a template");
                return;
            }

            // Change action to Preview
            params.action = 'Preview';

            // Execute Preview in new tab
            var url = 'index.php?' + jQuery.param(params);
            window.open(url, '_blank', 'width=900,height=700,scrollbars=yes');
        });
    });
</script>
