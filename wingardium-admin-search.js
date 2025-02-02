jQuery(document).ready(function($){

    /************************************************************
     * RECHERCHE ABONNES (onglet "Abonnés & Newsletter")
     ************************************************************/
    let $subSearch = $('#wingardium-subscribers-search');
    let $subList   = $('#wingardium-subscribers-list');
    let $subSpinner= $subSearch.siblings('.spinner');

    function loadSubscribers(searchTerm){
        $subSpinner.show();
        $.post(WingardiumAjax.ajaxUrl, {
            action: 'wingardium_search_subscribers',
            security: WingardiumAjax.nonce,
            search: searchTerm
        }, function(response){
            $subSpinner.hide();
            if(response.success){
                $subList.html(response.data);
            } else {
                $subList.html('<p>Une erreur est survenue.</p>');
            }
        });
    }

    // Charger la liste au démarrage
    loadSubscribers('');

    // Écoute input
    $subSearch.on('input', function(){
        let term = $(this).val();
        loadSubscribers(term);
    });


    /************************************************************
     * RECHERCHE TEMPLATES (onglet "Templates")
     ************************************************************/
    let $tmplSearch   = $('#wingardium-templates-search');
    let $tmplList     = $('#wingardium-templates-list');
    let $tmplSpinner  = $tmplSearch.siblings('.spinner');

    function loadTemplates(searchTerm){
        $tmplSpinner.show();
        $.post(WingardiumAjax.ajaxUrl, {
            action: 'wingardium_search_templates',
            security: WingardiumAjax.nonce,
            search: searchTerm
        }, function(response){
            $tmplSpinner.hide();
            if(response.success){
                $tmplList.html(response.data);
            } else {
                $tmplList.html('<p>Une erreur est survenue.</p>');
            }
        });
    }

    // Charger la liste au démarrage
    loadTemplates('');

    // Écoute input
    $tmplSearch.on('input', function(){
        let term = $(this).val();
        loadTemplates(term);
    });

});
