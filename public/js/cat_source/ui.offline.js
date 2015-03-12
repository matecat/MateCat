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

    $.extend(UI, {
        failover: function(reqArguments, operation) {
            console.log('test offline failover on ' + operation);
            /*
            if(operation != 'getWarning') {
                var pendingConnection = {
                    operation: operation,
                    args: reqArguments
                };
//			console.log('pendingConnection: ', pendingConnection);
                var dd = new Date();
                if(pendingConnection.args) {
                    localStorage.setItem('pending-' + dd.getTime(), JSON.stringify(pendingConnection));
                }
                if(!UI.checkConnectionTimeout) {
                    UI.checkConnectionTimeout = setTimeout(function() {
                        UI.checkConnection();
                        UI.checkConnectionTimeout = false;
                    }, 5000);
                }
            }
            */
        }
    });

}