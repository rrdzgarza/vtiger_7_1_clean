{* License Text *}
<div class="row-fluid">
    <div class="span12">
        <h3 class="module-title">{vtranslate('LBL_MANAGE_TEMPLATES', $MODULE)}</h3>
        <hr>
        
        <div class="row-fluid">
            <!-- Upload Form -->
            <form action="index.php" method="POST" enctype="multipart/form-data" class="form-inline">
                <input type="hidden" name="module" value="WordExport">
                <input type="hidden" name="action" value="FileAction">
                <input type="hidden" name="mode" value="upload">
                
                <div class="form-group">
                    <label>Upload New Template:</label>
                    <input type="file" name="template_file" accept=".docx,.html" required>
                    
                    <select name="target_module" class="form-control" required>
                        <option value="">-- Select Module --</option>
                        {foreach from=$SUPPORTED_MODULES item=MOD_NAME}
                            <option value="{$MOD_NAME}">{$MOD_NAME}</option>
                        {/foreach}
                    </select>
                    
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </form>
        </div>
        <br>
        
        <!-- Templates List -->
        <table class="table table-bordered table-condensed table-striped">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Template Filename</th>
                    <th>Size (KB)</th>
                    <th>Created Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$TEMPLATES item=TEMPLATE}
                <tr>
                    <td><span class="label label-info">{$TEMPLATE.module_name}</span></td>
                    <td>{$TEMPLATE.filename}</td>
                    <td>{$TEMPLATE.size}</td>
                    <td>{$TEMPLATE.createdtime}</td>
                    <td>
                        <a href="index.php?module=WordExport&action=FileAction&mode=delete&id={$TEMPLATE.id}" 
                           onclick="return confirm('Are you sure you want to delete this template?');" 
                           class="text-danger">Delete</a>
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
