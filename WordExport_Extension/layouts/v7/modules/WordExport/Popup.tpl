{* WordExport Popup - Export & Preview Modal *}
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
                                <i class="fa fa-cog"></i> Administrar Templates
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
            <button class="btn btn-default" data-dismiss="modal">Cerrar</button>
            <button class="btn btn-info" id="btnPreview"><i class="fa fa-eye"></i> Previsualizar</button>
            <button class="btn btn-primary" id="btnExportConfirm"><i class="fa fa-download"></i> Exportar</button>
        </div>
    </div>
</div>

<script type="text/javascript">
{literal}
jQuery(document).ready(function($) {

    // --- Overlay de preview: se crea en document.body para evitar problemas de stacking context ---
    var overlayId = 'wePreviewOverlay';
    if (!$('#' + overlayId).length) {
        var overlayHtml = '<div id="wePreviewOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:99999;">'
            + '<div style="position:absolute; top:20px; left:50%; transform:translateX(-50%); width:880px; max-width:95%; height:calc(100% - 80px); background:#fff; border-radius:6px; display:flex; flex-direction:column; box-shadow:0 10px 40px rgba(0,0,0,0.5);">'
            + '<div style="display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid #ddd; background:#f5f5f5; border-radius:6px 6px 0 0;">'
            + '<h4 style="margin:0; font-size:16px; color:#333;"><i class="fa fa-file-pdf-o"></i> Previsualización PDF</h4>'
            + '<div>'
            + '<button id="btnPreviewDownload" class="btn btn-primary btn-sm" style="margin-right:8px;"><i class="fa fa-download"></i> Descargar</button>'
            + '<button id="btnPreviewClose" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Cerrar</button>'
            + '</div></div>'
            + '<div style="flex:1; overflow:hidden; border-radius:0 0 6px 6px;">'
            + '<iframe id="wePreviewFrame" src="about:blank" style="width:100%; height:100%; border:none; display:block;"></iframe>'
            + '</div></div></div>';
        $(document.body).append(overlayHtml);
    }

    function getFormParams() {
        var form = $('#wordExportForm');
        return {
            module:        'WordExport',
            action:        'Export',
            record:        form.find('[name=record]').val(),
            source_module: form.find('[name=source_module]').val(),
            template:      form.find('[name=template]').val(),
            format:        'pdf'
        };
    }

    // Descarga via iframe persistente en document.body
    // Descarga sin navegar: iframe oculto en document.body
    function downloadViaIframe(url) {
        var frame = document.createElement('iframe');
        frame.style.cssText = 'display:none;width:0;height:0;position:absolute;left:-9999px';
        frame.src = url;
        document.body.appendChild(frame);
        setTimeout(function() { if (frame.parentNode) frame.parentNode.removeChild(frame); }, 120000);
    }

    // Exportar (descarga directa)
    $('#btnExportConfirm').on('click', function(e) {
        e.preventDefault();
        var params = getFormParams();
        if (!params.template) {
            alert('Por favor selecciona un template.');
            return;
        }
        downloadViaIframe('index.php?' + $.param(params));
        app.hideModalWindow();
    });

    // Previsualizar (PDF inline en overlay)
    $('#btnPreview').on('click', function(e) {
        e.preventDefault();
        var params = getFormParams();
        if (!params.template) {
            alert('Por favor selecciona un template.');
            return;
        }
        var previewParams = $.extend({}, params, {preview: '1'});
        var previewUrl = 'index.php?' + $.param(previewParams);

        $('#btnPreviewDownload').data('downloadParams', params);
        $('#wePreviewFrame').attr('src', previewUrl);
        $('#wePreviewOverlay').fadeIn(200);
    });

    // Descargar desde overlay
    $(document).on('click', '#btnPreviewDownload', function() {
        var params = $(this).data('downloadParams');
        if (params) downloadViaIframe('index.php?' + $.param(params));
    });

    // Cerrar overlay
    $(document).on('click', '#btnPreviewClose', function() {
        $('#wePreviewOverlay').fadeOut(200, function() {
            $('#wePreviewFrame').attr('src', 'about:blank');
        });
    });

    // Cerrar overlay al hacer click fuera del panel
    $(document).on('click', '#wePreviewOverlay', function(e) {
        if ($(e.target).is('#wePreviewOverlay')) {
            $('#btnPreviewClose').trigger('click');
        }
    });
});
{/literal}
</script>
