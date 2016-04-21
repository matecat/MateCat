/*
 Component: ui.offline
 */

UI.offlineCacheSize = 30;
UI.offlineCacheRemaining = UI.offlineCacheSize;
UI.checkingConnection = false;

UI.currentConnectionCountdown = null;

$.extend(UI, {
    startOfflineMode: function(){

        if( !UI.offline ){

            UI.offline = true;
            UI.body.attr('data-offline-mode', 'light-off');
            UI.showMessage({
                msg: '<span class="icon-power-cord"></span><span class="icon-power-cord2"></span>No connection available. You can still translate <span class="remainingSegments">' + UI.offlineCacheSize + '</span> segments in offline mode. Do not refresh or you lose the segments!'
            });

            UI.checkingConnection = setInterval( function() {
                UI.checkConnection( 'Recursive Check authorized' );
            }, 5000 );

        }
    },
    endOfflineMode: function () {
        if ( UI.offline ) {

            UI.offline = false;

            UI.showMessage( {
                msg: "Connection is back. We are saving translated segments in the database."
            } );

            setTimeout( function () {
                $( '#messageBar .close' ).click();
            }, 10000 );

            clearInterval( UI.currentConnectionCountdown );
            clearInterval( UI.checkingConnection );
            UI.currentConnectionCountdown = null;
            UI.checkingConnection = false;
            UI.body.removeAttr( 'data-offline-mode' );

            $('.noConnectionMsg').text( 'The connection is back. Your last, interrupted operation has now been done.' );

            setTimeout(function() {
                $('.noConnection').addClass('reConnection');
                setTimeout(function() {
                    $('.noConnection, .noConnectionMsg').remove();
                }, 500);
            }, 3000);

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
    activateOfflineCountdown: function ( message ) {

        if ( !$( '.noConnection' ).length ) {
            UI.body.append( '<div class="noConnection"></div><div class="noConnectionMsg"></div>' );
        }

        $( '.noConnectionMsg' ).html( '<div class="noConnectionMsg">' + message + '<br /><span class="reconnect">Trying to reconnect in <span class="countdown">30 seconds</span>.</span><br /><br /><input type="button" id="checkConnection" value="Try to reconnect now" /></div>' );

        //clear previous Interval and set a new one
        UI.currentConnectionCountdown = $( ".noConnectionMsg .countdown" ).countdown( function () {
            console.log( 'offlineCountdownEnd' );
            UI.checkConnection( 'Clear countdown authorized' );
            UI.activateOfflineCountdown( 'Still no connection.' );
        }, 30, " seconds" );

    },
    checkConnection: function( message ) {

        console.log(message);
        console.log('check connection');

        APP.doRequest({
            data: {
                action: 'ajaxUtils',
                exec: 'ping'
            },
            error: function() {
                /**
                 * do Nothing there are already a thread running
                 * @see UI.startOfflineMode
                 * @see UI.endOfflineMode
                 */
            },
            success: function() {

                console.log('check connection success');
                //check status completed
                if( !UI.restoringAbortedOperations ) {

                    UI.restoringAbortedOperations = true;
                    UI.execAbortedOperations( UI.endOfflineMode );
                    UI.restoringAbortedOperations = false;
                    UI.executingSetTranslation = false;
                    UI.execSetTranslationTail();
                }

            }
        });
    },

    /**
     * NOT USED
     * @deprecated
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
    /**
     * NOT USED
     * @deprecated
     *
     * @param reqArguments
     * @param operation
     */
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
                UI.addInStorage('pending-' + dd.getTime(), JSON.stringify(pendingConnection), 'contribution');
//                localStorage.setItem('pending-' + dd.getTime(), JSON.stringify(pendingConnection));
            }
            if(!UI.checkConnectionTimeout) {
                UI.checkConnectionTimeout = setTimeout(function() {
                    UI.checkConnection();
                    UI.checkConnectionTimeout = false;
                }, 5000);
            }
        }
    },
    /**
     * If there are some callback to be executed after the function call pass it as callback
     *
     * Note: the function stack is executed when the interpreter exit from the local scope
     * so, UI[operation] will be executed after the call of callback_to_execute.
     *
     * If we put the callback_to_execute out of this scope
     *      ( calling after the return of this function and not from inside it )
     *
     * UI[operation] will be executed before callback_to_execute.
     * Not working as expected because this behaviour affects "UI.offline = false;"
     *
     *
     * @param callback_to_execute
     */
    execAbortedOperations: function( callback_to_execute ) {

        callback_to_execute = callback_to_execute || {};
        callback_to_execute.call();
		//console.log(UI.abortedOperations);
        $.each(UI.abortedOperations, function() {
            var args = this.args;
            var operation = this.operation;
            if(operation == 'setTranslation') {
                UI[operation](args);
            } else if(operation == 'setCurrentSegment') {
                UI[operation](args[0]);
            } else if(operation == 'getSegments') {
                UI.reloadWarning();
            } else if( operation == 'setRevision' ){
                UI[operation](args);
            }
        });
        UI.abortedOperations = [];
    },
    checkOfflineCacheSize: function () {
        if ( !UI.offlineCacheRemaining ) {
            UI.activateOfflineCountdown( 'No connection available.' );
            //console.log( 'la cache è piena, andate in pace' );
        }
    },
    decrementOfflineCacheRemaining: function () {
        $('#messageBar .remainingSegments').text( --this.offlineCacheRemaining );
        UI.checkOfflineCacheSize();
    },
    incrementOfflineCacheRemaining: function(){
        // reset counter by 1
        UI.offlineCacheRemaining += 1;
        //$('#messageBar .remainingSegments').text( this.offlineCacheRemaining );
    }
});

$('html').on('mousedown', 'body[data-offline-mode="light-off"] .editor .actions .split', function(e) {
    e.preventDefault();
    APP.alert('Split is disabled in Offline Mode');
});
