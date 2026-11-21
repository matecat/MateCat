import {
  getXliffRegExpression,
  isToReplaceForLexiqa,
  tagSignatures,
} from './tagModel'
import {Base64} from 'js-base64'
// replaceTempTags/restoreTempTags inlined from textUtils to avoid
// the cycle: SegmentStore → tagUtils → textUtils → commonUtils → SegmentStore
const replaceTempTags = (text) => {
  const tags = []
  const makeid = (len) => {
    let r = ''
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
    for (let i = 0; i < len; i++)
      r += chars.charAt(Math.floor(Math.random() * chars.length))
    return r
  }
  text = text.replace(
    /<(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk).*?>/gi,
    (match) => {
      const id = makeid(5)
      tags.push({id, match})
      return '#_' + id + '_#'
    },
  )
  return {tags, text}
}
const restoreTempTags = (tags, text) => {
  // The first tag with an id wins, as a find over the list would pick it
  const byId = new Map()
  tags.forEach(({id, match}) => {
    if (!byId.has(id)) byId.set(id, match)
  })
  return text.replace(/#_([a-zA-Z]+?)_#/gi, (match, id) =>
    byId.has(id) ? byId.get(id) : match,
  )
}
import {isUndefined} from 'lodash'
import {getGEquivText} from './compressibleTagUtils'
import {
  numberTagsInDocumentOrder,
  pairClosingTags,
  replaceTagMatches,
  findTagMatches,
  scanPairedTags,
} from './tagScan'

// A closing tag shows only its number (no content); an open tag shows its
// number followed by its content. Every numbered tag takes the ph style,
// so a numbered g looks like a pc tag rather than a g.
const numberedTagToHtml = (isRtl, {index, role}, tagText) => {
  const {style, styleRTL} = tagSignatures.ph
  const styleClass = isRtl && styleRTL ? styleRTL : style
  const pairedClass =
    role === 'open' || role === 'close' ? ' tag-paired-' + role : ''
  const openSpan =
    '<span contenteditable="false" class="tag small ' +
    styleClass +
    ' tag-numbered' +
    pairedClass +
    '">' +
    '<span class="index-counter">' +
    (index + 1) +
    '</span>'
  if (role === 'close') return openSpan + '</span>'
  return openSpan + '<span data-text="true">' + tagText + '</span></span>'
}

// Forza conversione angolari in &lt o &gt [XLIFF 2.0] Tag senza dataref
const escapeAngleBrackets = (text) =>
  text.replace(/</g, '&lt').replace(/>/g, '&gt')

// No production caller: segments are shown with SegmentView. Kept as the
// reference SegmentView.parity.test.js holds that component to.
export const transformTagsToHtml = (text, isRtl = 0) => {
  isRtl = !!isRtl
  if (text) {
    try {
      const numbers = numberTagsInDocumentOrder(text)
      for (let key in tagSignatures) {
        const {
          placeholderRegex,
          decodeNeeded,
          style,
          placeholder,
          regex,
          styleRTL,
          selfClosing,
          type,
        } = tagSignatures[key]
        if (placeholderRegex) {
          text = replaceTagMatches(
            text,
            key,
            ({0: match, 1: captured}) => {
              let tagText = decodeNeeded
                ? escapeAngleBrackets(Base64.decode(captured))
                : selfClosing
                  ? captured
                  : match
              // Only compressible tags are numbered; a plain <g> keeps its raw
              // markup here and gets its id from showGTagIds below.
              const numbered = numbers[key]?.shift()
              if (numbered) {
                return numberedTagToHtml(
                  isRtl,
                  numbered,
                  type === 'g'
                    ? escapeAngleBrackets(getGEquivText(match))
                    : tagText,
                )
              }
              return (
                '<span contenteditable="false" class="tag small ' +
                (isRtl && styleRTL ? styleRTL : style) +
                '">' +
                tagText +
                '</span>'
              )
            },
            {display: true},
          )
        } else if (regex) {
          text = replaceTagMatches(
            text,
            key,
            ({0: match}) => {
              let tagText = placeholder ? placeholder : match
              const numbered = numbers[key]?.shift()
              if (numbered) {
                return numberedTagToHtml(isRtl, numbered, tagText)
              }
              return (
                '<span contenteditable="false" class="tag small ' +
                (isRtl && styleRTL ? styleRTL : style) +
                '">' +
                tagText +
                '</span>'
              )
            },
            {display: true},
          )
        }
      }
      text = showGTagIds(text)
    } catch (e) {
      console.error('Error parsing tag in transformTagsToHtml function', e)
    }
  }
  return text
}

