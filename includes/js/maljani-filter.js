jQuery(document).ready(function($) {
    let currentRegion = '';
    
    // Initialize filter on page load
    console.log('Maljani Filter initialized');
    
    function loadPolicies(departure = '', return_date = '', region = '') {
        console.log('Loading policies with dates and region:', departure, return_date, region);
        
        let data = {
            action: 'maljani_filter_policies',
            departure: departure,
            return: return_date,
            region: region
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
        $('.region-filter-btn').removeClass('active').css({
            'background': 'white',
            'color': '#0073aa'
        });
        
        $(this).addClass('active').css({
            'background': '#0073aa',
            'color': 'white'
        });
        
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
});