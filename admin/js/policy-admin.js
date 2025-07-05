jQuery(document).ready(function ($) {
	console.log('Maljani Admin JS Loaded');
    var frame;
    $('#upload_policy_feature_img').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({
            title: 'Select or Upload Feature Image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            var ratio = attachment.width / attachment.height;
            // Vérifie si ce n'est pas du portrait 4:6 (0.66)
            if(ratio < 0.64 || ratio > 0.68) {
                if(!confirm("L'image sélectionnée n'est pas au format portrait recommandé (4:6). Voulez-vous continuer quand même ?")) {
                    return;
                }
            }
            $('#policy_feature_img').val(attachment.id);
            $('#policy_feature_img_preview').attr('src', attachment.url).show();
        });
        frame.open();
    });
});