export const transformTagsToText = (text) => {
  if (!text) return text
  try {
    for (let key in tagSignatures) {
      const {placeholderRegex, decodeNeeded, placeholder, regex} =
        tagSignatures[key]
      if (placeholderRegex) {
        let globalRegex = new RegExp(
          placeholderRegex.source,
          placeholderRegex.flags + 'g',
        )
        text = text.replace(globalRegex, (match, text) => {
          return decodeNeeded ? decodeHtmlEntities(Base64.decode(text)) : match
        })
      } else if (regex) {
        let globalRegex = new RegExp(regex)
        text = text.replace(globalRegex, (match) => {
          return placeholder ? placeholder : match
        })
      }
    }
  } catch (e) {
    console.error('Error parsing tag in transformTagsToText function', e)
  }
  return text
}

export const excludeSomeTagsTransformToText = (text, excludeTags = []) => {
  try {
    for (let key in tagSignatures) {
      const {placeholderRegex, decodeNeeded, encodedPlaceholder, regex, type} =
        tagSignatures[key]
      const shouldExcludeTag = excludeTags.some((value) => value === type)
      if (placeholderRegex) {
        let globalRegex = new RegExp(
          placeholderRegex.source,
          placeholderRegex.flags + 'g',
        )
        text = text.replace(
          globalRegex,
          !shouldExcludeTag
            ? (match, text) => {
                return decodeNeeded
                  ? decodeHtmlEntities(Base64.decode(text))
                  : match
              }
            : '',
        )
      } else if (regex) {
        let globalRegex = new RegExp(regex)
        text = text.replace(
          globalRegex,
          !shouldExcludeTag
            ? (match) => {
                return encodedPlaceholder ? encodedPlaceholder : match
              }
            : '',
        )
      }
    }
  } catch (e) {
    console.error('Error parsing tag in excludeSomeTagsTransformToText function', e)
  }
  return text
}

export const excludeSomeTagsFromText = (text, excludeTags = []) => {
  try {
    for (let key in tagSignatures) {
      const {placeholderRegex, regex, type} = tagSignatures[key]
      const shouldExcludeTag = excludeTags.some((value) => value === type)

      if (shouldExcludeTag) {
        const globalRegex = placeholderRegex
          ? new RegExp(placeholderRegex.source, placeholderRegex.flags + 'g')
          : new RegExp(regex)

        text = text.replace(globalRegex, '')
      }
    }
  } catch (e) {
    console.error('Error parsing tag in excludeSomeTagsFromText function', e)
  }
  return text
}

// decodeHtmlEntities shrinks these entities; used to correct tag offsets below
const HTML_ENTITY_DECODE_DIFF = {
  '&lt;': 3,
  '&gt;': 3,
  '&amp;': 4,
}
// decodeHtmlEntities only decodes entities outside of tags (tags are protected
// via temp placeholders), so offsets computed on the raw text must be shifted
// left by however much entity-decoding shrinks the plain text before them.
// Returns that shrink as a function of the offset, scanning the text once.
const decodeShrinkCounter = (text, tagsStruct) => {
  // Characters covered by a tag: +1 where a tag starts, -1 where it ends
  const coverage = new Int32Array(text.length + 1)
  tagsStruct.forEach(({offset, length}) => {
    const start = Math.max(0, Math.min(offset, text.length))
    const end = Math.max(start, Math.min(offset + length, text.length))
    coverage[start]++
    coverage[end]--
  })
  const entityOffsets = []
  const shrinkUpTo = [0]
  const regex = /&lt;|&gt;|&amp;/g
  let match
  let covered = 0
  let scanned = 0
  while ((match = regex.exec(text)) !== null) {
    for (; scanned <= match.index; scanned++) covered += coverage[scanned]
    if (covered <= 0) {
      entityOffsets.push(match.index)
      shrinkUpTo.push(
        shrinkUpTo[shrinkUpTo.length - 1] + HTML_ENTITY_DECODE_DIFF[match[0]],
      )
    }
  }
  return (upToOffset) => {
    // Entities starting before upToOffset
    let low = 0
    let high = entityOffsets.length
    while (low < high) {
      const middle = (low + high) >> 1
      if (entityOffsets[middle] < upToOffset) low = middle + 1
      else high = middle
    }
    return shrinkUpTo[low]
  }
}

