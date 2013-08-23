APP = null;

APP = {
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
            type: '', // ?
            width: '30%', // (optional) default is 500px in the css rule
            title: '', // (optional)
            nearTitleContent: [ // (optional) list of items, from left
                                {
                                    content: '',
                                    callback: ''
                                },
                                ...
                            ],
            nearCloseContent: [ // (optional) list of items, from left
                                {
                                    content: '',
                                    callback: ''
                                },
                                ...
                            ],
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
        newPopup = '<div class="popup-outer"></div>' +
                    '<div class="popup">' +
                    '   <a href="#" class="x-popup"></a>' +
                    '   <h1>' + title + '</h1>' +
                    content;
        $.each(buttons, function() {

        });                    
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