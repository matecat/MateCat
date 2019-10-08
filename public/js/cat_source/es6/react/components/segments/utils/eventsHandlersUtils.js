

const EnventHandlers =  {
    handleCopyEvent: function ( e ) {
        let elem = $(e.target);
        let cloneTag, text;
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            let tag = (elem.hasClass('inside-attribute')) ? elem.parent('span.locked') : elem.parent().parent('span.locked');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = cloneTag.text();
            e.clipboardData.setData('text/plain', text.trim());
            e.preventDefault();
        } else if (elem.hasClass('locked')) {
            cloneTag = elem.clone();
            cloneTag.find('.inside-attribute').remove();
            text = htmlEncode(cloneTag.text());
            e.clipboardData.setData('text/plain', text.trim());
            e.clipboardData.setData('text/html', text.trim());
            e.preventDefault();
        }
    },
    handleDragEvent: function ( e ) {
        let elem = $(e.target);
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            let tag = elem.closest('span.locked:not(.inside-attribute)');
            let cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            let text = htmlEncode(cloneTag.text());
            e.dataTransfer.setData('text/plain', TagUtils.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', TagUtils.transformTextForLockTags(text).trim());
        } else if (elem.hasClass('locked')) {
            let text = htmlEncode(elem.text());
            e.dataTransfer.setData('text/plain', TagUtils.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', TagUtils.transformTextForLockTags(text).trim());
        }
    },
};

module.exports = EnventHandlers;
