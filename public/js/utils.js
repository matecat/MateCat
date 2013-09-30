function fitText(container,child,limitHeight){
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

function popup(conf){
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
}