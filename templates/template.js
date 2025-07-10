jQuery(document).ready(function ($) {
    console.log('Maljani Premium Calculator initialized.');
    $('#maljani-premium-calc').on('submit', function(e){
        e.preventDefault();
        let dep = new Date($(this).find('input[name="departure"]').val());
        let ret = new Date($(this).find('input[name="return"]').val());
        if(isNaN(dep) || isNaN(ret) || ret <= dep){
            $('#maljani-premium-result').html('<span style="color:red;">Please enter valid dates (return after departure).</span>');
            return;
        }
        let days = Math.ceil((ret - dep) / (1000*60*60*24));
        if(days < 1){
            $('#maljani-premium-result').html('<span style="color:red;">Minimum 1 day coverage.</span>');
            return;
        }
        // Vérifie la variable premiums
        let premiums = window.maljaniPremiums || [];
        console.log('Premiums table:', premiums, 'Days:', days);

        if (!Array.isArray(premiums) || premiums.length === 0) {
            $('#maljani-premium-result').html('<span style="color:red;">No premium table found for this policy.</span>');
            return;
        }

        let found = null;
        for(let i=0; i<premiums.length; i++){
            let from = parseInt(premiums[i].from, 10);
            let to = parseInt(premiums[i].to, 10);
            console.log('Checking range:', from, '-', to, 'for', days, 'days');
            if(days >= from && days <= to){
                found = premiums[i].premium;
                break;
            }
        }
        if(found){
            $('#maljani-premium-result').html('<strong>Premium for '+days+' day(s): </strong><span style="color:green;">'+found+'</span>');
        }else{
            $('#maljani-premium-result').html('<span style="color:red;">No premium found for '+days+' days.</span>');
        }
    });
});