jQuery(function ($) {
    console.log('Maljani Filter JS Loaded');
    $('#maljani-policy-filter-form').on('submit', function(e){
        e.preventDefault();
        var data = $(this).serialize();
        $('#maljani-policy-results').html('Chargement...');
        $.post(maljaniFilter.ajaxurl, {
            action: 'maljani_filter_policies',
            ...Object.fromEntries(new URLSearchParams(data))
        }, function(response){
            $('#maljani-policy-results').html(response);
        });
    });
});