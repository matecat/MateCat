/*
 Component: ui.offline
 */
if(config.offlineModeEnabled) {

    $(window).on('offlineON', function(d) {
        numUntranslated = $('section.status-new, section.status-draft')
        UI.showMessage({
            msg: 'No connection available. You can still translate in offline mode. You have <span class="left">30</span> segments left to translate while you wait for connection to return.'})
    })

}