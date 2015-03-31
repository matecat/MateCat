/**
 Component: ui.noconnection
 */

if(!config.offlineModeEnabled) {

    $(window).on('offlineON', function(d) {

    })

    $.extend(UI, {
        failover: function(reqArguments, operation) {
            console.log('failover on ' + operation);
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
        },

        failedConnection: function(reqArguments, operation) {
            console.log('failed connection');
            console.log('UI.offline: ', UI.offline);
            if(UI.offline) {
                $(window).trigger('stillNoConnection');
            } else {
                $(window).trigger({
                    type: "offlineON",
                    reqArguments: reqArguments,
                    operation: operation
                });
            }
        },
        blockUIForNoConnection: function (reqArguments, operation) {
            if(this.autoFailoverEnabled) {
                this.failover(reqArguments, operation);
                return false;
            }
            if(operation != 'getWarning') {
                var pendingConnection = {
                    operation: operation,
                    args: reqArguments
                };
                UI.abortedOperations.push(pendingConnection);
            }
            if(!config.offlineModeEnabled) {
                if(!$('.noConnection').length) {
                    UI.body.append('<div class="noConnection"></div><div class="noConnectionMsg">No connection available.<br /><span class="reconnect">Trying to reconnect in <span class="countdown">30 seconds</span>.</span><br /><br /><input type="button" id="checkConnection" value="Try to reconnect now" /></div>');
                    $(".noConnectionMsg .countdown").countdown(UI.checkConnection, 30, " seconds");
                }
            }

        },

        checkConnection: function() {
            console.log('check connection');
            APP.doRequest({
                data: {
                    action: 'ajaxUtils',
                    exec: 'ping'
                },
                error: function() {
                    console.log('error on checking connection');
                    if(UI.autoFailoverEnabled) {
                        setTimeout(function() {
                            UI.checkConnection();
                        }, 5000);
                    } else {
                        if(config.offlineModeEnabled) {
                            $(window).trigger('stillNoConnection');
                        } else {
                            $(".noConnectionMsg .reconnect").html('Still no connection. Trying to reconnect in <span class="countdown">30 seconds</span>.');
                            $(".noConnectionMsg .countdown").countdown(UI.checkConnection, 30, " seconds");
                        }


                    }
                },
                success: function() {
                    console.log('connection is back');
                    if(config.offlineModeEnabled) {
                        $(window).trigger('offlineOFF');
                    } else {
                        if(!UI.restoringAbortedOperations) UI.connectionIsBack();
                    }
                }
            });
        },
        connectionIsBack: function() {
            UI.restoringAbortedOperations = true;
            this.execAbortedOperations();
            if(!UI.autoFailoverEnabled) {
                $('.noConnectionMsg').text('The connection is back. Your last, interrupted operation has now been done.');
                setTimeout(function() {
                    $('.noConnection').addClass('reConnection');
                    setTimeout(function() {
                        $('.noConnection, .noConnectionMsg').remove();
                    }, 500);
                }, 3000);
            }
            UI.restoringAbortedOperations = false;

        },
        execAbortedOperations: function() {
            if(UI.autoFailoverEnabled) {
//			console.log(localStorage);
                var pendingArray = [];
                inp = 'pending';
                $.each(localStorage, function(k,v) {
                    if(k.substring(0, inp.length) === inp) {
                        pendingArray.push(JSON.parse(v));
                    }
                });
//			console.log(pendingArray);
                UI.abortedOperations = pendingArray;
            }
//		console.log(UI.abortedOperations);
            $.each(UI.abortedOperations, function() {
                args = this.args;
                operation = this.operation;
                if(operation == 'setTranslation') {
                    UI[operation](args[0], args[1], args[2]);
                } else if(operation == 'updateContribution') {
                    UI[operation](args[0], args[1]);
                } else if(operation == 'setContributionMT') {
                    UI[operation](args[0], args[1], args[2]);
                } else if(operation == 'setCurrentSegment') {
                    UI[operation](args[0]);
                } else if(operation == 'getSegments') {
                    UI.reloadWarning();
                }
            });
            UI.abortedOperations = [];
            UI.clearStorage('pending');
        }

    });

}