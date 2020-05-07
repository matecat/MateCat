/**
 *
 * @param segmentString
 * @returns {*}
 */
export const cleanSegmentString = (segmentString) => {
    const regExp = getXliffRegExpression();
    return segmentString.replace(regExp, '');
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
        .replace(/&apos;/g,'\'')
        .replace(/&quot;/g,'\"');
};

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTMLLeaveTags = (escapedHTML) => {
    return escapedHTML
        .replace(/&amp;/g,'&')
        .replace(/&apos;/g,'\'')
        .replace(/&quot;/g,'\"');
};
