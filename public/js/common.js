APP = null;

APP = {
    init: function () {
        this.setLoginEvents();
        if (config.isLoggedIn) {
            APP.teamStorageName = 'teamId-' + config.userMail;
            this.setTeamNameInMenu();
            this.setUserImage()
        }
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

            if ( $( '.modal[data-type='+dataType+']' ).hasClass( 'closeOnSuccess' ) ) APP.closePopup();
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
        } ).on('keyup', function(e) {
            if (e.keyCode == 27 && ($("body").hasClass("side-popup")) ) {
                APP.ModalWindow.onCloseModal();
                e.preventDefault();
                e.stopPropagation();
            }
        });

        this.checkGlobalMassages();


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
                ' <p class="text-container-top"></p>' +
                ' <p class="buttons-popup-container button-aligned-right">' +
                '</p>' +
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
                filled_tpl.find( 'p.text-container-top' ).html( options['content'] );
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
                        filled_tpl.find( '.popup .buttons-popup-container' )
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
                                .addClass('popup-confirm').find('.buttons-popup-container')
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
                        filled_tpl.find( '.popup .buttons-popup-container' ).append(
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
        if ( typeof escapeTextLen == 'undefined' ) escapeTextLen = 4;
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

        var loop = true;
        // break recursion for browser width resize below 1024 px to avoid infinite loop and stack overflow
        while ( container.height() >= limitHeight && loop == true ) {
            loop = this.fitText(container, child, limitHeight, escapeTextLen, actualTextLow, actualTextHi);
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
    numberWithCommas: function(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
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
        if (!APP.notificationBox) {
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
    removeParam: function(parameter)
    {
        var url=document.location.href;
        var urlparts= url.split('?');

        if (urlparts.length>=2)
        {
            var urlBase=urlparts.shift();
            var queryString=urlparts.join("?");

            var prefix = encodeURIComponent(parameter)+'=';
            var pars = queryString.split(/[&;]/g);
            for (var i= pars.length; i-->0;)
                if (pars[i].lastIndexOf(prefix, 0)!==-1)
                    pars.splice(i, 1);
            url = urlBase+'?'+pars.join('&');
            window.history.pushState('',document.title,url); // added this line to push the new url directly to url bar .

        }
        return url;
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
    },

    evalFlashMessagesForNotificationBox : function() {
        if ( config.flash_messages && Object.keys( config.flash_messages ).length ) {

            _.each(['warning', 'notice', 'error'], function( type ) {
                if ( config.flash_messages[ type ] ) {
                    _.each(config.flash_messages[ type ],function( obj ) {
                        APP.addNotification({
                            autoDismiss: false,
                            dismissable: true,
                            position : "bl",
                            text : obj.value,
                            title: type,
                            type : type,
                            allowHtml : true
                        });
                    });
                }
            });

        }
    },

    lookupFlashServiceParam : function( name ) {
        if ( config.flash_messages && config.flash_messages.service ) {
            return _.filter( config.flash_messages.service, function( service, index ) {
                return service.key == name ;
            });
        }
    },

    checkGlobalMassages: function () {
        var self = this;
        if (config.global_message) {
            var messages = JSON.parse(config.global_message);
            $.each(messages, function () {
                var elem = this;
                if (typeof $.cookie('msg-' + this.token) == 'undefined' && ( new Date(this.expire) > ( new Date() ) )) {
                    var notification = {
                        title: 'Notice',
                        text: this.msg,
                        type: 'warning',
                        autoDismiss: false,
                        position: "bl",
                        allowHtml: true,
                        closeCallback: function () {
                            var expireDate = new Date(elem.expire);
                            $.cookie('msg-' + elem.token, '', {expires: expireDate});
                        }
                    };
                    APP.addNotification(notification);
                    return false;
                }
            });
        }
    },

    checkEmail: function(text) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        if ( !re.test(text.trim()) ) {
            return false;
        }
        return true;
    },

    getUserShortName: function (user) {
        return (user.first_name[0] + user.last_name[0]).toUpperCase();
    },

    getLastTeamSelected: function (teams) {
        if (localStorage.getItem(this.teamStorageName)) {
            var lastId = localStorage.getItem(this.teamStorageName);
            var team = teams.find(function (t, i) {
                return parseInt(t.id) === parseInt(lastId);
            });
            if (team) {
                return team;
            } else {
                return teams[0];
            }
        } else {
            return teams[0];
        }
    },

    setTeamInStorage(teamId) {
        localStorage.setItem(this.teamStorageName, teamId);
    },

    downloadFile: function (idJob, pass, callback) {

        //create an iFrame element
        var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop({
            id:'iframeDownload',
            src: ''
        });

        //append iFrame to the DOM
        $("body").append( iFrameDownload );

        //generate a token download
        var downloadToken = new Date().getTime() + "_" + parseInt( Math.random( 0, 1 ) * 10000000 );

        //set event listner, on ready, attach an interval that check for finished download
        iFrameDownload.ready(function () {

            //create a GLOBAL setInterval so in anonymous function it can be disabled
            downloadTimer = window.setInterval(function () {

                //check for cookie
                var token = $.cookie( downloadToken );

                //if the cookie is found, download is completed
                //remove iframe an re-enable download button
                if ( typeof token != 'undefined' ) {
                    /*
                     * the token is a json and must be read with "parseJSON"
                     * in case of failure:
                     *      error_message = Object {code: -110, message: "Download failed.
                     *      Please contact the owner of this MateCat instance"}
                     *
                     * in case of success:
                     *      error_message = Object {code: 0, message: "Download Complete."}
                     *
                     */
                    tokenData = $.parseJSON(token);
                    if(parseInt(tokenData.code) < 0) {
                        var notification = {
                            title: 'Error',
                            text: 'Download failed. Please, fix any tag issues and try again in 5 minutes. If it still fails, please, contact support@matecat.com',
                            type: 'error'
                        };
                        APP.addNotification(notification);
                        // UI.showMessage({msg: tokenData.message})
                    }
                    if (callback) {
                        callback();
                    }

                    window.clearInterval( downloadTimer );
                    $.cookie( downloadToken, null, { path: '/', expires: -1 });
                    iFrameDownload.remove();
                }

            }, 2000);

        });

        //clone the html form and append a token for download
        // var iFrameForm = $("#fileDownload").clone().append(
        //     $( document.createElement( 'input' ) ).prop({
        //         type:'hidden',
        //         name:'downloadToken',
        //         value: downloadToken
        //     })
        // );

        var iFrameForm = $('<form id="fileDownload" action="'+ config.basepath +'" method="post">' +
                '<input type="hidden" name="action" value="downloadFile" />' +
                '<input type="hidden" name="id_job" value="'+ idJob +'" />' +
                '<input type="hidden" name="id_file" value="" />' +
                '<input type="hidden" name="password" value="'+ pass +'"/>' +
                '<input type="hidden" name="download_type" value="all" />' +
                '<input type="hidden" name="downloadToken" value="'+ downloadToken +'" />' +
            '</form>');

        //append from to newly created iFrame and submit form post
        iFrameDownload.contents().find('body').append( iFrameForm );
        iFrameDownload.contents().find("#fileDownload").submit();

    },

    downloadGDriveFile: function (openOriginalFiles, jobId, pass ,callback) {

        if (typeof openOriginalFiles === 'undefined') {
            openOriginalFiles = 0;
        }

        // TODO: this should be relative to the current USER, find a
        // way to generate this at runtime.
        //
        /*if( !config.isGDriveProject || config.isGDriveProject == 'false' ) {
         UI.showDownloadCornerTip();
         }*/

        if ( typeof window.googleDriveWindows == 'undefined' ) {
            window.googleDriveWindows = {};
        }

        var winName ;

        var driveUpdateDone = function(data) {
            if( !data.urls || data.urls.length === 0 ) {
                APP.alert({msg: "MateCat was not able to update project files on Google Drive. Maybe the project owner revoked privileges to access those files. Ask the project owner to login again and grant Google Drive privileges to MateCat."});

                return;
            }

            var winName ;

            $.each( data.urls, function(index, item) {
                winName = 'window' + item.localId ;

                if ( typeof window.googleDriveWindows[ winName ] != 'undefined' && window.googleDriveWindows[ winName ].opener != null ) {
                    window.googleDriveWindows[ winName ].location.href = item.alternateLink ;
                    window.googleDriveWindows[ winName ].focus();
                } else {
                    window.googleDriveWindows[ winName ] = window.open( item.alternateLink );
                }
            });
        };

        $.ajax({
            cache: false,
            url: APP.downloadFileURL( openOriginalFiles, jobId, pass ),
            dataType: 'json'
        })
            .done( driveUpdateDone )
            .always(function() {
                if (callback){
                    callback();
                }
            });
    },

    downloadFileURL : function( openOriginalFiles, idJob, pass ) {
        return sprintf( '%s?action=downloadFile&id_job=%s&password=%s&original=%s',
            config.basepath,
            idJob,
            pass,
            openOriginalFiles
        );
    },

    setTeamNameInMenu: function () {
        if (APP.USER.STORE.teams) {
            var team = this.getLastTeamSelected(APP.USER.STORE.teams);
            $('.user-menu-container .organization-name').text(team.name);
        } else {
            setTimeout(this.setTeamNameInMenu.bind(this), 500);
        }
    },

    setUserImage: function () {
        if (APP.USER.STORE.user) {
            if (!APP.USER.STORE.metadata) return;
            var urlImage = APP.USER.STORE.metadata.gplus_picture;
            var html = '<img class="ui-user-top-image-general user-menu-preferences" src="' + urlImage + '"/>';
            $('.user-menu-container .user-menu-preferences').replaceWith(html);
            $('.user-menu-preferences').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $('#modal').trigger('openpreferences');
                return false;
            });
        } else {
            setTimeout(this.setUserImage.bind(this), 500);
        }
    },

    fromDateToString: function (date) {
        var dd = new Date( date );
        return {
            day: $.format.date(dd, "d") ,
            month: $.format.date(dd, "MMMM"),
            year: $.format.date(dd, "yy"),
            time: $.format.date(dd, "hh") + ":" + $.format.date(dd, "mm") + " " + $.format.date(dd, "a"),
        };
    },

    getGMTDate: function (date, timeZoneFrom) {
        if (typeof date === "string" && date.indexOf("-") > -1) {
            date = date.replace(/-/g, "/");
        }
        var timezoneToShow = APP.readCookie( "matecat_timezone" );
        if ( timezoneToShow == "" ) {
            timezoneToShow = -1 * ( new Date().getTimezoneOffset() / 60 );
        }
        var dd = new Date( date );
        var timeZoneFrom = (timeZoneFrom) ? timeZoneFrom : (-1 * ( new Date().getTimezoneOffset() / 60) ); //TODO UTC0 ? Why the browser gmt
        dd.setMinutes( dd.getMinutes() + (timezoneToShow - timeZoneFrom) * 60 );
        var timeZone = this.getGMTZoneString();
        return {
            day: $.format.date(dd, "d") ,
            month: $.format.date(dd, "MMMM"),
            time: $.format.date(dd, "hh") + ":" + $.format.date(dd, "mm") + " " + $.format.date(dd, "a"),
            time2: $.format.date(dd, "HH") + ":" + $.format.date(dd, "mm"),
            year: $.format.date(dd, "yyyy"),
            gmt: timeZone
        };
    },

    getGMTZoneString: function () {
        // var timezoneToShow = "";
        var timezoneToShow = APP.readCookie( "matecat_timezone" );
        if ( timezoneToShow == "" ) {
            timezoneToShow = -1 * ( new Date().getTimezoneOffset() / 60 );
        }
        timezoneToShow = (timezoneToShow > 0) ? '+' + timezoneToShow : timezoneToShow;
        return (timezoneToShow % 1 === 0) ? "GMT " + timezoneToShow + ':00' : "GMT " + parseInt(timezoneToShow) + ':30';

    },

    getDefaultTimeZone: function () {
        var timezoneToShow = APP.readCookie( "matecat_timezone" );
        if ( timezoneToShow == "" ) {
            timezoneToShow = -1 * ( new Date().getTimezoneOffset() / 60 );
        }
        return timezoneToShow;
    },

    readCookie: function( cookieName ) {
        cookieName += "=";
        var cookies = document.cookie.split(';');

        for ( var i = 0; i < cookies.length; i++ ) {
            var cookie = cookies[i].trim();

            if ( cookie.indexOf( cookieName ) == 0 )
                return cookie.substring( cookieName.length, cookie.length );
        }
        return "";
    },


    setCookie: function( cookieName, cookieValue, expiration ) {
        if( typeof expiration == "undefined" ) {
            expiration = new Date();
            expiration.setYear(new Date().getFullYear() + 1);
        }
        document.cookie = cookieName + "=" + cookieValue + "; expires=" + expiration.toUTCString() + "; path=/";
    }


};

$(document).ready(function(){
    APP.init();
});

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


