jQuery(document).ready(function($) {
    // Lucide Icon Initialization
    function initIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    // Initialize icons on page load
    initIcons();

    // Modal/Benefit popup logic (Keeping existing functionality but enhancing UI)
    function addLightboxStyles() {
        if (!document.getElementById('maljani-lightbox-style')) {
            const style = document.createElement('style');
            style.id = 'maljani-lightbox-style';
            style.innerHTML = `
            .maljani-lightbox-bg {
                position: fixed; left: 0; top: 0; width: 100vw; height: 100vh;
                background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px);
                z-index: 9998; display: flex; align-items: center; justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            .maljani-lightbox-content {
                background: rgba(255, 255, 255, 0.95); border-radius: 40px; 
                box-shadow: 0 40px 100px -20px rgba(0,0,0,0.2);
                max-width: 500px; padding: 48px; position: relative;
                border: 1px solid rgba(255,255,255,0.8);
                animation: maljaniLightboxIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .maljani-lightbox-close {
                position: absolute; top: 24px; right: 24px; background: #f1f5f9; border: none; 
                width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; 
                justify-content: center; color: #64748b; cursor: pointer; transition: all 0.2s;
            }
            .maljani-lightbox-close:hover { background: #fee2e2; color: #ef4444; transform: rotate(90deg); }
            @keyframes maljaniLightboxIn { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
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
            $bg.html('<div class="maljani-lightbox-content">'+$benefits.html()+'<button class="maljani-lightbox-close" title="Close"><i data-lucide="x"></i></button></div>');
            // Re-init icons for the X button inside the lightbox
            initIcons();
        }
    });

    $(document).on('click', '.maljani-lightbox-close, .maljani-lightbox-bg', function(e){
        if ($(e.target).is('.maljani-lightbox-bg, .maljani-lightbox-close, .maljani-lightbox-close *')) {
            $('.maljani-lightbox-bg').fadeOut(200, function(){ $(this).remove(); });
        }
    });

    // Handle Alpine results loading triggers
    $(document).on('maljani-results-loaded', function() {
        initIcons();
    });
});