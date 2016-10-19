APP = null;

APP = {
    init: function () {
        this.isCattool = $( 'body' ).hasClass( 'cattool' );
        $( "body" ).on( 'click', '.modal .x-popup', function ( e ) {
            e.preventDefault();
            APP.closePopup();
        } ).on( 'click', '.modal[data-type=alert] .btn-ok', function ( e ) {
            e.preventDefault();
            APP.closePopup();
            if ( $( this ).attr( 'data-callback' ) ) {
                if ( typeof UI[$( this ).attr( 'data-callback' )] === 'function' ) {
                    UI[$( this ).attr( 'data-callback' )]();
                    APP.confirmValue = true;
                }
            }
        } ).on( 'click', '.modal[data-type=confirm] .btn-ok:not(.disabled), .modal[data-type=confirm_checkbox] .btn-ok:not(.disabled)', function ( e ) {
            e.preventDefault();
            var dataType = $('.modal' ).attr('data-type');

            if ( !$( '.modal[data-type='+dataType+']' ).hasClass( 'closeOnSuccess' ) ) APP.closePopup();
            if ( $( this ).attr( 'data-callback' ) ) {
                if ( typeof UI[$( this ).attr( 'data-callback' )] === 'function' ) {
                    var context = $( this ).attr( 'data-context' ) || '';
                    UI[$( this ).attr( 'data-callback' )]( decodeURI( context ) );
                    APP.confirmValue = true;
                } else {
                    APP.confirmValue = APP.confirmCallbackFunction();
                }
            }
            APP.waitingConfirm = false;
            APP.cancelValue = false;
        } ).on( 'click', '.modal[data-type=confirm_checkbox] .btn-cancel, .modal[data-type=confirm] .btn-cancel, .modal[data-type=confirm] .x-popup', function ( e ) {
            e.preventDefault();
            APP.closePopup();
            el = $( this ).parents( '.modal' ).find( '.btn-cancel' );
            if ( $( el ).attr( 'data-callback' ) ) {
                if ( typeof UI[$( el ).attr( 'data-callback' )] === 'function' ) {
                    var context = $( el ).attr( 'data-context' ) || '';
                    UI[$( el ).attr( 'data-callback' )]( decodeURI( context ) );
                } else {
                    APP.cancelValue = APP.cancelCallbackFunction();
                }
            }
            APP.confirmValue = false;
            APP.waitingConfirm = false;
            APP.cancelValue = true;
        } ).on( 'click', '.popup-outer.closeClickingOutside', function ( e ) {
            e.preventDefault();
            $( this ).parents( '.modal' ).find( '.x-popup' ).click();
        } );

        $( '#sign-in' ).click( function ( e ) {
            e.preventDefault();
            APP.googole_popup( $( this ).data( 'oauth' ) );
        } );

        $( '#sign-in-o, #sign-in-o-mt' ).click( function ( e ) {
            $( '#sign-in' ).trigger( 'click' );
        } );
    },
    alert: function ( options ) {
        //FIXME
        // Alert message, NEVER displayed if there are a redirect after it because html div popups are no-blocking
        // Transform alert to a function like confirm with a callable function passed as callback

        if ( typeof options == 'string' ) {
            options.callback = false;
            options.msg = options;
        }
        var callback = (typeof options == 'string') ? false : options.callback;
        var content = (typeof options == 'string') ? options : options.msg;
        this.popup( {
            type: 'alert',
            onConfirm: callback,
            closeClickingOutside: true,
            title: (options.title || 'Warning'),
            content: content
        } );
    },
    googole_popup: function ( url ) {
        //var rid=$('#rid').text();
        //url=url+'&rid='+rid;
        var newWindow = window.open( url, 'name', 'height=600,width=900' );
        if ( window.focus ) {
            newWindow.focus();
        }
    },
    confirm: function ( options ) {
        this.waitingConfirm = true;
        this.popup( {
            type: (options.type || 'confirm'),
            name: options.name,
            onConfirm: options.callback,
            caller: options.caller,
            onCancel: options.onCancel,
            title: (options.title || 'Confirmation required'),
            cancelTxt: options.cancelTxt,
            okTxt: options.okTxt,
            content: options.msg,
            context: options.context,
            closeOnSuccess: (options.closeOnSuccess || false)
        } );
        return APP.confirmValue; // TODO: this return value is clearly meaningless
    },
    confirmAndCheckbox: function(options){
        this.waitingConfirm = true;
        this.popup( {
            type: (options.type || 'confirm_checkbox'),
            name: options.name,
            onConfirm: options.callback,
            caller: options.caller,
            onCancel: options.onCancel,
            title: (options.title || 'Confirmation required'),
            cancelTxt: options.cancelTxt,
            okTxt: options.okTxt,
            content: options.msg,
            context: options.context,
            closeOnSuccess: (options.closeOnSuccess || false),
            checkbox_label: options['checkbox-label']
        } );
    },
    initMessageBar: function () {
        if ( !$( 'header #messageBar' ).length ) {
            console.log( 'no messageBar found' );
            $( 'header' ).prepend( '<div id="messageBar"><span class="msg"></span><a href="#" class="close"></a></div>' );
        }
        $( "body" ).on( 'click', '#messageBar .close', function ( e ) {
            e.preventDefault();
            $( 'body' ).removeClass( 'incomingMsg' );
            $( '#messageBar' ).html( '<span class="msg"></span><a href="#" class="close"></a>' );
        } );
    },
    showMessage: function ( options ) {
        $( '#messageBar .msg' ).html( options.msg );
        if ( typeof options.fixed != 'undefined' ) {
            $( '#messageBar' ).addClass( 'fixed' );
        } else {
            $( '#messageBar' ).removeClass( 'fixed' );
        }
        $( 'body' ).addClass( 'incomingMsg' );
    },
    doRequest: function ( req, log ) {

        var logTxt = (typeof log == 'undefined') ? '' : '&type=' + log;
        var version = (typeof config.build_number == 'undefined') ? '' : '-v' + config.build_number;
        var builtURL = (req.url) ? req.url : config.basepath + '?action=' + req.data.action + logTxt + this.appendTime() + version + ',jid=' + config.id_job + ((typeof req.data.id_segment != 'undefined') ? ',sid=' + req.data.id_segment : '');
        var reqType = (req.type) ? req.type : 'POST';
        var setup = {
            url: builtURL,

			data: req.data,
			type: reqType,
			dataType: 'json'
			//TODO set timeout longer than server curl for TM/MT
		};

		// Callbacks
		if (typeof req.success === 'function')
			setup.success = req.success;
		if (typeof req.complete === 'function')
			setup.complete = req.complete;
		if (typeof req.context != 'undefined')
			setup.context = req.context;
		if (typeof req.error === 'function')
			setup.error = req.error;
		if (typeof req.beforeSend === 'function')
			setup.beforeSend = req.beforeSend;

		return $.ajax(setup);
	}, 
    appendTime: function() {
        var t = new Date();
        return '&time=' + t.getTime();
    },
    disableLink : function(e){
        e.preventDefault();
    },
    popup: function ( conf ) {
        this.closePopup();

        _tpl_newPopup = '' +
                '<div class="modal">' +
                ' <div class="popup-outer"></div>' +
                '</div>';

        _tpl_popupInner = '' +
                '<div class="popup">' +
                ' <a href="javascript:;" class="x-popup remove"></a>' +
                ' <h1></h1>' +
                ' <p></p>' +
                '</div>';

        _tpl_button = '' +
                '<a href="javascript:;" class="btn-ok">Ok</a>';

        _tpl_checkbox = '' +
                        '<div class="boxed">' +
                        ' <input type="checkbox" id="popup-checkbox" class="confirm_checkbox"><label></label>' +
                        '</div>';


        _tpl_checkbox_dontshow = '' +
                '<div class="boxed">' +
                ' <input type="checkbox" class="dont_show"><label></label>' +
                '</div>';

        var renderOkButton = function ( options ) {
            var filled_tpl = $(_tpl_button);
            filled_tpl.attr("class","")
                    .addClass( 'btn-ok' )
                    .html("Ok");

            if ( typeof options[ 'callback'] != 'undefined' ) {
                filled_tpl.data( 'callback', options['callback'] )
                        .attr( 'data-callback', options['callback'] );
            }

            if ( typeof options['txt'] != 'undefined' ) {
                filled_tpl.html( options['txt'] );
            }

            if ( typeof options['context'] != 'undefined' ) {
                filled_tpl.data( 'context', options['context'] )
                        .attr( 'data-context', options['context'] );
            }

            return filled_tpl;
        };

        var renderCancelButton = function ( options ) {
            var filled_tpl = $( _tpl_button );

            filled_tpl.attr("class","")
                    .addClass( 'btn-cancel' )
                    .html("Cancel");

            if ( typeof options['callback'] != 'undefined' ) {
                filled_tpl.data( 'callback', options['callback'] )
                        .attr( 'data-callback', options['callback'] );
            }

            if ( typeof options['context'] != 'undefined' ) {
                filled_tpl.data( 'context', options['context'] )
                        .attr( 'data-context', options['context'] );
            }

            if ( typeof options['txt'] != 'undefined' ) {
                filled_tpl.html( options['txt'] );
            }
            return filled_tpl;
        };

        var renderButton = function ( options ){
            var filled_tpl = $( _tpl_button );

            if ( typeof options['callback'] != 'undefined' ) {
                var params = '';

                if ( typeof options['params'] != 'undefined' ) {
                    params = options['params'];
                }

                filled_tpl.attr( 'onClick', "UI."+options['callback'] +"(\'"+params+"\');return false;" );
            }

            if ( typeof options['btn-type'] != 'undefined' ) {
                filled_tpl.addClass('btn-'+options['btn-type']);
            }

            if ( typeof options['context'] != 'undefined' ) {
                filled_tpl.data( 'context', options['context'] )
                        .attr( 'data-context', options['context'] );
            }

            if ( typeof options['txt'] != 'undefined' ) {
                filled_tpl.html( options['txt'] );
            }
            return filled_tpl;
        };

        var renderCheckbox = function ( options ) {
            var filled_tpl = $( _tpl_checkbox );

            if ( typeof options['checkbox_label'] != 'undefined' ) {
                filled_tpl.find('.confirm_checkbox + label').html(options['checkbox_label']);
            }
            return filled_tpl;
        };

        var renderDontShowCheckbox = function( options ){
            var filled_tpl = $( _tpl_checkbox_dontshow );

            if ( typeof options['checkbox_label'] != 'undefined' ) {
                filled_tpl.find('.dont_show + label' )
                        .html("Don't show this dialog again for the current job");
            }
            return filled_tpl;
        };

        var renderPopupInner = function ( options ) {
            var filled_tpl = $( _tpl_popupInner );
            if ( typeof options['type'] != 'undefined' ) {
                switch ( options['type'] ) {
                    case 'alert' :
                        filled_tpl.addClass( 'popup-alert' );
                        break;

                    case 'confirm':
                        filled_tpl.addClass( 'popup-confirm' );
                        break;
                    default:
                        break;
                }
            }

            if ( typeof options['title'] != 'undefined' ) {
                filled_tpl.find( 'h1' ).html( options['title'] );
            }

            if ( typeof options['content'] != 'undefined' ) {
                filled_tpl.find( 'p' ).html( options['content'] );
            }

            return filled_tpl;
        };

        var renderPopup = function ( options ) {
            var filled_tpl = $( _tpl_newPopup );

            if ( typeof options['closeOnSuccess'] != 'undefined' ) {
                filled_tpl.addClass( 'closeOnSuccess' );
            }

            if ( conf.closeClickingOutside ){
                filled_tpl.find( '.popup-outer' ).addClass( 'closeClickingOutside' );
            }

            filled_tpl.attr( 'data-name', '' ).
                    data( 'name', '' );

            if ( typeof options['name'] != 'undefined' ) {
                filled_tpl.attr( 'data-name', options['name'] ).
                        data( 'name', options['name'] );
            }

            filled_tpl.append( renderPopupInner( options ) );

            if ( typeof options['type'] != 'undefined' ) {
                filled_tpl.attr( 'data-type', options['type'] ).
                        data( 'type', options['type'] );
                switch ( options['type'] ) {
                    case 'alert' :
                        filled_tpl.find( '.popup' )
                                .append( renderOkButton( {
                                            'context' : options['context'],
                                            'callback': options['onConfirm'],
                                            'txt': options['okTxt']
                                        }
                                )
                        );
                        break;
                    case 'confirm':
                    case 'confirm_checkbox' :
                        if ( options['type'] == 'confirm_checkbox' ) {
                            filled_tpl.find( '.popup p' )
                                    .append( renderCheckbox( options ) );
                        }

                        filled_tpl.find( '.popup' )
                                .addClass('confirm_checkbox')
                                .addClass('popup-confirm')
                                .append( renderCancelButton( {
                                    'context' : options['context'],
                                    'callback': options['onCancel'],
                                    'txt': options['cancelTxt']
                                } )
                                ).append( renderOkButton( {
                                    'context' : options['context'],
                                    'callback': options['onConfirm'],
                                    'txt': options['okTxt']
                                })
                        );

                        APP.confirmCallbackFunction = (options.onConfirm) ? options.onConfirm : null;
                        APP.cancelCallbackFunction = (options.onCancel) ? options.onCancel : null;
                        APP.callerObject = (options.caller) ? options.caller : null;

                        if ( options['type'] == 'confirm_checkbox' ) {
                            filled_tpl.find( '.popup' )
                                    .append( renderDontShowCheckbox( options ) );

                            disableOk( filled_tpl );


                            $( 'body' ).on( 'click', '#popup-checkbox', function () {
                                if ( $( '#popup-checkbox' ).is( ':checked' ) ) {
                                    enableOk( filled_tpl );
                                }
                                else {
                                    disableOk( filled_tpl );
                                }
                            } )
                            .on( 'click', '.dont_show', function () {
                                if ( $( '.dont_show' ).is( ':checked' ) ) {
                                    //set global variable because the popup will be destroyed on close event.
                                    dont_show = 1;
                                }
                                else {
                                    dont_show = 0;
                                }

                            } );
                        }
                        break;

                    case 'free':
                        filled_tpl.find( '.popup' ).append(
                                renderButton( {
                                    'callback': this.callback,
                                    'btn-type': this.type,
                                    'params': this.params,
                                    'txt': this.text
                                } )
                        );
                        break;
                    default:
                        break;
                }
            }
            return filled_tpl;
        };

        var disableOk = function( context ){
            var callback = context.find('.btn-ok' ).attr('data-callback');

            context.find('.btn-ok' )
                    .addClass('disabled' )
                    .attr('disabled','disabled' )
                    .removeAttr('data-callback' )
                    .attr('data-callback-disabled', callback)
                    .bind("click",UI.disableLink);
        };

        var enableOk = function ( context ) {
            var callback = context.find('.btn-ok' ).attr('data-callback-disabled');
            context.find('.btn-ok' )
                    .removeClass('disabled' )
                    .removeAttr('disabled')
                    .removeAttr('data-callback-disabled' )
                    .attr('data-callback', callback)
                    .unbind('click', UI.disableLink);
        };

        newPopup = renderPopup( conf );

        $( 'body' ).append( newPopup );

    },
    closePopup: function () {
        $( '.modal[data-type=view]' ).hide();
        $( '.modal:not([data-type=view])' ).remove();
        // TODO: not sure this is still useful
        $(window).trigger({
            type: "modalClosed"
        });
    },
    fitText: function ( container, child, limitHeight, escapeTextLen, actualTextLow, actualTextHi ) {
        if ( typeof escapeTextLen == 'undefined' ) escapeTextLen = 12;
        if ( typeof $( child ).attr( 'data-originalText' ) == 'undefined' ) {
            $( child ).attr( 'data-originalText', $( child ).text() );
        }

        var originalText = $( child ).text();

        //tail recursion exit control
        if ( originalText.length < escapeTextLen || ( actualTextLow + actualTextHi ).length < escapeTextLen ) {
            return false;
        }

        if ( typeof actualTextHi == 'undefined' && typeof actualTextLow == 'undefined' ) {

            //we are in window.resize
            if ( originalText.match( /\[\.\.\.]/ ) ) {
                originalText = $( child ).attr( 'data-originalText' );
            }

            actualTextLow = originalText.substr( 0, Math.ceil( originalText.length / 2 ) );
            actualTextHi = originalText.replace( actualTextLow, '' );
        }

        actualTextHi = actualTextHi.substr( 1 );
        actualTextLow = actualTextLow.substr( 0, actualTextLow.length - 1 );

        child.text( actualTextLow + '[...]' + actualTextHi );

        var test = true;
        // break recursion for browser width resize below 1024 px to avoid infinite loop and stack overflow
        while ( container.height() >= limitHeight && test == true ) {
            test = this.fitText(container, child, limitHeight, escapeTextLen, actualTextLow, actualTextHi);
        }
        return false;

    },
    objectSize: function ( obj ) {
        var size = 0, key;
        for ( key in obj ) {
            if ( obj.hasOwnProperty( key ) ) size++;
        }
        return size;
    },
    addCommas: function ( nStr ) {
        nStr += '';
        x = nStr.split( '.' );
        x1 = x[0];
        x2 = x.length > 1 ? '.' + x[1] : '';
        var rgx = /(\d+)(\d{3})/;
        while ( rgx.test( x1 ) ) {
            x1 = x1.replace( rgx, '$1' + ',' + '$2' );
        }
        return x1 + x2;
    },
    zerofill: function ( i, l, s ) {
        var o = i.toString();
        if ( !s ) {
            s = '0';
        }
        while ( o.length < l ) {
            o = s + o;
        }
        return o;
    },
    addDomObserver: function (element, callback) {
        if (_.isUndefined(element)) return;
        MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

        var observer = new MutationObserver(function(mutations, observer) {
            // fired when a mutation occurs
            callback.call();
        });
        // define what element should be observed by the observer
        // and what types of mutations trigger the callback
        observer.observe(element, {
            childList: true,
            characterData: false,
            attributes: false,
        });
    },
    /**
     * Function to add notifications to the interface
     * notification object with the following properties
     *
     * title:           (String) Title of the notification.
     * text:            (String) Message of the notification
     * type:            (String, Default "info") Level of the notification. Available: success, error, warning and info.
     * position:        (String, Default "bl") Position of the notification. Available: tr (top right), tl (top left),
     *                      tc (top center), br (bottom right), bl (bottom left), bc (bottom center)
     * closeCallback    (Function) A callback function that will be called when the notification is about to be removed.
     * openCallback     (Function) A callback function that will be called when the notification is successfully added.
     * allowHtml:       (Boolean, Default false) Set to true if the text contains HTML, like buttons
     * autoDismiss:     (Boolean, Default true) Set if notification is dismissible by the user.
     *
     */

    addNotification: function (notification) {
        if (!APP. notificationBox) {
            APP.notificationBox = ReactDOM.render(
                React.createElement(NotificationBox),
                $(".notifications-wrapper")[0]
            );
        }
        
        return APP.notificationBox.addNotification(notification);
    },
    removeNotification: function (notification) {
        if (APP.notificationBox) {
            APP.notificationBox.removeNotification(notification);
        }
    },

    getParameterByName : function(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, " "));
    },
    getCursorPosition :  function(editableDiv) {
        var caretPos = 0,
            sel, range;
        if (window.getSelection) {
            sel = window.getSelection();
            if (sel.rangeCount) {
                range = sel.getRangeAt(0);
                if (range.commonAncestorContainer == editableDiv) {
                    caretPos = range.endOffset;
                }
            }
        } else if (document.selection && document.selection.createRange) {
            range = document.selection.createRange();
            if (range.parentElement() == editableDiv) {
                var tempEl = document.createElement("span");
                editableDiv.insertBefore(tempEl, editableDiv.firstChild);
                var tempRange = range.duplicate();
                tempRange.moveToElementText(tempEl);
                tempRange.setEndPoint("EndToEnd", range);
                caretPos = tempRange.text.length;
            }
        }
        return caretPos;
    }
};

$.extend( $.expr[":"], {
    "containsNC": function ( elem, i, match ) {
        return (elem.textContent || elem.innerText || "").toLowerCase().indexOf( (match[3] || "").toLowerCase() ) >= 0;
    }
} );

var _prum = [['id',
    '54fdb531abe53d014cfbfea5'],
    ['mark',
        'firstbyte',
        (new Date()).getTime()]];
(function () {
    var s = document.getElementsByTagName( 'script' )[0]
            , p = document.createElement( 'script' );
    p.async = 'async';
    p.src = '//rum-static.pingdom.net/prum.min.js';
    s.parentNode.insertBefore( p, s );
})();
