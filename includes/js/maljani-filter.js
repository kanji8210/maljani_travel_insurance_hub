jQuery(document).ready(function($) {
    let currentRegion = '';
    
    // Initialize filter on page load
    console.log('Maljani Filter initialized');
    
    function loadPolicies(departure = '', return_date = '', region = '') {
        console.log('Loading policies with dates and region:', departure, return_date, region);
        
        let columns = $('.maljani-filter-wrapper').data('columns') || 4;
        
        let data = {
            action: 'maljani_filter_policies',
            departure: departure,
            return: return_date,
            region: region,
            columns: columns
        };
        
        $('#maljani-policy-results').addClass('loading').html('<p>Calculating premiums...</p>');
        
        $.post(maljaniFilter.ajaxurl, data, function(response) {
            $('#maljani-policy-results').removeClass('loading');
            if (response.success && response.data && response.data.html) {
                $('#maljani-policy-results').html(response.data.html);
            } else {
                $('#maljani-policy-results').html('<p>Error loading policies.</p>');
            }
        }).fail(function() {
            $('#maljani-policy-results').removeClass('loading');
            $('#maljani-policy-results').html('<p>Error connecting to server.</p>');
        });
    }

    // Auto-calculate when both dates are filled
    function checkAndCalculate() {
        let departure = $('input[name="departure"]').val();
        let return_date = $('input[name="return"]').val();
        
        if (departure && return_date) {
            // Validate dates
            let depDate = new Date(departure);
            let retDate = new Date(return_date);
            
            if (depDate >= retDate) {
                $('input[name="departure"], input[name="return"]').css('border-color', 'red');
                $('#maljani-policy-results').html('<p style="color:red;">Return date must be after departure date.</p>');
                return;
            } else {
                $('input[name="departure"], input[name="return"]').css('border-color', '#ddd');
            }
            
            // Auto-calculate premiums
            loadPolicies(departure, return_date, currentRegion);
        }
    }

    // Handle date changes - auto-calculate
    $(document).on('change', 'input[name="departure"], input[name="return"]', function() {
        checkAndCalculate();
    });

    // Handle region filter buttons
    $(document).on('click', '.region-filter-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Region button clicked:', $(this).data('region'));
        
        // Update button styles
        $('.region-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Update current region
        currentRegion = $(this).data('region') || '';
        
        console.log('Current region set to:', currentRegion);
        
        // If dates are filled, reload with new region filter
        let departure = $('input[name="departure"]').val();
        let return_date = $('input[name="return"]').val();
        
        if (departure && return_date) {
            console.log('Reloading with dates and region');
            loadPolicies(departure, return_date, currentRegion);
        } else {
            console.log('No dates set, just updated region filter');
        }
    });
    
    // Remove form submission handler since we auto-calculate
    $(document).on('submit', '#maljani-policy-filter-form', function(e) {
        e.preventDefault();
        // Do nothing - auto-calculation handles everything
    });
    
    // Désactive le comportement sur toute la vignette, ne l'applique que sur le nom
    $(document).on('mouseenter', '.insurer-name', function(e){
        if (!$(this).find('.insurer-hover-hint').length) {
            $('body').append('<div class="insurer-hover-hint">Click to see profile</div>');
        }
    }).on('mousemove', '.insurer-name', function(e){
        $('.insurer-hover-hint').css({
            left: e.clientX + 16,
            top: e.clientY + 16
        });
    }).on('mouseleave', '.insurer-name', function(){
        $('.insurer-hover-hint').remove();
    });

    // Lightbox CSS (à ajouter dynamiquement si besoin)
    function addLightboxStyles() {
        if (!document.getElementById('maljani-lightbox-style')) {
            const style = document.createElement('style');
            style.id = 'maljani-lightbox-style';
            style.innerHTML = `
            .maljani-lightbox-bg {
                position: fixed; left: 0; top: 0; width: 100vw; height: 100vh;
                background: rgba(24,49,83,0.45); z-index: 9998; display: flex; align-items: center; justify-content: center;
            }
            .maljani-lightbox-content {
                background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(24,49,83,0.18);
                min-width: 280px; max-width: 95vw; padding: 32px 28px; position: relative;
                animation: maljaniLightboxIn 0.18s;
            }
            .maljani-lightbox-close {
                position: absolute; top: 12px; right: 18px; background: none; border: none; font-size: 1.8em; color: #183153; cursor: pointer;}
            @keyframes maljaniLightboxIn { from { opacity: 0; transform: scale(0.95);} to { opacity: 1; transform: scale(1);} }
            `;
            document.head.appendChild(style);
        }
    }

    $(document).on('mouseenter', '.insurer-name', function(){
        $(this).css('cursor', 'pointer');
    });

    $(document).on('click', '.insurer-name', function(e){
        e.stopPropagation();
        addLightboxStyles();
        var insurerId = $(this).data('insurer-id');
        // Cherche le HTML du profil déjà présent dans la page
        var $profile = $('#insurer-profile-' + insurerId);
        if ($('.maljani-lightbox-bg').length) $('.maljani-lightbox-bg').remove();
        var $bg = $('<div class="maljani-lightbox-bg"></div>').appendTo('body');
        if ($profile.length) {
            $bg.html('<div class="maljani-lightbox-content">'+$profile.html()+'<button class="maljani-lightbox-close" title="Close">&times;</button></div>');
        } else {
            $bg.html('<div class="maljani-lightbox-content"><div style="padding:32px;text-align:center;">Profile not found</div><button class="maljani-lightbox-close" title="Close">&times;</button></div>');
        }
    });
    // Fermer la lightbox
    $(document).on('click', '.maljani-lightbox-close, .maljani-lightbox-bg', function(e){
        if ($(e.target).is('.maljani-lightbox-bg, .maljani-lightbox-close')) {
            $('.maljani-lightbox-bg').fadeOut(120, function(){ $(this).remove(); });
        }
    });

    $(document).on('click', '.see-benefits', function(e){
        e.preventDefault();
        addLightboxStyles();
        var policyId = $(this).data('policy-id');
        var $benefits = $('#policy-benefits-' + policyId);
        if ($('.maljani-lightbox-bg').length) $('.maljani-lightbox-bg').remove();
        var $bg = $('<div class="maljani-lightbox-bg"></div>').appendTo('body');
        if ($benefits.length) {
            $bg.html('<div class="maljani-lightbox-content">'+$benefits.html()+'<button class="maljani-lightbox-close" title="Close">&times;</button></div>');
        } else {
            $bg.html('<div class="maljani-lightbox-content"><div style="padding:32px;text-align:center;">Benefits not found</div><button class="maljani-lightbox-close" title="Close">&times;</button></div>');
        }
    });
});