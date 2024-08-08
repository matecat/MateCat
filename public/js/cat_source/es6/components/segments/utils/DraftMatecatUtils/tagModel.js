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

const tagSignaturesMap = {
  ph: {
    type: 'ph',
    regex: /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/g,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/,
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
    regex: /<g\b[^>]+?id="([^"]+)"[^>]*?>/g,
    selfClosing: false,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /<g\b[^>]+?id="([^"]+)"[^>]*?>/,
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
    regex: /<\/g>/g,
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
    regex: /<bx\b[^>]+?id="([^"]+)"[^>]*?\/>/g,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /<bx\b[^>]+?id="([^"]+)"[^>]*?\/>/,
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
    regex: /<ex\b[^>]+?id="([^"]+)"[^>]*?\/>/g,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /<ex\b[^>]+?id="([^"]+)"[^>]*?\/>/,
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
    regex: /<x\b[^>]+?id="([^"]+)"[^>]*?\/>/gi,
    selfClosing: true,
    isClosure: false,
    placeholder: null,
    placeholderRegex: /<x\b[^>]+?id="([^"]+)"[^>]*?\/>/,
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
    replaceForLexiqa: true,
    lexiqaText: ' ',
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
    replaceForLexiqa: true,
    lexiqaText: '\n',
    convertToLexiqaIgnoreAnglesBrackets: true,
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
    replaceForLexiqa: true,
    lexiqaText: '\n',
    convertToLexiqaIgnoreAnglesBrackets: true,
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
  space: {
    type: 'space',
    regex: /(?<!<[^>]*)\s(?![^<]*>)/g,
    selfClosing: true,
    isClosure: false,
    placeholder: '\u00b7',
    encodedPlaceholder: ' ',
    placeholderRegex: null,
    decodeNeeded: false,
    errorCheckAvailable: false,
    lexiqaAvailable: true,
    glossaryAvailable: true,
    style: 'tag-selfclosed tag-space-placeholder',
    showTooltip: false,
    replaceForLexiqa: true,
    lexiqaText: ' ',
  },
}

const tagSignaturesMiddleware = (() => {
  const callbacks = {}
  return {
    callbacks,
    set: (tagName, callback) => (callbacks[tagName] = callback),
  }
})()

const setTagSignatureMiddleware = tagSignaturesMiddleware.set

const tagSignatures = new Proxy(tagSignaturesMap, {
  getOwnPropertyDescriptor(target, prop) {
    const value = tagSignaturesMiddleware.callbacks[prop]?.(target[prop])

    const enumerable =
      typeof value !== 'boolean' || (typeof value === 'boolean' && value)

    return {enumerable, configurable: true, value: target[prop]}
  },
  get(target, prop) {
    const value = tagSignaturesMiddleware.callbacks[prop]?.(target[prop])

    if (typeof value === 'boolean' && !value) return

    return typeof value === 'object' ? value : target[prop]
  },
})

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

const getStyleForName = (tagName, isRtl) => {
  return Object.keys(tagSignatures)
    .filter((tagKey) => tagKey === tagName)
    .map((tagKey) =>
      isRtl && tagSignatures[tagKey].styleRTL
        ? tagSignatures[tagKey].styleRTL
        : tagSignatures[tagKey].style,
    )
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
  return /(<\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*((?!&lt;|<).)*?>)/gim
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
  setTagSignatureMiddleware,
}
