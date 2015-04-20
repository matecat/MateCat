/*
 Component: ui.offline
 */

UI.offlineCacheSize = 2;
UI.offlineCacheRemaining = UI.offlineCacheSize;
UI.offlineCountdownOn = false;
UI.checkingConnection = false;

$.extend(UI, {
    startOfflineMode: function(){
        if( !this.offline ){
            UI.offline = true;
            UI.body.attr('data-offline-mode', 'light-off');
            UI.showMessage({
                msg: '<span class="icon-power-cord"></span><span class="icon-power-cord2"></span>No connection available. You can still translate <span class="remainingSegments">' + UI.offlineCacheSize + '</span> segments in offline mode.'
            });
        }
    },
    endOfflineMode: function (from) {
        if ( this.offline ) {
            UI.showMessage( {
                msg: "Connection is back. We are saving translated segments in the database."
            } );
            setTimeout( function () {
                $( '#messageBar .close' ).click();
            }, 10000 );
            UI.offline = false;
            UI.body.removeAttr( 'data-offline-mode' );
        }
    },
    failover: function(reqArguments, operation) {
//            console.log('failover on ' + operation);
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

        UI.startOfflineMode();

        if ( operation != 'getWarning' ) {
            var pendingConnection = {
                operation: operation,
                args: reqArguments
            };
            UI.abortedOperations.push( pendingConnection );
        }

    },
    blockUIForNoConnection: function (reqArguments, operation) {
        console.log('blockUIForNoConnection');
        if(this.autoFailoverEnabled) {
            this.failover(reqArguments, operation);
            return false;
        }
        this.offlineCountdown( 'No connection available.' );
    },
    offlineCountdown: function ( message ) {

        if ( this.offlineCountdownOn ) {
            // this is a semaphore, only one thread at a time must act
            return false;
        }

        if ( !$( '.noConnection' ).length ) {
            UI.body.append( '<div class="noConnection"></div><div class="noConnectionMsg"></div>' );
        }

        $( '.noConnectionMsg' ).html( '<div class="noConnectionMsg">' + message + '<br /><span class="reconnect">Trying to reconnect in <span class="countdown">30 seconds</span>.</span><br /><br /><input type="button" id="checkConnection" value="Try to reconnect now" /></div>' );

        this.offlineCountdownOn = true;
        $( ".noConnectionMsg .countdown" ).countdown( function () {
            console.log( 'offlineCountdownEnd' );
            UI.offlineCountdownOn = false;
            UI.checkConnection( 'Clear countdown authorized', true );
        }, 30, " seconds" );

    },
    goOnline: function () {
        $(window).trigger('offlineOFF');
    },
    checkConnection: function(message, authorizedCheck) {

        //we does not want to flood server with connection checks
        // one connection check at a time is enough
        // BUT when an human clicks the event MUST pass, so set authorizedCheck to true for human events
        authorizedCheck = typeof authorizedCheck !== 'undefined' ? authorizedCheck : false;
        if( this.checkingConnection && !authorizedCheck ){
            console.log( 'Connection already in check status' );
            return false;
        }

        console.log(message);
        console.log('check connection');

        this.checkingConnection = true;
        APP.doRequest({
            data: {
                action: 'ajaxUtils',
                exec: 'ping'
            },
            error: function() {
                console.log('error on checking connection');
                if(UI.autoFailoverEnabled) {
                    setTimeout(function() {
                        UI.checkConnection( 'Recursive Check authorized', true );
                    }, 5000);
                } else {
                    UI.offlineCountdown( 'Still no connection.' );
                }
            },
            success: function() {
                console.log('check connection success');

                //check status completed
                UI.checkingConnection = false;
                if(!UI.restoringAbortedOperations) UI.connectionIsBack();

            }
        });
    },
    connectionIsBack: function() {
//            console.log('connection is back');
        if(this.offline) this.endOfflineMode('light');
        this.restoringAbortedOperations = true;
        this.execAbortedOperations();
        if(!this.autoFailoverEnabled) {
            $('.noConnectionMsg').text('The connection is back. Your last, interrupted operation has now been done.');
            setTimeout(function() {
                $('.noConnection').addClass('reConnection');
                setTimeout(function() {
                    $('.noConnection, .noConnectionMsg').remove();
                }, 500);
            }, 3000);
        }
        this.restoringAbortedOperations = false;
        this.executingSetTranslation = false;
        this.execSetTranslationTail();
        this.execSetContributionTail();
    },

    /**
     * NOT USED
     * @param operation
     * @param job
     * @returns {Array|*}
     */
    extractLocalStoredItems: function (operation, job) {
        job = job || false;
        items = [];
        $.each(localStorage, function(k,v) {
            op = k.substring(0, operation.length);
            if(op === operation) {
                kAr = k.split('-');
                if(job) {
                    if(kAr[1] === job) {
                        console.log(kAr[1]);
                        return true;
                    }
                }
                items.push({
                    'operation': op,
                    'job': kAr[1],
                    'sid': kAr[2],
                    'value': JSON.parse(v)
                });
//                    items.push(JSON.parse(v));
            }
        });
        return items;

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
		//console.log(UI.abortedOperations);
        $.each(UI.abortedOperations, function() {
            args = this.args;
            operation = this.operation;
            if(operation == 'setTranslation') {
                UI[operation](args[0], args[1], args[2]);
                UI.incrementOfflineCacheRemaining(); // re-add 1 to Cache max size
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
    },
    checkOfflineCacheSize: function () {
        if ( !UI.offlineCacheRemaining ) {
            UI.blockUIForNoConnection();
            //console.log( 'la cache è piena, andate in pace' );
        }
    }

});


$(window).on('offlineOFF', function(d) {

    console.log('offlineOFF');
    UI.offline = false;
    UI.body.removeAttr('data-offline-mode');

    UI.showMessage({
        msg: "Connection is back. We are saving translated segments in the database."
    });

});

$('html').on('mousedown', 'body[data-offline-mode="light-off"] .editor .actions .split', function(e) {
    e.preventDefault();
    APP.alert('Split is disabled in Offline Mode');
});

//            UI.offlineCacheRemaining = UI.offlineCacheSize; // reset counter with 1 second of delay
