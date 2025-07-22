// Maljani Sales Form JavaScript
jQuery(document).ready(function($) {
    console.log('Maljani Sales Form loaded');
    
    // Validation en temps réel des dates
    $('input[name="departure"], input[name="return"]').on('change', function() {
        const departure = $('input[name="departure"]').val();
        const return_date = $('input[name="return"]').val();
        
        if (departure && return_date) {
            const dep = new Date(departure);
            const ret = new Date(return_date);
            
            if (dep >= ret) {
                $(this).css('border-color', 'red');
                alert('La date de retour doit être postérieure à la date de départ.');
            } else {
                $('input[name="departure"], input[name="return"]').css('border-color', '');
                
                // Calcul automatique des jours
                const diffTime = Math.abs(ret - dep);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                $('#days-covered').text(diffDays);
                
                // Mise à jour du premium si disponible
                if (window.maljaniPremiums && diffDays > 0) {
                    let newPremium = '';
                    window.maljaniPremiums.forEach(function(row) {
                        if (diffDays >= parseInt(row.from) && diffDays <= parseInt(row.to)) {
                            newPremium = row.premium;
                        }
                    });
                    if (newPremium) {
                        $('#premium-amount').text(newPremium);
                        $('input[name="amount_paid"]').val(newPremium);
                    }
                }
            }
        }
    });
    
    // Confirmation avant soumission finale
    $('form[method="post"]').on('submit', function(e) {
        const acceptTerms = $('input[name="accept_terms"]').is(':checked');
        const paymentRef = $('input[name="payment_reference"]').val();
        
        if (!acceptTerms) {
            e.preventDefault();
            alert('Veuillez accepter les conditions générales.');
            return false;
        }
        
        if (!paymentRef) {
            e.preventDefault();
            alert('Veuillez saisir une référence de paiement.');
            return false;
        }
        
        // Log de soumission de formulaire
        console.log('Form submission:', {
            action: this.action,
            policy_id: ctaDep ? ctaDep.value : 'N/A',
            departure: ctaDep ? ctaDep.value : 'N/A',
            return: ctaRet ? ctaRet.value : 'N/A'
        });
        
        return confirm('Confirmez-vous la soumission de cette demande d\'assurance ?');
    });
});
