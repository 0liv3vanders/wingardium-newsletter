jQuery(document).ready(function($){
    function closeWingardiumModal() {
        $('.wingardium-overlay').remove();
    }

    // Fermer au clic sur X ou sur l’overlay
    $(document).on('click', '.wingardium-close-modal', closeWingardiumModal);
    $(document).on('click', '.wingardium-overlay', function(e){
        if($(e.target).hasClass('wingardium-overlay')){
            closeWingardiumModal();
        }
    });

    // Détecter paramètres ?wingardium_subscribed=1 ou ?wingardium_unsubscribed=1
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('wingardium_subscribed')) {
        const title   = <?php echo json_encode( get_option('wingardium_modal_subscribe_title','Inscription réussie') ); ?>;
        const content = <?php echo json_encode( get_option('wingardium_modal_subscribe_content','Merci, vous êtes bien inscrit(e) !') ); ?>;

        showWingardiumModal(title, content);
    }
    if(urlParams.has('wingardium_unsubscribed')) {
        const title   = <?php echo json_encode( get_option('wingardium_modal_unsubscribe_title','Désinscription confirmée') ); ?>;
        const content = <?php echo json_encode( get_option('wingardium_modal_unsubscribe_content','Vous êtes bien désinscrit(e)') ); ?>;

        showWingardiumModal(title, content);
    }

    function showWingardiumModal(title, content) {
        const modalHtml = `
               <div class="wingardium-overlay">
                 <div class="wingardium-modal">
                   <span class="wingardium-close-modal">X</span>
                   <h2 style="margin-top:0;">${title}</h2>
                   <p>${content}</p>
                 </div>
               </div>
           `;
        $('body').append(modalHtml);
    }
});