export const transformTagsToLexiqaText = (text) => {
  const tagsStruct = scanPairedTags(text).sort((a, b) =>
    a.offset > b.offset ? 1 : -1,
  )

  let textNormalized = text
  const {tags, text: tempText} = replaceTempTags(text)
  textNormalized = decodeHtmlEntities(tempText)
  textNormalized = restoreTempTags(tags, textNormalized)

  const decodeShrinkBefore = decodeShrinkCounter(text, tagsStruct)

  // The text is built in chunks: `built` holds the replaced part, `rest` from `consumed` on is still
  // untouched. Rebuilding the whole string for every tag took quadratic time on long segments.
  const built = []
  let builtLength = 0
  let rest = textNormalized
  let consumed = 0
  let difference = 0
  tagsStruct.forEach((tag) => {
    const {convertToLexiqaIgnoreAnglesBrackets, lexiqaText, decodeNeeded} =
      tagSignatures[tag.name]

    const offsetStart = tag.offset - decodeShrinkBefore(tag.offset) - difference
    const offsetEnd = offsetStart + tag.encodedText.length

    // Must match the text the tag shows in the editor, or transformLexiqaPoints
    // maps the warnings back to the wrong offsets: a <g> and its </g> show the
    // <g>'s equiv-text when it has one, otherwise its id.
    const placeholderText = isToReplaceForLexiqa(tag.name)
      ? lexiqaText
      : decodeNeeded
        ? tag.decodedText
        : tag.name === 'g' || tag.name === 'gCl'
          ? tag.placeholder
          : tag.id !== ''
            ? tag.id
            : tag.placeholder

    const shouldIgnoreAngleBrackets = convertToLexiqaIgnoreAnglesBrackets

    const placeholder = `${!shouldIgnoreAngleBrackets ? '<' : ''}${placeholderText}${!shouldIgnoreAngleBrackets ? '>' : ''}`

    const valueLength = builtLength + rest.length - consumed
    let newLength
    if (offsetStart >= builtLength && offsetEnd <= valueLength) {
      built.push(
        rest.slice(consumed, consumed + offsetStart - builtLength),
        placeholder,
      )
      consumed += offsetEnd - builtLength
      builtLength = offsetStart + placeholder.length
      newLength = valueLength - (offsetEnd - offsetStart) + placeholder.length
    } else {
      // A tag overlapping one already replaced, or past the end: the whole string, as before
      const value = built.join('') + rest.slice(consumed)
      rest = `${value.slice(0, offsetStart)}${placeholder}${value.substring(offsetEnd)}`
      built.length = 0
      builtLength = 0
      consumed = 0
      newLength = rest.length
    }
    difference += valueLength - newLength
  })
  return built.join('') + rest.slice(consumed)
}

const ZWSP = String.fromCharCode(parseInt('200B', 16))

// The last step of transformTagsToHtml: a plain <g> left as markup shows its id, a plain </g> the id of the nearest
// <g> before it still open, or '?' when there is none. The </g> are found again once the <g> are replaced, as the id
// written in place of a <g> can hold one.
const showGTagIds = (tx) => {
  try {
    const gTags = [
      ...findTagMatches(tx, 'g').map((match) => ({opening: true, match})),
      ...findTagMatches(tx, 'gCl').map((match) => ({opening: false, match})),
    ].sort((a, b) => a.match.index - b.match.index)
    const pairs = pairClosingTags(
      gTags,
      (tag) => tag.opening,
      (tag) => !tag.opening,
    )
    const openingIds = gTags
      .filter((tag) => tag.opening)
      .map((tag) => tag.match[1])
    const closingIds = [...pairs.values()].map((opening) =>
      opening ? opening.match[1] : '?',
    )
    tx = replaceTagMatches(tx, 'g', () => ZWSP + openingIds.shift() + ZWSP)
    if (findTagMatches(tx, 'gCl').length > closingIds.length)
      throw new Error('More closing g tags than were paired')
    tx = replaceTagMatches(tx, 'gCl', () => ZWSP + closingIds.shift() + ZWSP)
  } catch (e) {
    console.error('Error matching tag g in TagUtils.showGTagIds function')
  }
  return tx
}

