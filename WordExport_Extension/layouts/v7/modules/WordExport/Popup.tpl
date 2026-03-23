{* WordExport Popup - Export & Preview Modal *}
<div class="modal-dialog model-container">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h3 class="modal-title">Exportar a PDF / Word</h3>
        </div>
        <div class="modal-body">
            <form id="wordExportForm" class="form-horizontal">
                <input type="hidden" name="module" value="WordExport" />
                <input type="hidden" name="action" value="Export" />
                <input type="hidden" name="record" value="{$RECORD}" />
                <input type="hidden" name="source_module" value="{$SOURCE_MODULE}" />

                {* ===== SECCIÓN PDF (templates HTML) ===== *}
                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 12px; background: #f9f9f9;">
                    <h5 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-file-pdf-o"></i> Exportar a PDF</h5>

                    {if count($HTML_TEMPLATES) > 0}
                    <div class="form-group" style="margin-bottom: 8px;">
                        <label class="control-label col-sm-3">Template</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control template-filter" data-target="template_html" placeholder="Buscar template..." style="margin-bottom: 4px;" />
                            <select name="template_html" class="form-control" style="width: 100%;" size="4">
                                {foreach from=$HTML_TEMPLATES item=TEMPLATE}
                                    <option value="{$TEMPLATE}">{$TEMPLATE}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 8px;">
                        <label class="control-label col-sm-3">Nombre</label>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <input type="text" name="custom_filename" class="form-control" value="{$DEFAULT_FILENAME}" />
                                <span class="input-group-addon">.pdf</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 5px;">
                        <div class="col-sm-offset-3 col-sm-8">
                            <div class="checkbox" style="margin: 0;">
                                <label>
                                    <input type="checkbox" name="save_to_docs" value="1"> Guardar en Documentos
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <div class="col-sm-offset-3 col-sm-8">
                            <button class="btn btn-info btn-sm" id="btnPreview" style="margin-right: 5px;">
                                <i class="fa fa-eye"></i> Previsualizar
                            </button>
                            <button class="btn btn-primary btn-sm" id="btnExportPdf">
                                <i class="fa fa-download"></i> Exportar PDF
                            </button>
                        </div>
                    </div>
                    {else}
                    <p style="color: #999; margin: 0;">No hay templates PDF para este m&oacute;dulo.</p>
                    {/if}
                </div>

                {* ===== SECCIÓN WORD (templates DOCX) ===== *}
                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 12px; background: #f9f9f9;">
                    <h5 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-file-word-o"></i> Exportar a Word</h5>

                    {if count($DOCX_TEMPLATES) > 0}
                    <div class="form-group" style="margin-bottom: 8px;">
                        <label class="control-label col-sm-3">Template</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control template-filter" data-target="template_docx" placeholder="Buscar template..." style="margin-bottom: 4px;" />
                            <select name="template_docx" class="form-control" style="width: 100%;" size="6">
                                {foreach from=$DOCX_TEMPLATES item=TEMPLATE}
                                    <option value="{$TEMPLATE}">{$TEMPLATE}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 8px;">
                        <label class="control-label col-sm-3">Nombre</label>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <input type="text" name="custom_filename_docx" class="form-control" value="{$DEFAULT_FILENAME}" />
                                <span class="input-group-addon">.docx</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <div class="col-sm-offset-3 col-sm-8">
                            <button class="btn btn-success btn-sm" id="btnExportWord">
                                <i class="fa fa-download"></i> Exportar Word
                            </button>
                        </div>
                    </div>
                    {else}
                    <p style="color: #999; margin: 0;">No hay templates Word para este m&oacute;dulo.</p>
                    {/if}
                </div>

            </form>

            <div style="margin-top: 8px; text-align: right;">
                <a href="index.php?module=WordExport&view=ListTemplates" target="_blank" style="font-size: 11px;">
                    <i class="fa fa-cog"></i> Administrar Templates
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" data-dismiss="modal">Cerrar</button>
        </div>
    </div>
</div>

