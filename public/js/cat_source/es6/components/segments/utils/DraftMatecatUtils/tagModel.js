/*
'tagName': {
        type: 'tagName',
        openRegex: the regex to find the opening of the tag, e.g. <g,
        openLength: the number of characters of the open string,
        closeRegex: the regex to find the closing of the tag, e.g. />,
        selfClosing: true if tag don't has a closing tag, like </g> for <g>
        isClosure: True if tag is a closure of another tag like </g>,
        placeholder: the string to display instead of encoded tag,
        placeholderRegex: the regex to find equiv-text content inside the encoded tag. MUST be the first capturing group.
        decodeNeeded: True if equiv-text need decoding
    },
 */

const tagSignatures = {
  // ph1: {
  //   type: 'ph',
  //   regex: /&lt;ph.*?id="((?:(?!dataRef|equiv-text|subType|type|&gt).)+?)"\s(dataRef=")*((?:(?!equiv-text|&gt;).)+?)"\sequiv-text="base64:((?:(?!&gt;).)+?)"\/&gt;/gi,
  //   selfClosing: true,
  //   isClosure: false,
  //   placeholder: null,
  //   placeholderRegex: /&lt;ph.*?id="(?:(?:(?!dataRef|equiv-text|subType|type|&gt).)+?)"\s(?:(?:(?!equiv-text|&gt;).)+?)"\sequiv-text="base64:((?:(?!&gt;).)+?)"\/&gt;/,
  //   decodeNeeded: true,
  //   errorCheckAvailable: true,
  //   lexiqaAvailable: false,
  //   glossaryAvailable: false,
  //   style: 'tag-selfclosed tag-ph',
  //   showTooltip: true,
  //   replaceForLexiqa: false,
  // },
  // ph2: {
  //   type: 'ph',
  //   regex: /&lt;ph\sid="((?:(?!&gt;|equiv-text|dataRef).)+?)"\sdataRef="((?:(?!&gt;|equiv-text).)+?)"\/&gt;/gi,
  //   selfClosing: true,
  //   isClosure: false,
  //   placeholder: null,
  //   placeholderRegex: /&lt;ph\sid="(?:(?:(?!dataRef|equiv-text|&gt;).)+?)"\sdataRef="((?:(?!&gt;|equiv-text).)+?)"\/&gt;/,
  //   decodeNeeded: false,
  //   errorCheckAvailable: true,
  //   lexiqaAvailable: false,
  //   glossaryAvailable: false,
  //   style: 'tag-selfclosed tag-ph',
  //   showTooltip: true,
  //   replaceForLexiqa: false,
  // },
  ph: {
    type: 'ph',
    regex:
      /&lt;ph(?:(?:(?!id).)*?)id="(?:[^"].*?)"(?:(?:(?!equiv-text).)*?)equiv-text="base64:((?:(?!&gt;).)*?)"\/&gt;/gi,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex:
      /&lt;ph(?:(?:(?!id).)*?)id="(?:[^"].*?)"(?:(?:(?!equiv-text).)*?)equiv-text="base64:((?:(?!&gt;).)*?)"\/&gt;/,
    decodeNeeded: true,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed tag-ph',
    showTooltip: true,
    replaceForLexiqa: false,
  },
  g: {
    type: 'g',
    regex: /&lt;g[^&]*id="(.*?)".*?&gt;/g,
    selfClosing: false,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /&lt;g .*?\bid="(.*?)".*?&gt;/,
    decodeNeeded: false,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-open',
    styleRTL: 'tag-close',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  gCl: {
    type: 'g',
    regex: /&lt;(\/g)&gt;/g,
    selfClosing: false,
    isClosure: true,
    placeholder: '</g>',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-close',
    styleRTL: 'tag-open',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  bx: {
    type: 'bx',
    regex: /&lt;bx[^&]*id="(.*?)".*?\/&gt;/g,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /&lt;bx .*?id="(.*?)".*?\/&gt;/,
    decodeNeeded: false,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  ex: {
    type: 'ex',
    regex: /&lt;ex[^&]*id="(.*?)".*?\/&gt;/g,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /&lt;ex .*?id="(.*?)".*?\/&gt;/,
    decodeNeeded: false,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  x: {
    type: 'x',
    regex: /&lt;x[^&]*id="(.*?)".*?&gt;/gi,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /&lt;x .*?id="(.*?)".*?\/&gt;/,
    decodeNeeded: false,
    errorCheckAvailable: true,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  nbsp: {
    type: 'nbsp',
    regex: /##\$(_A0)\$##/g,
    selfClosing: true,
    isClosure: false,
    placeholder: '\u00B0', //'°',
    encodedPlaceholder: '##$_A0$##',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: false,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed tag-nbsp',
    showTooltip: false,
    replaceForLexiqa: true,
    lexiqaText: ' ',
  },
  tab: {
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
    showTooltip: false,
    replaceForLexiqa: false,
  },
  carriageReturn: {
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
    showTooltip: false,
    replaceForLexiqa: false,
  },
  lineFeed: {
    type: 'lineFeed',
    regex: /##\$(_0A)\$##/g,
    selfClosing: true,
    isClosure: false,
    placeholder: '\n',
    encodedPlaceholder: '##$_0A$##',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: false,
    lexiqaAvailable: true,
    glossaryAvailable: false,
    style: 'tag-selfclosed tag-lf',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  splitPoint: {
    type: 'splitpoint',
    regex: /##\$_(SPLIT)\$##/g,
    selfClosing: true,
    isClosure: false,
    placeholder: '\uf03d', //'\u21F9', //⇹ content: "\f03d";
    encodedPlaceholder: '##$_SPLIT$##',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: false,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-split',
    showTooltip: false,
    replaceForLexiqa: false,
  },
  wordJoiner: {
    type: 'wordJoiner',
    regex: /\u2060/g,
    selfClosing: true,
    isClosure: false,
    placeholder: '\u2060',
    encodedPlaceholder: '⁠',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: false,
    lexiqaAvailable: false,
    glossaryAvailable: false,
    style: 'tag-selfclosed tag-word-joiner',
    showTooltip: false,
    replaceForLexiqa: true,
    lexiqaText: ' ',
  },
}

function TagStruct(offset = -1, length = 0, type = null, name = null) {
  this.offset = offset
  this.length = length
  this.type = type
  this.mutability = 'IMMUTABLE'
  this.data = {
    id: null,
    name: name,
    encodedText: null,
    decodedText: null,
    openTagId: null,
    closeTagId: null,
    openTagKey: null,
    closeTagKey: null,
    placeholder: null,
    originalOffset: -1,
  }
}

const getSplitBlockTag = () => {
  return ['lineFeed', 'carriageReturn']
}

const getSplitPointTag = () => {
  return ['splitPoint']
}

const getBuildableTag = () => {
  return Object.keys(tagSignatures).filter(
    (tagKey) => tagSignatures[tagKey].encodedPlaceholder,
  )
}

// Control params: errorCheckAvailable
const getErrorCheckTag = () => {
  return Object.keys(tagSignatures).filter(
    (tagKey) => tagSignatures[tagKey].errorCheckAvailable,
  )
}

// Control params: lexiqaAvailable
const getNoLexiqaTag = () => {
  return Object.keys(tagSignatures).filter(
    (tagKey) => !tagSignatures[tagKey].lexiqaAvailable,
  )
}

// Control params: glossaryAvailable
const getNoGlossaryTag = () => {
  return Object.keys(tagSignatures).filter(
    (tagKey) => !tagSignatures[tagKey].glossaryAvailable,
  )
}

// Control params: showTooltip
const getTooltipTag = () => {
  return Object.keys(tagSignatures).filter(
    (tagKey) => tagSignatures[tagKey].showTooltip,
  )
}

const getStyleForName = (tagName) => {
  return Object.keys(tagSignatures)
    .filter((tagKey) => tagKey === tagName)
    .map((tagKey) => tagSignatures[tagKey].style)
}

const getCorrectClosureTag = (tagType) => {
  return Object.keys(tagSignatures).filter((tagKey) => {
    return (
      tagSignatures[tagKey].isClosure && tagSignatures[tagKey].type === tagType
    )
  })
}

const getCorrectTag = (tagType, isClosure = false) => {
  return Object.keys(tagSignatures)
    .filter((tagKey) => {
      return (
        tagSignatures[tagKey].isClosure === isClosure &&
        tagSignatures[tagKey].type === tagType
      )
    })
    .join()
}

const isToReplaceForLexiqa = (tagType, isClosure = false) => {
  return (
    tagSignatures[tagType].isClosure === isClosure &&
    tagSignatures[tagType].type === tagType &&
    tagSignatures[tagType].replaceForLexiqa
  )
}

const getXliffRegExpression = () => {
  return /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*((?!&lt;|<).)*?&gt;)/gim
}

const getTagSignature = (tagType) => {
  return tagSignatures[tagType]
}

export {
  tagSignatures,
  TagStruct,
  getErrorCheckTag,
  getNoLexiqaTag,
  getNoGlossaryTag,
  getBuildableTag,
  getSplitBlockTag,
  getTooltipTag,
  getStyleForName,
  getCorrectClosureTag,
  getCorrectTag,
  getSplitPointTag,
  getXliffRegExpression,
  isToReplaceForLexiqa,
  getTagSignature,
}
