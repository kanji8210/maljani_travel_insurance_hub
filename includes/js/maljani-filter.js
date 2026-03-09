jQuery(document).ready(function($) {
    let currentStep = 1;
    let currentRegion = '';
    
    // NAVIGATION: Next Step
    $(document).on('click', '.mj-btn-next', function() {
        const nextStepId = $(this).data('next');
        const nextStepNum = (nextStepId === 'step-dates') ? 2 : 3;
        
        if (nextStepId === 'step-results') {
            // Validate dates before proceeding
            const dep = $('input[name="departure"]').val();
            const ret = $('input[name="return"]').val();
            if (!dep || !ret) {
                alert('Please select both departure and return dates.');
                return;
            }
            const d1 = new Date(dep);
            const d2 = new Date(ret);
            if (d1 >= d2) {
                alert('Return date must be after departure date.');
                return;
            }
            
            // Trigger results loading
            goToStep(3);
            loadPolicies(dep, ret, currentRegion);
        } else {
            goToStep(nextStepNum);
        }
    });

    // NAVIGATION: Back Step
    $(document).on('click', '.mj-btn-back', function() {
        const prevStepId = $(this).data('back');
        const prevStepNum = (prevStepId === 'step-destination') ? 1 : 2;
        goToStep(prevStepNum);
    });

    function goToStep(stepNum) {
        $('.wizard-step').removeClass('active');
        $(`.wizard-step:nth-child(${stepNum})`).addClass('active');
        
        // Update Progress Bar
        $('.progress-step').removeClass('active completed');
        $('.progress-step').each(function() {
            const s = $(this).data('step');
            if (s < stepNum) $(this).addClass('completed');
            if (s === stepNum) $(this).addClass('active');
        });
        
        const progress = ((stepNum - 1) / 2) * 100;
        $('.progress-fill').css('width', `${progress}%`);
        
        currentStep = stepNum;
        
        // Hide results if we go back
        if (stepNum < 3) {
            $('#maljani-policy-results').fadeOut();
        }
    }

    // REGION SELECTION
    $(document).on('click', '.region-card', function() {
        $('.region-card').removeClass('active');
        $(this).addClass('active');
        currentRegion = $(this).data('region') || '';
        $('#maljani-region-input').val(currentRegion);
        
        // Optional: Auto-advance to dates after selection
        // setTimeout(() => goToStep(2), 300);
    });

    // DATE CALCULATION
    $(document).on('change', 'input[name="departure"], input[name="return"]', function() {
        const dep = $('input[name="departure"]').val();
        const ret = $('input[name="return"]').val();
        
        if (dep && ret) {
            const d1 = new Date(dep);
            const d2 = new Date(ret);
            if (d2 > d1) {
                const diffTime = Math.abs(d2 - d1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                $('#trip-duration-display').text(diffDays);
                $('.trip-summary-box').fadeIn();
            } else {
                $('.trip-summary-box').fadeOut();
            }
        }
    });

    function loadPolicies(departure, return_date, region) {
        let columns = $('.maljani-wizard-wrapper').data('columns') || 4;
        
        let data = {
            action: 'maljani_filter_policies',
            departure: departure,
            return: return_date,
            region: region,
            columns: columns
        };
        
        if (typeof maljaniFilter !== 'undefined' && maljaniFilter.security) {
            data.security = maljaniFilter.security;
        }

        const ajaxUrl = (typeof maljaniFilter !== 'undefined' && maljaniFilter.ajax_url) ? maljaniFilter.ajax_url : maljaniFilter.ajaxurl;

        $.post(ajaxUrl, data, function(response) {
            if (response.success && response.data && response.data.html) {
                // Smooth transition to results
                setTimeout(() => {
                    $('.wizard-form').fadeOut(300, function() {
                        $('#maljani-policy-results').fadeIn(400);
                        $('.maljani-results-grid-anchor').html(response.data.html);
                    });
                }, 1500); // Artificial delay for premium loader feel
            } else {
                goToStep(2);
                alert('No policies found for your criteria.');
            }
        }).fail(function() {
            goToStep(2);
            alert('Connection error. Please try again.');
        });
    }

    // Modal/Benefit popup logic (Keeping existing functionality)
    function addLightboxStyles() {
        if (!document.getElementById('maljani-lightbox-style')) {
            const style = document.createElement('style');
            style.id = 'maljani-lightbox-style';
            style.innerHTML = `
            .maljani-lightbox-bg {
                position: fixed; left: 0; top: 0; width: 100vw; height: 100vh;
                background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px);
                z-index: 9998; display: flex; align-items: center; justify-content: center;
            }
            .maljani-lightbox-content {
                background: #fff; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
                max-width: 90vw; padding: 40px; position: relative;
                animation: maljaniLightboxIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .maljani-lightbox-close {
                position: absolute; top: 20px; right: 20px; background: #f1f5f9; border: none; 
                width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; 
                justify-content: center; color: #64748b; cursor: pointer; transition: all 0.2s;
            }
            .maljani-lightbox-close:hover { background: #e2e8f0; color: #ef4444; }
            @keyframes maljaniLightboxIn { from { opacity: 0; transform: scale(0.9) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
            `;
            document.head.appendChild(style);
        }
    }

    $(document).on('click', '.see-benefits', function(e){
        e.preventDefault();
        addLightboxStyles();
        const policyId = $(this).data('policy-id');
        const $benefits = $('#policy-benefits-' + policyId);
        
        if ($('.maljani-lightbox-bg').length) $('.maljani-lightbox-bg').remove();
        const $bg = $('<div class="maljani-lightbox-bg"></div>').appendTo('body');
        
        if ($benefits.length) {
            $bg.html('<div class="maljani-lightbox-content">'+$benefits.html()+'<button class="maljani-lightbox-close" title="Close">&times;</button></div>');
        }
    });

    $(document).on('click', '.maljani-lightbox-close, .maljani-lightbox-bg', function(e){
        if ($(e.target).is('.maljani-lightbox-bg, .maljani-lightbox-close')) {
            $('.maljani-lightbox-bg').fadeOut(200, function(){ $(this).remove(); });
        }
    });
});