export const decodePlaceholdersToPlainText = (str) => {
  return str
    ? str
        .replace(
          tagSignatures['lineFeed'].regex,
          tagSignatures['lineFeed'].placeholder,
        )
        .replace(
          tagSignatures['carriageReturn'].regex,
          tagSignatures['carriageReturn'].placeholder,
        )
        .replace(
          tagSignatures['carriageReturn'].regex,
          `${tagSignatures['carriageReturn'].placeholder}${tagSignatures['lineFeed'].placeholder}`,
        )
        .replace(tagSignatures['tab'].regex, tagSignatures['tab'].placeholder)
        .replace(tagSignatures['nbsp'].regex, tagSignatures['nbsp'].placeholder)
    : str
}
export const encodePlaceholdersToTags = (str) => {
  return str
    ? str
        .replaceAll(
          tagSignatures['lineFeed'].placeholder,
          tagSignatures['lineFeed'].encodedPlaceholder,
        )
        .replaceAll(
          tagSignatures['carriageReturn'].placeholder,
          tagSignatures['carriageReturn'].encodedPlaceholder,
        )
        .replaceAll(
          `${tagSignatures['carriageReturn'].placeholder}${tagSignatures['lineFeed'].placeholder}`,
          tagSignatures['carriageReturn'].encodedPlaceholder,
        )
        .replaceAll(
          tagSignatures['tab'].placeholder,
          tagSignatures['tab'].encodedPlaceholder,
        )
        .replaceAll(
          tagSignatures['nbsp'].placeholder,
          tagSignatures['nbsp'].encodedPlaceholder,
        )
    : str
}

export const decodeTagsToUnicodeChar = (str) => {
  return str
    ? str
        .replace(
          tagSignatures['lineFeed'].regex,
          tagSignatures['lineFeed'].unicodeChar,
        )
        .replace(
          tagSignatures['carriageReturn'].regex,
          tagSignatures['carriageReturn'].unicodeChar,
        )
        .replace(
          tagSignatures['carriageReturn'].regex,
          tagSignatures['carriageReturn'].unicodeChar,
        )
        .replace(tagSignatures['tab'].regex, tagSignatures['tab'].unicodeChar)
        .replace(tagSignatures['nbsp'].regex, tagSignatures['nbsp'].unicodeChar)
    : str
}

export const encodeTagsFromUnicodeChar = (str) => {
  return str
    ? str
        .replace(
          tagSignatures['lineFeed'].regexUnicodeChar,
          tagSignatures['lineFeed'].encodedPlaceholder,
        )
        .replace(
          tagSignatures['carriageReturn'].regexUnicodeChar,
          tagSignatures['carriageReturn'].encodedPlaceholder,
        )
        .replace(
          tagSignatures['tab'].regexUnicodeChar,
          tagSignatures['tab'].encodedPlaceholder,
        )
        .replace(
          tagSignatures['nbsp'].regexUnicodeChar,
          tagSignatures['nbsp'].encodedPlaceholder,
        )
    : str
}

export const removePlaceholdersForGlossary = (str) => {
  return str.replace(tagSignatures['nbsp'].regex, ' ')
}

export const decodeHtmlEntities = (text) => {
  return (
    (text ?? '')
      // .replace(/&apos;/g, "'")
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&')
  )
}
export const encodeHtmlEntities = (text) => {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  // .replace(/'/g, '&apos;')
}


export const removeTagsFromText = (segmentString) => {
  const regExp = getXliffRegExpression()
  if (segmentString) {
    return segmentString.replace(regExp, '')
  }
  return segmentString
}

export const textHasTags = (segmentString) => {
  const regExp = getXliffRegExpression()
  if (segmentString) {
    return regExp.test(segmentString)
  }
  return segmentString
}

/**
 * Add at the end of the target the missing tags
 */
export const autoFillTagsInTarget = (segmentObj) => {
  const regx = getXliffRegExpression()
  let sourceTags = segmentObj.segment.match(regx)

  let newhtml = segmentObj.translation

  let targetTags = segmentObj.translation.match(regx)

  if (targetTags == null) {
    targetTags = []
  } else {
    targetTags = targetTags.map(function (elem) {
      return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '')
    })
  }

  let missingTags = sourceTags.map(function (elem) {
    return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '')
  })
  //remove from source tags all the tags in target segment
  for (let i = 0; i < targetTags.length; i++) {
    let pos = missingTags.indexOf(targetTags[i])
    if (pos > -1) {
      missingTags.splice(pos, 1)
    }
  }

  //add tags into the target segment
  for (let i = 0; i < missingTags.length; i++) {
    newhtml = newhtml + missingTags[i]
  }
  return newhtml
}

/**
 * Check if the data-original attribute in the source of the segment contains special tags (Ex: <g id=1></g>)
 * (Note that in the data-original attribute there are the &amp;lt instead of &lt)
 * @param originalText
 * @returns {boolean}
 */
export const hasDataOriginalTags = (originalText) => {
  const reg = getXliffRegExpression()
  return !isUndefined(originalText) && reg.test(originalText)
}

export const checkXliffTagsInText = (text) => {
  const reg = getXliffRegExpression()
  return reg.test(text)
}

export const removeZeroWidthSpace = (text) => {
  return text.replace(
    new RegExp(String.fromCharCode(parseInt('200B', 16)), 'gi'),
    '',
  )
}
