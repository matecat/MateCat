
/*
 Component: functions
 */

function htmlEncode(value) {
    if (value) {
        return $('<div />').text(value).html();
    } else {
        return '';
    }
}

function htmlDecode(value) {
    if (value) {
        return $('<div />').html(value).text();
    } else {
        return '';
    }
}

function utf8_to_b64(str) { // currently unused
    return window.btoa(unescape(encodeURIComponent(str)));
}

function b64_to_utf8(str) { // currently unused
    return decodeURIComponent(escape(window.atob(str)));
}

function escapeRegExp(str) {
    return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}
