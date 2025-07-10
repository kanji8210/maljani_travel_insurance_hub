jQuery(document).ready(function($) {
    function loadPolicies(paged = 1, append = false) {
        console.log('Chargement des policies pour la page:', paged);
        let data = {
            action: 'maljani_filter_policies',
            region: $('#maljani-policy-filter-form select[name="region"]').val(),
            insurer: $('#maljani-policy-filter-form select[name="insurer"]').val(),
            paged: paged
        };
        $('#maljani-policy-results').addClass('loading');
        $.post(maljaniFilter.ajaxurl, data, function(response) {
            $('#maljani-policy-results').removeClass('loading');
            if (response.success && response.data && response.data.html) {
                if (append) {
                    // Ajoute les nouvelles policies à la suite
                    $('#maljani-policy-results ul.maljani-policy-grid').append(
                        $(response.data.html).find('ul.maljani-policy-grid').html()
                    );
                    // Remplace le bouton "Charger plus"
                    $('#maljani-policy-results .maljani-load-more').remove();
                    $('#maljani-policy-results').append(
                        $(response.data.html).find('.maljani-load-more')
                    );
                } else {
                    $('#maljani-policy-results').html(response.data.html);
                }
            } else {
                $('#maljani-policy-results').html('<p>Erreur lors du chargement des policies.</p>');
            }
        });
    }

    // Filtre
    $(document).on('submit', '#maljani-policy-filter-form', function(e) {
        e.preventDefault();
        loadPolicies(1, false);
    });

    // Charger plus
    $(document).on('click', '.maljani-load-more', function() {
        let next = $(this).data('next');
        loadPolicies(next, true);
    });
});