APP = null;

APP = {
    init: function() {
//        this.waitingConfirm = false;
//        this.confirmValue = null;
        $("body").on('click', '.modal .x-popup', function(e) {
            e.preventDefault();
            APP.closePopup();
        }).on('click', '.modal[data-type=alert] .btn-ok', function(e) {
            e.preventDefault();
            APP.closePopup();
        }).on('click', '.modal[data-type=confirm] .btn-ok', function(e) {
            e.preventDefault();
            APP.closePopup();
            if($(this).attr('data-callback')) {
                UI[$(this).attr('data-callback')]();
            };
//            APP.confirmValue = true;
//            APP.waitingConfirm = false;
        }).on('click', '.modal[data-type=confirm] .btn-cancel, .modal[data-type=confirm] .x-popup', function(e) {
            e.preventDefault();
            APP.closePopup();
            el = $(this).parents('.modal').find('.btn-cancel');
            if($(el).attr('data-callback')) {
                UI[$(el).attr('data-callback')]();
            };
//            APP.confirmValue = false;
//            APP.cancelValue = false;
//            APP.waitingConfirm = false;
        }).on('click', '.popup-outer.closeClickingOutside', function(e) {
            e.preventDefault();
            APP.closePopup();
        })
    },
    alert: function(msg) {
        this.popup({
            type: 'alert',
            closeClickingOutside: true,
            title: 'Warning',
            content: msg
        });
    },
    confirm: function(options) {
        this.waitingConfirm = true;
        this.popup({
            type: 'confirm',
            name: options.name,
            onConfirm: options.callback,
            onCancel: options.onCancel,
            title: 'Confirmation required',
            cancelTxt: options.cancelTxt,
            okTxt: options.okTxt,
            content: options.msg
        });
        this.checkConfirmation();
    },
    checkConfirmation: function() {
//        if(this.waitingConfirm) {
//            setTimeout(function() {
//                APP.checkConfirmation();
//            }, 200);            
//        } else {
//            console.log('this.confirmCallbackFunction: ' + this.confirmCallbackFunction);
//            console.log('this.cancelCallbackFunction: ' + this.cancelCallbackFunction);
//            if(this.confirmCallbackFunction) {
//                UI[this.confirmCallbackFunction](this.confirmValue);
//                this.confirmValue = null;
//                this.confirmCallbackFunction = null;
//            }
//            if(this.cancelCallbackFunction) {
//                UI[this.cancelCallbackFunction](this.cancelValue);
//                this.cancelValue = null;
//                this.cancelCallbackFunction = null;
//            }
//        }
    },
    doRequest: function(req,log) {
        logTxt = (typeof log == 'undefined')? '' : '&type=' + log;
        var setup = {
            url: config.basepath + '?action=' + req.data.action + logTxt + this.appendTime(),
            data: req.data,
            type: 'POST',
            dataType: 'json'
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
        $.ajax(setup);        
    }, 
    appendTime: function() {
        var t = new Date();
        return '&time=' + t.getTime();
    },
    popup: function(conf) {

/*
        // 
        {
            type: '', // ? (optional) alert|confirm|view:  set the popup as an alert box, a confirm box, a view (the markup is already loaded in the page, it is only showed/hidden), or if not specified as a custom popup
            closeClickingOutside: false, // (optional) default is false
            width: '30%', // (optional) default is 500px in the css rule
            title: '', // (optional)
            onConfirm: 'functionName' // (optional) UI function to call after confirmation. Confirm value is anyway stored in APP.confirmValue, but a UI function can be automatically called when the user confirm true or false (checks are done every 0.2 seconds)
            content: '', // html
            buttons:    [ // (optional) list from left
                                {
                                    type: '', // "ok" (default) or "cancel"
                                    text: '', 
                                    callback: '', // name of a UI function to execute
                                    params: '' // (optional) parameters to pass at the callback function
                                },
                                ...                        
                        ]
        }
 */
        this.closePopup();

        newPopup = '<div class="modal" data-name="' + ((conf.name)? conf.name : '') + '"' + ((conf.type == 'alert')? ' data-type="alert"' : (conf.type == 'confirm')? ' data-type="confirm"' : '') + '>' +
                    '   <div class="popup-outer"></div>' +
                    '   <div class="popup' + ((conf.type == 'alert')? ' popup-alert' : (conf.type == 'confirm')? ' popup-confirm' : '') + '">' +
                    '       <a href="#" class="x-popup remove"></a>' +
                    '       <h1>' + conf.title + '</h1>' +
                    '       <p>' + conf.content + '</p>';
        if(conf.type == 'alert') {
            newPopup += '<a href="#" class="btn-ok">ok<\a>';          
        } else if(conf.type == 'confirm') {
            newPopup +=     '<a href="#" class="btn-cancel"' + ((conf.onCancel)? ' data-callback="' + conf.onCancel + '"' : '') + '>' + ((conf.cancelTxt)? conf.cancelTxt : 'Cancel') + '<\a>' +          
                             '<a href="#" class="btn-ok"' + ((conf.onConfirm)? ' data-callback="' + conf.onConfirm + '"' : '') + '>' + ((conf.okTxt)? conf.okTxt : 'Ok') + '<\a>';    
            this.confirmCallbackFunction = (conf.onConfirm)? conf.onConfirm : null;
            this.cancelCallbackFunction = (conf.onCancel)? conf.onCancel : null;
        } else {
            $.each(conf.buttons, function(index) {
                var cl = (this.type == 'ok')? 'btn-ok' : (this.type == 'cancel')? 'btn-cancel' : '';
                newPopup += '<a href="#"' + ((this.callback)? ' onclick="UI.' + this.callback + '(\'' + ((this.params)? this.params : '') + '\'); return false;"' : '') + ' id="popup-button-' + index + '" class="' + cl + '">' + (this.text || 'ok') + '<\a>';
            });            
        }
        newPopup += '</div>';
        $('body').append(newPopup);
//        $('.modal:not([data-type=view])').show();
//        $('.popup').fadeIn('fast');
        if(conf.closeClickingOutside) $('.popup-outer').addClass('closeClickingOutside');
    },
    closePopup: function() {
        $('.modal[data-type=view]').hide();
        $('.modal:not([data-type=view])').remove();
//            $('.popup.hide, .popup-outer.hide').hide();
//            $('.popup:not(.hide), .popup-outer:not(.hide)').remove();
    },
    fitText: function(container,child,limitHeight) {
        if(container.height() < (limitHeight+1)) return;
        txt = child.text();
        var name = txt;
        var ext = '';
        if(txt.split('.').length > 1) {
            var extension = txt.split('.')[txt.split('.').length-1];
            name = txt.replace('.'+extension,'');
            ext = '.' + extension;
        }
        firstHalf = name.substr(0 , Math.ceil(name.length/2));
        secondHalf = name.replace(firstHalf,'');
        child.text(firstHalf.substr(0,firstHalf.length-1)+'[...]'+secondHalf.substr(1)+ext);
        while (container.height() > limitHeight) {
            child.text(child.text().replace(/(.)\[\.\.\.\](.)/,'[...]'));
        }
    }
};