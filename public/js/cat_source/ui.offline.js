/*
 Component: ui.offline
 */
if(config.offlineModeEnabled) {

    $(window).on('offlineON', function(d) {
        UI.offline = true;
//        numUntranslated = $('section.status-new, section.status-draft');
        UI.showMessage({
            msg: 'No connection available. You can still translate in offline mode until you reach the maximum storage size.'})
    }).on('offlineOFF', function(d) {
        console.log('offlineOFF');
        UI.offline = false;
        UI.showMessage({
            msg: "Connection is back. We are saving translated segments in the database."})
        UI.checkConnection();
    }).on('stillNoConnection', function(d) {
        setTimeout(function() {
            UI.checkConnection();
        }, 30000);
    })

}