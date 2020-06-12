/**
 *
 * @param segmentString
 * @returns {*}
 */
export const cleanSegmentString = (segmentString) => {
    const regExp = getXliffRegExpression();
    if(segmentString){
        return segmentString.replace(regExp, '');
    }
    return segmentString;
};

/**
 *
 * @returns {RegExp}
 */
export const getXliffRegExpression = () => {
    return /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gmi; // group, multiline, case-insensitive
};

export const getIdAttributeRegEx = () => {
    return /id="(mtc_\d+|\d+)"/g;
}


/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTML = (escapedHTML) => {
    return escapedHTML
        .replace(/&lt;/g,'<')
        .replace(/&gt;/g,'>')
        .replace(/&amp;/g,'&')
        .replace(/&nbsp;/g,' ')
        .replace(/&apos;/g,'\'')
        .replace(/&quot;/g,'\"');
};

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTMLLeaveTags = (escapedHTML) => {
    if(escapedHTML){
        return escapedHTML
            .replace(/&amp;/g,'&')
            .replace(/&nbsp;/g,' ')
            .replace(/&apos;/g,'\'')
            .replace(/&quot;/g,'\"');
    }
    return escapedHTML;

};

export const decodePhTags = (text) => {
    if(text){
        return text.replace( /&lt;ph.*?equiv-text="base64:(.*?)"\/&gt;/gi , (match, text) => {
            return Base64.decode(text);
        });
    }
    return '';
};

export const formatText = (text, format) => {
    switch (format) {
        case 'uppercase':
            text = text.toUpperCase();
            break;
        case 'lowercase':
            text = text.toLowerCase();
            break;
        case 'capitalize':
            text = text.charAt(0).toUpperCase() + text.substr(1).toLowerCase();
            break;
        default:
            break;
    }
    return text;
};
