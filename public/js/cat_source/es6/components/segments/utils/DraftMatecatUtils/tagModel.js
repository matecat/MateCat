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
    'phUber': {
        type: 'phUber',
        regex: /&lt;ph\sid="((?:(?!&gt;).)+?)"\sdataRef="((?:(?!&gt;).)+?)"\/&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;ph\sid="(?:(?:(?!&gt;).)+?)"\sdataRef="((?:(?!&gt;).)+?)"\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-ph',
        showTooltip: true
    },
    'ph': {
        type: 'ph',
        regex: /&lt;ph\sid="((?:(?!&gt;).)+?)"\sequiv-text="base64:((?:(?!&gt;).)+?)"\/&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;ph\sid="(?:(?:(?!&gt;).)+?)"\sequiv-text="base64:((?:(?!&gt;).)+?)"\/&gt;/,
        decodeNeeded: true,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-ph',
        showTooltip: true
    },
    'g': {
        type: 'g',
        regex: /&lt;g.*?id="(.*?)".*?&gt;/gi,
        selfClosing: false,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;g.*?id="(.*?)".*?&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-open',
        styleRTL: 'tag-close',
        showTooltip: false
    },
    'gCl': {
        type: 'gCl',
        regex: /&lt;(\/g)&gt;/gi,
        selfClosing: false,
        isClosure: true,
        placeholder: '</>',
        placeholderRegex: null,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-close',
        styleRTL: 'tag-open',
        showTooltip: false
    },
    'bx': {
        type: 'bx',
        regex: /&lt;bx.*?id="(.*?)".*?\/&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;bx.*?id="(.*?)".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-selfclosed',
        showTooltip: false
    },
    'ex': {
        type: 'ex',
        regex: /&lt;ex.*?id="(.*?)".*?\/&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;ex.*?id="(.*?)".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-selfclosed',
        showTooltip: false
    },
    'x': {
        type: 'x',
        regex: /&lt;x.*?id="(.*?)".*?&gt;/gi,
        selfClosing: true,
        isClosure: false,
        placeholder: null,
        placeholderRegex: /&lt;x.*?id="(.*?)".*?\/&gt;/,
        decodeNeeded: false,
        errorCheckAvailable: true,
        lexiqaAvailable: false,
        glossaryAvailable: false,
        style: 'tag-selfclosed',
        showTooltip: false
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
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-nbsp',
        showTooltip: false
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
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-tab',
        showTooltip: false
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
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-cr',
        showTooltip: false
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
        glossaryAvailable: false,
        style: 'tag-selfclosed tag-lf',
        showTooltip: false
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

// Control params: glossaryAvailable
const getNoGlossaryTag = () => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return !tagSignatures[tagKey].glossaryAvailable}).
    map(tagKey => {return tagSignatures[tagKey].type})
};

// Control params: showTooltip
const getTooltipTag = () => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return tagSignatures[tagKey].showTooltip}).
    map(tagKey => {return tagSignatures[tagKey].type})
};

const getStyleForType = (type) => {
    return Object.keys(tagSignatures).
    filter(tagKey =>{return tagSignatures[tagKey].type === type}).
    map(tagKey => {return tagSignatures[tagKey].style})
}

export {tagSignatures,
    TagStruct,
    getErrorCheckTag,
    getNoLexiqaTag,
    getNoGlossaryTag,
    getBuildableTag,
    getSplitBlockTag,
    getTooltipTag,
    getStyleForType};
