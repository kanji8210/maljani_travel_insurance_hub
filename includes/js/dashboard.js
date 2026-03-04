// JavaScript pour le tableau de bord utilisateur Maljani
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Gestion des onglets
        $('.tab-button').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Désactiver tous les onglets
            $('.tab-button').removeClass('active');
            $('.tab-content').removeClass('active');
            
            // Activer l'onglet sélectionné
            $(this).addClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });
        
        // Filtrage en temps réel du tableau des polices
        $('#policy-search, #status-filter').on('input change', function() {
            filterPoliciesTable();
        });
        
        function filterPoliciesTable() {
            const searchTerm = $('#policy-search').val().toLowerCase();
            const statusFilter = $('#status-filter').val();
            
            $('#policies-tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                const status = $row.data('status');
                
                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                $row.toggle(matchesSearch && matchesStatus);
            });
            
            // Afficher un message si aucun résultat
            const visibleRows = $('#policies-tbody tr:visible').length;
            if (visibleRows === 0) {
                if (!$('#no-results-message').length) {
                    $('#policies-tbody').append(
                        '<tr id="no-results-message"><td colspan="8" style="text-align:center;padding:20px;color:#666;">No policies found matching your criteria.</td></tr>'
                    );
                }
            } else {
                $('#no-results-message').remove();
            }
        }
        
        // Gestion des détails de police (modal)
        $('.view-details').on('click', function() {
            const policyId = $(this).data('policy-id');
            showPolicyDetails(policyId);
        });
        
        function showPolicyDetails(policyId) {
            // Créer une modal pour afficher les détails de la police
            const modalHtml = `
                <div id="policy-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        max-width: 600px;
                        width: 90%;
                        max-height: 80vh;
                        overflow-y: auto;
                        position: relative;
                    ">
                        <button id="close-modal" style="
                            position: absolute;
                            top: 15px;
                            right: 15px;
                            background: none;
                            border: none;
                            font-size: 24px;
                            cursor: pointer;
                            color: #666;
                        ">&times;</button>
                        <h3>Policy Details</h3>
                        <div id="policy-details-content">Loading...</div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Charger les détails via AJAX (prefer `ajax_url`/`security`, fallback to legacy keys)
            var _ajaxUrl = (typeof maljaniDashboard !== 'undefined' && (maljaniDashboard.ajax_url || maljaniDashboard.ajaxurl)) ? (maljaniDashboard.ajax_url || maljaniDashboard.ajaxurl) : '/wp-admin/admin-ajax.php';
            var _security = (typeof maljaniDashboard !== 'undefined' && (maljaniDashboard.security || maljaniDashboard.nonce)) ? (maljaniDashboard.security || maljaniDashboard.nonce) : '';

            $.ajax({
                url: _ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_policy_details',
                    policy_id: policyId,
                    nonce: _security
                },
                success: function(response) {
                    if (response.success) {
                        $('#policy-details-content').html(response.data);
                    } else {
                        $('#policy-details-content').html('<p>Error loading policy details.</p>');
                    }
                },
                error: function() {
                    $('#policy-details-content').html('<p>Error loading policy details.</p>');
                }
            });
            
            // Fermer la modal
            $('#close-modal, #policy-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#policy-modal').remove();
                }
            });
        }
        
        // Validation du formulaire de profil
        $('.profile-form').on('submit', function(e) {
            const email = $('#email').val();
            const fullName = $('#full_name').val();
            
            if (!email || !fullName) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Validation email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Confirmation
            if (!confirm(maljaniDashboard.strings.confirm_update)) {
                e.preventDefault();
                return false;
            }
            
            // Ajouter un loading state
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).addClass('loading');
        });
        
        // Amélioration UX pour les liens PDF
        $('a[href*="generate-policy-pdf"]').on('click', function() {
            const $this = $(this);
            const originalText = $this.text();
            
            $this.text('📄 Generating...').addClass('loading');
            
            setTimeout(() => {
                $this.text(originalText).removeClass('loading');
            }, 3000);
        });
        
        // Tri des colonnes du tableau
        $('.policies-table th').on('click', function() {
            const columnIndex = $(this).index();
            const $tbody = $('#policies-tbody');
            const rows = $tbody.find('tr').toArray();
            
            const isAscending = !$(this).hasClass('sort-asc');
            
            // Supprimer les classes de tri de tous les headers
            $('.policies-table th').removeClass('sort-asc sort-desc');
            
            // Ajouter la classe appropriée au header cliqué
            $(this).addClass(isAscending ? 'sort-asc' : 'sort-desc');
            
            rows.sort((a, b) => {
                const aText = $(a).find('td').eq(columnIndex).text().trim();
                const bText = $(b).find('td').eq(columnIndex).text().trim();
                
                // Essayer de comparer comme des nombres d'abord
                const aNum = parseFloat(aText);
                const bNum = parseFloat(bText);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? aNum - bNum : bNum - aNum;
                }
                
                // Sinon comparer comme du texte
                return isAscending ? 
                    aText.localeCompare(bText) : 
                    bText.localeCompare(aText);
            });
            
            $tbody.empty().append(rows);
        });
        
        // Ajouter des indicateurs de tri aux en-têtes
        $('.policies-table th').each(function() {
            $(this).css({
                'cursor': 'pointer',
                'user-select': 'none',
                'position': 'relative'
            }).append('<span class="sort-indicator" style="margin-left:5px;opacity:0.3;">↕</span>');
        });
        
        // Mise à jour des indicateurs de tri
        $(document).on('click', '.policies-table th', function() {
            $('.sort-indicator').text('↕').css('opacity', '0.3');
            
            if ($(this).hasClass('sort-asc')) {
                $(this).find('.sort-indicator').text('↑').css('opacity', '1');
            } else if ($(this).hasClass('sort-desc')) {
                $(this).find('.sort-indicator').text('↓').css('opacity', '1');
            }
        });
        
        // Animation d'entrée pour les statistiques
        $('.stat-box').each(function(index) {
            $(this).css({
                'opacity': '0',
                'transform': 'translateY(20px)'
            }).delay(index * 100).animate({
                'opacity': '1'
            }, 500, function() {
                $(this).css('transform', 'translateY(0)');
            });
        });
        
        // Responsive: Convertir le tableau en cartes sur mobile
        function makeTableResponsive() {
            if ($(window).width() < 768) {
                // Logique pour transformer le tableau en cartes sur mobile
                // (à implémenter si nécessaire)
            }
        }
        
        $(window).on('resize', makeTableResponsive);
        makeTableResponsive();
    });
    
})(jQuery);
