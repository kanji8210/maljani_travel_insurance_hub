jQuery(document).ready(function($) {
    // Feature image uploader
    $('#upload_policy_feature_img').click(function(e) {
        e.preventDefault();
        
        const frame = wp.media({
            title: 'Select Feature Image',
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $('#policy_feature_img').val(attachment.id);
            $('#policy_feature_img_preview').attr('src', attachment.url).show();
            $('#remove_policy_feature_img').show();
        });
        
        frame.open();
    });
    
    // Remove feature image
    $('#remove_policy_feature_img').click(function() {
        $('#policy_feature_img').val('');
        $('#policy_feature_img_preview').attr('src', '').hide();
        $(this).hide();
    });
    
    // Add day premium row
    $('#add-day-premium-row').click(function() {
        const row = '<tr>' +
            '<td><input type="number" name="day_premium_from[]" min="1" style="width:90px;" /></td>' +
            '<td><input type="number" name="day_premium_to[]" min="1" style="width:90px;" /></td>' +
            '<td><input type="number" name="day_premium_amount[]" min="0" step="0.01" style="width:120px;" /></td>' +
            '<td><button type="button" class="remove-row button">-</button></td>' +
        '</tr>';
        
        $('#day-premium-table tbody').append(row);
    });
    
    // Remove day premium row
    $('#day-premium-table').on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
    });
    
    // Validate day ranges
    $('#day-premium-table').on('change', 'input', function() {
        const row = $(this).closest('tr');
        const from = parseInt(row.find('input[name^="day_premium_from"]').val()) || 0;
        const to = parseInt(row.find('input[name^="day_premium_to"]').val()) || 0;
        
        if (from > to) {
            row.addClass('error');
            row.find('input').css('border-color', 'red');
        } else {
            row.removeClass('error');
            row.find('input').css('border-color', '');
        }
    });
    
    // Add new region via AJAX
    $('#add_policy_region').click(function() {
        const newRegion = $('#new_policy_region').val().trim();
        const nonce = $(this).data('nonce');
        
        if (!newRegion) {
            alert('Please enter a region name');
            return;
        }
        
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'add_policy_region',
                region: newRegion,
                security: policyAdmin.nonce
            },
            beforeSend: function() {
                $('#add_policy_region').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    const newOption = new Option(
                        response.data.name,
                        response.data.term_id,
                        true,
                        true
                    );
                    $('#policy_region').append(newOption).trigger('change');
                    $('#new_policy_region').val('');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Server error, please try again');
            },
            complete: function() {
                $('#add_policy_region').prop('disabled', false);
            }
        });
    });
});