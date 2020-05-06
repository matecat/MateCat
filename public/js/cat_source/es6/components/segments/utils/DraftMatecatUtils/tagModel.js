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
        openRegex: /&lt;ph/g,
        openLength: 6,
        closeRegex: /(\/&gt;)/, // '/>'
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /equiv-text="base64:(.+)"/,
        decodeNeeded: true
    },
    'g': {
        type: 'g',
        openRegex: /&lt;g/g,
        openLength: 5,
        closeRegex: /(&gt;)/, // '>'
        selfClosing: false,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /(id="\w+")/,
        decodeNeeded: false
    },
    'cl': {
        type: 'cl',
        openRegex: /&lt;\/g&gt;/g,
        openLength: 10,
        closeRegex: null,
        selfClosing: false,
        isClosure: true,
        placeholder: '<g/>',
        placeholderRegex: null,
        decodeNeeded: false
    },
    'nbsp':{
        type: 'nbsp',
        openRegex: /##\$(_A0)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '°',
        placeholderRegex: null,
        decodeNeeded: false
    },
    'tab':{
        type: 'tab',
        openRegex: /##\$(_09)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '#',
        placeholderRegex: null,
        decodeNeeded: false
    },
    'lineFeed':{
        type: 'lineFeed',
        openRegex: /##\$(_0D)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\n',
        placeholderRegex: null,
        decodeNeeded: false
    },
    'carriageReturn':{
        type: 'carriageReturn',
        openRegex: /##\$(_0A)\$##/g,
        openLength: 9,
        closeRegex: null,
        selfClosing: true,
        isClosure: false,
        placeholder: '\\r',
        placeholderRegex: null,
        decodeNeeded: false
    }
};

function TagStruct(offset, length, type) {
    this.offset = offset || -1;
    this.length = length || 0;
    this.type = type || null;
    this.mutability = 'IMMUTABLE';
    this.data = {
        encodedText: null,
        decodedText: null,
        openTagId: null,
        closeTagId:null,
        openTagKey: null,
        closeTagKey: null,
        placeholder: null
    }
}

export {tagSignatures, TagStruct};
