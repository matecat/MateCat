/*
 Component: ui.offline
 */

UI.offlineCacheSize = 20;
UI.offlineCacheRemaining = UI.offlineCacheSize;
UI.checkingConnection = false;

UI.currentConnectionCountdown = null;
UI._backupEvents = {};

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
                    $._data( $("body")[0] ).events = UI._backupEvents;
                    UI._backupEvents = {};
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

        //remove focus from the edit area
        setTimeout( function(){
            UI.editarea.blur();
            $('#checkConnection').focus();
            UI._backupEvents = $._data( $("body")[0] ).events;
            $._data( $("body")[0] ).events = {}
        }, 300 );


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

                    //reset counter
                    UI.offlineCacheRemaining = UI.offlineCacheSize;
                }

            }
        });
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
                /**
                 * No Op because
                 * @see UI.checkConnection
                 * already perform a
                 * UI.execSetTranslationTail();
                 */
            } else if(operation == 'setCurrentSegment') {
                /**
                 * No OP, Not Useful
                 */
                //UI[operation](args[0]);
            } else if(operation == 'getSegments') {
                UI.reloadWarning();
            } else if( operation == 'setRevision' ){
                UI[operation](args);
            }
        });
        UI.abortedOperations = [];
    },
    checkOfflineCacheSize: function () {
        if ( UI.offlineCacheRemaining <= 0 ) {
            UI.activateOfflineCountdown( 'No connection available.' );
            //console.log( 'la cache è piena, andate in pace' );
        }
    },
    decrementOfflineCacheRemaining: function () {
        $('#messageBar .remainingSegments').text( --this.offlineCacheRemaining );
        UI.showExistingMessage();
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
