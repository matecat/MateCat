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
        regex: /&lt;ph.*?id="(.*?)".*?equiv-text="base64:(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /equiv-text="base64:(.*?)"/,
        decodeNeeded: true,
        errorCheckAvailable: true,
        lexiqaAvailable: true,
        style: 'tag tag-selfclosed'
    },
    'g': {
        type: 'g',
        regex: /&lt;g.*?id="(.*?)".*?&gt;/gi,
        selfClosing: false,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /id="(\d+)"/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag tag-open'
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
        style: 'tag tag-close'
    },
    'bx': {
        type: 'bx',
        regex: /&lt;bx.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /id="(\d+)"/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag tag-selfclosed'
    },
    'ex': {
        type: 'ex',
        regex: /&lt;ex.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /id="(\d+)"/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag tag-selfclosed'
    },
    'x': {
        type: 'x',
        regex: /&lt;x.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /id="(\d+)"/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        style: 'tag tag-selfclosed'
    },
    'nbsp':{
        type: 'nbsp',
        regex: /##\$(_A0)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '°',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag tag-selfclosed'
    },
    'tab':{
        type: 'tab',
        regex: /##\$(_09)\$##/g,
        selfClosing: true,
        isClosure: false,
        placeholder: '#',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag tag-selfclosed'
    },
    'carriageReturn':{
        type: 'carriageReturn',
        regex: /##\$(_0D)\$##/g,
        openRegex: /##\$(_0D)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\r',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag tag-selfclosed'
    },
    'lineFeed':{
        type: 'lineFeed',
        regex: /##\$(_0A)\$##/g,
        openRegex: /##\$(_0A)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\n',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: false,
        lexiqaAvailable: true,
        style: 'tag tag-selfclosed'
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

export {tagSignatures, TagStruct, getErrorCheckTag, getNoLexiqaTag};
