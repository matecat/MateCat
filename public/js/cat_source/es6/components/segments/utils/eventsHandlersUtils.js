import TextUtils from '../../../utils/textUtils';

const EnventHandlers = {
    handleCopyEvent: function (e) {
        let elem = $(e.target);
        let cloneTag, text;
        if (elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute')) {
            let tag = elem.hasClass('inside-attribute')
                ? elem.parent('span.locked')
                : elem.parent().parent('span.locked');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = cloneTag.text();
            e.clipboardData.setData('text/plain', text.trim());
            e.preventDefault();
        } else if (elem.hasClass('locked')) {
            cloneTag = elem.clone();
            cloneTag.find('.inside-attribute').remove();
            text = TextUtils.htmlEncode(cloneTag.text());
            e.clipboardData.setData('text/plain', text.trim());
            e.clipboardData.setData('text/html', text.trim());
            e.preventDefault();
        }
    },
    handleDragEvent: function (e) {
        let elem = $(e.target);
        let cloneTag, text;
        if (elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute')) {
            let tag = elem.closest('span.locked:not(.inside-attribute)');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = TextUtils.htmlEncode(cloneTag.text());
            text = TagUtils.transformTextForLockTags(text).trim() + ' ';
            e.dataTransfer.clearData();
            e.dataTransfer.setData('text/plain', text);
            e.dataTransfer.setData('text/html', text);
            console.log('text dragged', text);
        } else if (elem.hasClass('locked')) {
            let tag = elem.closest('span.locked');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = TextUtils.htmlEncode(cloneTag.text());
            text = TagUtils.transformTextForLockTags(text).trim() + ' ';
            e.dataTransfer.clearData();
            e.dataTransfer.setData('text/plain', text);
            e.dataTransfer.setData('text/html', text);
            console.log('text dragged', text);
        }
    },
};

module.exports = EnventHandlers;