<script type="text/javascript">
{literal}
jQuery(document).ready(function($) {

    // --- Filtro de templates: buscar mientras escribes ---
    $('.template-filter').on('keyup', function() {
        var query = $(this).val().toLowerCase();
        var selectName = $(this).data('target');
        var $select = $('[name="' + selectName + '"]');
        var firstVisible = null;

        $select.find('option').each(function() {
            var text = $(this).text().toLowerCase();
            if (query === '' || text.indexOf(query) !== -1) {
                $(this).show();
                if (!firstVisible) firstVisible = $(this);
            } else {
                $(this).hide();
            }
        });

        // Auto-select first visible match
        if (firstVisible) {
            $select.val(firstVisible.val());
        }
    });

    // Sort options alphabetically on load
    $('select[name="template_html"], select[name="template_docx"]').each(function() {
        var $select = $(this);
        var options = $select.find('option').toArray().sort(function(a, b) {
            return a.text.localeCompare(b.text);
        });
        $select.empty().append(options);
        if (options.length > 0) {
            $select.val(options[0].value);
        }
    });

    // --- Overlay de preview en document.body ---
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

    function getBaseParams() {
        var form = $('#wordExportForm');
        return {
            module:        'WordExport',
            action:        'Export',
            record:        form.find('[name=record]').val(),
            source_module: form.find('[name=source_module]').val()
        };
    }

    function downloadViaIframe(url) {
        var frame = document.createElement('iframe');
        frame.style.cssText = 'display:none;width:0;height:0;position:absolute;left:-9999px';
        frame.src = url;
        document.body.appendChild(frame);
        setTimeout(function() { if (frame.parentNode) frame.parentNode.removeChild(frame); }, 120000);
    }

    // ===== PDF: Exportar =====
    $('#btnExportPdf').on('click', function(e) {
        e.preventDefault();
        var form = $('#wordExportForm');
        var template = form.find('[name=template_html]').val();
        if (!template) { alert('Selecciona un template PDF.'); return; }

        var params = getBaseParams();
        params.template = template;
        params.format = 'pdf';
        var customName = form.find('[name=custom_filename]').val();
        if (customName) params.custom_filename = customName;
        if (form.find('[name=save_to_docs]').is(':checked')) params.save_to_docs = '1';

        downloadViaIframe('index.php?' + $.param(params));
        app.hideModalWindow();
    });

    // ===== PDF: Previsualizar =====
    $('#btnPreview').on('click', function(e) {
        e.preventDefault();
        var form = $('#wordExportForm');
        var template = form.find('[name=template_html]').val();
        if (!template) { alert('Selecciona un template PDF.'); return; }

        var params = getBaseParams();
        params.template = template;
        params.format = 'pdf';
        params.preview = '1';

        var downloadParams = $.extend({}, params);
        delete downloadParams.preview;
        var customName = form.find('[name=custom_filename]').val();
        if (customName) downloadParams.custom_filename = customName;

        $('#btnPreviewDownload').data('downloadParams', downloadParams);
        $('#wePreviewFrame').attr('src', 'index.php?' + $.param(params));
        $('#wePreviewOverlay').fadeIn(200);
    });

    // ===== WORD: Exportar =====
    $('#btnExportWord').on('click', function(e) {
        e.preventDefault();
        var form = $('#wordExportForm');
        var template = form.find('[name=template_docx]').val();
        if (!template) { alert('Selecciona un template Word.'); return; }

        var params = getBaseParams();
        params.template = template;
        params.format = 'docx';
        var customName = form.find('[name=custom_filename_docx]').val();
        if (customName) params.custom_filename = customName;

        downloadViaIframe('index.php?' + $.param(params));
        app.hideModalWindow();
    });

    // ===== Preview overlay controls =====
    $(document).on('click', '#btnPreviewDownload', function() {
        var params = $(this).data('downloadParams');
        if (params) downloadViaIframe('index.php?' + $.param(params));
    });
    $(document).on('click', '#btnPreviewClose', function() {
        $('#wePreviewOverlay').fadeOut(200, function() {
            $('#wePreviewFrame').attr('src', 'about:blank');
        });
    });
    $(document).on('click', '#wePreviewOverlay', function(e) {
        if ($(e.target).is('#wePreviewOverlay')) {
            $('#btnPreviewClose').trigger('click');
        }
    });
});
{/literal}
</script>
