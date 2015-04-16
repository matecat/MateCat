/*
 Component: ui.offline
 */
//if(config.offlineModeEnabled) {
    UI.offlineCacheSize = 2;
    UI.offlineCacheRemaining = UI.offlineCacheSize;
    UI.offlineCountdownOn = false;

    $(window).on('offlineON', function(d) {
        UI.offline = true;
        UI.body.attr('data-offline-mode', 'light-off');
//        numUntranslated = $('section.status-new, section.status-draft');
        UI.showMessage({
            msg: '<span class="icon-power-cord"></span><span class="icon-power-cord2"></span>No connection available. You can still translate <span class="remainingSegments">' + UI.offlineCacheSize + '</span> segments in offline mode.'
        })
    }).on('offlineOFF', function(d) {
        console.log('offlineOFF');
        UI.offline = false;
        UI.body.removeAttr('data-offline-mode');

        UI.showMessage({
            msg: "Connection is back. We are saving translated segments in the database."
        })
//        UI.checkConnection();
    }).on('stillNoConnection', function(d) {
        setTimeout(function() {
            UI.checkConnection();
        }, 30000);
    }).on('offlineSegmentSave', function(d) {
        UI.checkOfflineCacheSize();
    }).on('offlineCacheIsFull', function(d) {
        UI.closeOfflineMode('total');
        UI.blockUIForNoConnection();
    })
    $('html').on('mousedown', 'body[data-offline-mode="light-off"] .editor .actions .split', function(e) {
        e.preventDefault();
        APP.alert('Split is disabled in Offline Mode');
    })

    $.extend(UI, {
        closeOfflineMode: function (from) {
            if(this.offline) {
                UI.showMessage({
                    msg: "Connection is back. We are saving translated segments in the database."
                })
                setTimeout(function() {
                    $('#messageBar .close').click();
                }, 10000);
                UI.offline = false;
                UI.body.removeAttr('data-offline-mode');
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
//            console.log('failed connection');
            if(this.offline) {
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
            console.log('blockUIForNoConnection');
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
            if(!$('.noConnection').length) {
                UI.body.append('<div class="noConnection"></div><div class="noConnectionMsg">No connection available.<br /><span class="reconnect">Trying to reconnect in <span class="countdown">30 seconds</span>.</span><br /><br /><input type="button" id="checkConnection" value="Try to reconnect now" /></div>');
                //                $(".noConnectionMsg .countdown").countdown(UI.simpleTest, 30, " seconds");
                console.log('before first countdown');
                this.offlineCountdownStart();

//                $(".noConnectionMsg .countdown").countdown(UI.checkConnection('first countdown'), 30, " seconds");
            }
        },
        offlineCountdownStart: function () {
            this.offlineCountdownOn = true;
            $(".noConnectionMsg .countdown").countdown(UI.offlineCountdownEnd(), 30, " seconds");
        },

        offlineCountdownEnd: function () {
            console.log('offlineCountdownEnd');
            this.offlineCountdownOn = false;
//            this.checkConnection('first countdown');
        },

        goOffline: function () {
            $(window).trigger('offlineON');
        },
        goOnline: function () {
            $(window).trigger('offlineOFF');
        },
        checkConnection: function(messaggio) {
            console.log(messaggio);
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
                        if(!this.offlineCountdownOn) {
                            UI.offlineCountdownStart();
                        }
/*
                        if(UI.offline) {
                            console.log('UI.offline');
//                            $(window).trigger('stillNoConnection');
//                            $(".noConnectionMsg .reconnect").html('Still no connection. Trying to reconnect in <span class="countdown">10 seconds</span>.');
//                            $(".noConnectionMsg .countdown").countdown(UI.checkConnection('new countdown'), 10, " seconds");
                        } else {
                            console.log('NOT UI.offline');
//                            $(".noConnectionMsg .reconnect").html('Still no connection. Trying to reconnect in <span class="countdown">10 seconds</span>.');
//                            console.log('a');
//                            $(".noConnectionMsg .countdown").countdown(UI.checkConnection('new countdown'), 10, " seconds");
//                            $(".noConnectionMsg .countdown").countdown(UI.checkConnection('new countdown'), 10, " seconds");
                        }
*/
                    }
                },
                success: function() {
                    console.log('check connection success');
                    if(!UI.restoringAbortedOperations) UI.connectionIsBack();

                    /*
                    if(config.offlineModeEnabled) {
                        $(window).trigger('offlineOFF');
                    } else {
                        if(!UI.restoringAbortedOperations) UI.connectionIsBack();
                    }
                    */
                }
            });
        },
        connectionIsBack: function() {
//            console.log('connection is back');
            if(this.offline) this.closeOfflineMode('light');
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
        },
        checkOfflineCacheSize: function () {
            if(!UI.offlineCacheRemaining) {
//                console.log('la cache è finita, andate in pace');
                $(window).trigger('offlineCacheIsFull');
            }
        }

        /*
        failover: function(reqArguments, operation) {
            console.log('test offline failover on ' + operation);

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

        }
        */
    });

//}