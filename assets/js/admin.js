jQuery(function($) {
    let methodIndex = $('.method-row').length;
    
    // Add new method
    $('#add-new-method').on('click', function() {
        const template = $('#method-row-template').html();
        const compiled = template.replace(/{{index}}/g, methodIndex++);
        $('#shipping-methods-repeater').append(compiled);
        initCodeEditors(methodIndex-1);
    });
    
    // Remove method
    $(document).on('click', '.remove-method', function() {
        $(this).closest('.method-row').remove();
    });
    
    // Change method type
    $(document).on('change', '.method-select', function() {
        const $row = $(this).closest('.method-row');
        const method = $(this).val();
        const index = $row.data('index');
        let findClass = `.settings-section.section-${index}`;
        
        let settingsHtml = '';
        if (method === 'custom_formula') {
            settingsHtml = `
                <label>Formula (JavaScript):</label>
                <textarea name="methods[${index}][method_setting][formula]" class="formula-editor editor-${index}"></textarea>
            `;
        } else if (method === 'mrl') {
            settingsHtml = `
                <label>Account Name:</label>
                <input type="text" name="methods[${index}][method_setting][account]">
                <label>Password:</label>
                <input type="password" name="methods[${index}][method_setting][password]">
            `;
        }
        $row.find(findClass).html(settingsHtml);
        initCodeEditors(index);
    });
    
    // Save form
    $('#glint-shipping-form').on('submit', function(e) {
        e.preventDefault();
        
        // Save all CodeMirror editors to their textareas
        $('.formula-editor').each(function() {
            const cm = $(this).data('codemirror');
            if (cm) {
                cm.save();
            }
        });
        
        // Serialize form data
        const formData = {};
        $(this).find('input, select, textarea').each(function() {
            const name = $(this).attr('name');
            if (!name) return;
            
            // Convert name to object notation
            const keys = name.replace(/\]/g, '').split('[');
            let current = formData;
            
            keys.forEach((key, i) => {
                if (i === keys.length - 1) {
                    current[key] = $(this).val();
                } else {
                    if (!current[key]) current[key] = {};
                    current = current[key];
                }
            });
        });
        
        // Prepare AJAX data
        const data = {
            action: 'glint_save_shipping_methods',
            security: glintShippingAdmin.nonce,
            methods: formData.methods || {}
        };
        
        // Send AJAX request
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                alert('Settings saved successfully!');
            } else {
                alert('Error: ' + response.data);
            }
        }).fail(function() {
            alert('Server error occurred. Please try again.');
        });
    });
    
    // Initialize code editors and store CodeMirror instances
    function initCodeEditors(rowIndex) {
        let findClass = `.formula-editor.editor-${rowIndex}`;
        $(findClass).each(function() {
            if (!this.hasAttribute('data-cm')) {
                const editor = wp.codeEditor.initialize(this, {
                    mode: 'javascript',
                    lineNumbers: true,
                    indentUnit: 4,
                    tabSize: 4
                });
                // Store CodeMirror instance on the textarea
                $(this).data('codemirror', editor.codemirror);
            }
        });
    }

    initCodeEditors(0);
});