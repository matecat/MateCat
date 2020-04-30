const tagStruct = {
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
    }
};

export default tagStruct;
