jQuery(document).ready(function ($) {
    console.log('Maljani Premium Calculator initialized.');
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
        });
        
        frame.open();
    });
    
    // Remove feature image
    $('#remove_policy_feature_img').click(function(e) {
        e.preventDefault();
        $('#policy_feature_img').val('');
        $('#policy_feature_img_preview').attr('src', '').hide();
    });
    
    // Add day premium row
    $('#add-day-premium-row').click(function(e) {
        e.preventDefault();
        const row = '<tr>' +
            '<td><input type="number" name="day_premium_from[]" min="1" placeholder="1" /></td>' +
            '<td><input type="number" name="day_premium_to[]" min="1" placeholder="365" /></td>' +
            '<td><input type="number" name="day_premium_amount[]" min="0" step="0.01" placeholder="0.00" /></td>' +
            '<td><button type="button" class="remove-row policy-btn-secondary">Remove</button></td>' +
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
    
    // ── Countries covered tag manager ──────────────────────────────────────
    var SCHENGEN = [
        'Austria','Belgium','Croatia','Czech Republic','Denmark','Estonia',
        'Finland','France','Germany','Greece','Hungary','Iceland','Italy',
        'Latvia','Liechtenstein','Lithuania','Luxembourg','Malta','Netherlands',
        'Norway','Poland','Portugal','Romania','Slovakia','Slovenia','Spain','Sweden'
    ];
    var AFRICA = [
        'Algeria','Angola','Benin','Botswana','Burkina Faso','Burundi','Cabo Verde',
        'Cameroon','Central African Republic','Chad','Comoros','Congo','DR Congo',
        "Côte d'Ivoire",'Djibouti','Egypt','Equatorial Guinea','Eritrea','Eswatini',
        'Ethiopia','Gabon','Gambia','Ghana','Guinea','Guinea-Bissau','Kenya',
        'Lesotho','Liberia','Libya','Madagascar','Malawi','Mali','Mauritania',
        'Mauritius','Morocco','Mozambique','Namibia','Niger','Nigeria','Rwanda',
        'São Tomé and Príncipe','Senegal','Seychelles','Sierra Leone','Somalia',
        'South Africa','South Sudan','Sudan','Tanzania','Togo','Tunisia','Uganda',
        'Zambia','Zimbabwe'
    ];
    var WORLDWIDE_NOTE = ['Worldwide (All Countries)'];

    var ALL_COUNTRIES = [
        'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda',
        'Argentina','Armenia','Australia','Austria','Azerbaijan','Bahamas','Bahrain',
        'Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan',
        'Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria',
        'Burkina Faso','Burundi','Cabo Verde','Cambodia','Cameroon','Canada',
        'Central African Republic','Chad','Chile','China','Colombia','Comoros',
        'Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark',
        'Djibouti','Dominica','Dominican Republic','DR Congo','Ecuador','Egypt',
        'El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia',
        'Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana',
        'Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti',
        'Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland',
        'Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati',
        'Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya',
        'Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia',
        'Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius',
        'Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco',
        'Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','New Zealand',
        'Nicaragua','Niger','Nigeria','North Korea','North Macedonia','Norway','Oman',
        'Pakistan','Palau','Panama','Papua New Guinea','Paraguay','Peru','Philippines',
        'Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saint Kitts and Nevis',
        'Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino',
        'São Tomé and Príncipe','Saudi Arabia','Senegal','Serbia','Seychelles',
        'Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia',
        'South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan',
        'Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania',
        'Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia',
        'Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates',
        'United Kingdom','United States','Uruguay','Uzbekistan','Vanuatu',
        'Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'
    ];

    // Populate datalist
    var dl = document.getElementById('country-datalist');
    if (dl) {
        ALL_COUNTRIES.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c;
            dl.appendChild(opt);
        });
    }

    // Tag state
    var selectedCountries = [];
    try {
        var hv = $('#policy-countries-hidden').val();
        selectedCountries = hv ? JSON.parse(hv) : [];
    } catch(e) { selectedCountries = []; }

    function renderTags() {
        var $tags = $('#country-tags');
        $tags.empty();
        selectedCountries.forEach(function(c) {
            var $tag = $('<span>').css({
                display:'inline-flex', alignItems:'center', gap:'4px',
                padding:'4px 10px', background:'#ede9fe', color:'#4f46e5',
                borderRadius:'100px', fontSize:'12px', fontWeight:'600'
            }).text(c);
            var $x = $('<button>').attr('type','button')
                .css({background:'none',border:'none',cursor:'pointer',color:'#7c3aed',
                      fontWeight:'700',padding:'0 0 0 4px',fontSize:'13px',lineHeight:1})
                .text('×')
                .attr('aria-label','Remove ' + c)
                .on('click', function() {
                    selectedCountries = selectedCountries.filter(function(v){ return v !== c; });
                    renderTags();
                });
            $tag.append($x);
            $tags.append($tag);
        });
        $('#country-count').text('(' + selectedCountries.length + ')');
        $('#policy-countries-hidden').val(JSON.stringify(selectedCountries));
    }

    function addCountries(list) {
        list.forEach(function(c) {
            if (selectedCountries.indexOf(c) === -1) selectedCountries.push(c);
        });
        renderTags();
    }

    renderTags(); // render saved values on load

    $('#country-add-btn').on('click', function() {
        var val = $('#country-add-input').val().trim();
        if (!val) return;
        // Support comma-separated
        val.split(',').map(function(v){ return v.trim(); }).filter(Boolean).forEach(function(v){
            if (selectedCountries.indexOf(v) === -1) selectedCountries.push(v);
        });
        $('#country-add-input').val('');
        renderTags();
    });
    $('#country-add-input').on('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#country-add-btn').trigger('click'); }
    });

    $('#preset-schengen').on('click', function() { addCountries(SCHENGEN); });
    $('#preset-worldwide').on('click', function() { selectedCountries = WORLDWIDE_NOTE.slice(); renderTags(); });
    $('#preset-africa').on('click',    function() { addCountries(AFRICA); });
    $('#preset-clear').on('click',     function() { selectedCountries = []; renderTags(); });
    // ── /Countries ───────────────────────────────────────────────────────────

    // Add new region via AJAX
    $('#add_policy_region').click(function() {
        const newRegion = $('#new_policy_region').val().trim();
        
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
    
    // Afficher la ligne d'édition au clic sur Edit
    $('.maljani-sales-table').on('click', '.edit-sale-btn', function() {
        var saleId = $(this).data('sale');
        $('#sale-row-' + saleId).hide();
        $('#sale-edit-row-' + saleId).show();
    });

    // Annuler l'édition
    $('.maljani-sales-table').on('click', '.cancel-edit-sale-btn', function() {
        var saleId = $(this).data('sale');
        $('#sale-edit-row-' + saleId).hide();
        $('#sale-row-' + saleId).show();
    });
});