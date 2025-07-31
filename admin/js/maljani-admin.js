/**
 * Maljani Admin JavaScript
 * Scripts pour l'interface d'administration du plugin Maljani
 */

(function($) {
    'use strict';

    /**
     * Code à exécuter quand le DOM est prêt
     */
    $(document).ready(function() {
        console.log('Maljani Admin scripts loaded');

        // Initialiser les fonctionnalités admin si nécessaire
        initAdminFeatures();
    });

    /**
     * Initialise les fonctionnalités d'administration
     */
    function initAdminFeatures() {
        // Confirmation pour les actions de suppression
        $('.delete-action').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
                return false;
            }
        });

        // Gestion des tooltips si présents
        if (typeof $().tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Gestion des alertes dismissible
        $('.notice-dismiss').on('click', function() {
            $(this).parent().fadeOut();
        });
    }

    /**
     * Fonction utilitaire pour afficher des notifications
     */
    window.maljaniShowNotice = function(message, type) {
        type = type || 'info';
        var noticeClass = 'notice notice-' + type;
        var notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss après 5 secondes
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    };

})(jQuery);
