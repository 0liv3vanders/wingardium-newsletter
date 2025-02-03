jQuery(document).ready(function($){
    function closeWingardiumModal() {
        $('.wingardium-overlay').remove();
    }
    $(document).on('click', '.wingardium-close-modal', closeWingardiumModal);
    $(document).on('click', '.wingardium-overlay', function(e){
        if($(e.target).hasClass('wingardium-overlay')){
            closeWingardiumModal();
        }
    });

    // Vérifie les paramètres d’URL
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('wingardium_subscribed')) {
        // On utilise les valeurs passées via wp_localize_script
        let title   = WingardiumModalData.subscribeTitle;
        let content = WingardiumModalData.subscribeContent;
        showWingardiumModal(title, content);
    }
    if(urlParams.has('wingardium_unsubscribed')) {
        let title   = WingardiumModalData.unsubscribeTitle;
        let content = WingardiumModalData.unsubscribeContent;
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
