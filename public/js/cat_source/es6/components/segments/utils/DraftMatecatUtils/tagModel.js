/*
'tagName': {
        type: 'tagName',
        openRegex: the regex to find the opening of the tag, e.g. <g,
        openLength: the number of characters of the open string,
        closeRegex: the regex to find the closing of the tag, e.g. />,
        selfClosing: true if tag don't has a closing tag, like </g> for <g>
        isClosure: True if tag is a closure of another tag like </g>,
        placeholder: the string to display instead of encoded tag,
        placeholderRegex: the regex to find equiv-text content inside the encoded tag
        decodeNeeded: True if equiv-text need decoding
    },
 */

const tagSignatures = {
    'ph': {
        type: 'ph',
        regex: /&lt;ph.*?id=".*?".*?equiv-text="base64:(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;ph.*?id="(?:.*?)".*?equiv-text="base64:(.*?)".*?&gt;/,
        decodeNeeded: true,
        errorCheckAvailable: true,
        lexiqaAvailable: true,
        style: 'tag-selfclosed'
    },
    'g': {
        type: 'g',
        regex: /&lt;g.*?id="(.*?)".*?&gt;/gi,
        selfClosing: false,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;g.*?id=".*?(\d+).*?".*?&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag-open'
    },
    'gCl': {
        type: 'gCl',
        regex: /&lt;(\/g)&gt;/gi,
        selfClosing: false,
        isClosure: true,
        placeholder: '</g>',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag-close'
    },
    'bx': {
        type: 'bx',
        regex: /&lt;bx.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;bx.*?id=".*?(\d+).*?".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag-selfclosed'
    },
    'ex': {
        type: 'ex',
        regex: /&lt;ex.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;ex.*?id=".*?(\d+).*?".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag-selfclosed'
    },
    'x': {
        type: 'x',
        regex: /&lt;x.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;x.*?id=".*?(\d+).*?".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag-selfclosed'
    },
    'nbsp':{
        type: 'nbsp',
        regex: /##\$(_A0)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '\u00B0', //'°',
        encodedPlaceholder: '##$_A0$##',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag-selfclosed tag-nbsp'
    },
    'tab':{
        type: 'tab',
        regex: /##\$(_09)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '\u21E5', //'⇥'
        encodedPlaceholder: '##$_09$##',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag-selfclosed tag-tab'
    },
    'carriageReturn':{
        type: 'carriageReturn',
        regex: /##\$(_0D)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\r',
        encodedPlaceholder: '##$_0D$##',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag-selfclosed tag-cr'
    },
    'lineFeed':{
        type: 'lineFeed',
        regex: /##\$(_0A)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\n',
        encodedPlaceholder: '##$_0A$##',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag-selfclosed tag-lf'
    }
};

function TagStruct(offset, length, type) {
    this.offset = offset || -1;
    this.length = length || 0;
    this.type = type || null;
    this.mutability = 'IMMUTABLE';
    this.data = {
        id: null,
        encodedText: null,
        decodedText: null,
        openTagId: null,
        closeTagId:null,
        openTagKey: null,
        closeTagKey: null,
        placeholder: null,
        originalOffset: -1
    }
}

const getSplitBlockTag = () => {
    return ['lineFeed', 'carriageReturn']
};

const getBuildableTag = () => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return tagSignatures[tagKey].encodedPlaceholder}).
    map(tagKey => {return tagSignatures[tagKey].type})
};

// Control params: errorCheckAvailable
const getErrorCheckTag = () => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return tagSignatures[tagKey].errorCheckAvailable}).
    map(tagKey => {return tagSignatures[tagKey].type})
};

// Control params: lexiqaAvailable
const getNoLexiqaTag = () => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return !tagSignatures[tagKey].lexiqaAvailable}).
    map(tagKey => {return tagSignatures[tagKey].type})
};

export {tagSignatures, TagStruct, getErrorCheckTag, getNoLexiqaTag, getBuildableTag, getSplitBlockTag};
