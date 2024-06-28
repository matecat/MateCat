import {
  getXliffRegExpression,
  isToReplaceForLexiqa,
  tagSignatures,
} from './tagModel'
import {Base64} from 'js-base64'
import TextUtils from '../../../../utils/textUtils'
import {isUndefined} from 'lodash'
import getEntities from './getEntities'
import matchTagStructure from '../../../segments/utils/DraftMatecatUtils/matchTag'

export const transformTagsToHtml = (text, isRtl = 0) => {
  isRtl = !!isRtl
  try {
    for (let key in tagSignatures) {
      const {
        placeholderRegex,
        decodeNeeded,
        style,
        placeholder,
        regex,
        styleRTL,
        selfClosing,
      } = tagSignatures[key]
      if (placeholderRegex) {
        let globalRegex = new RegExp(
          placeholderRegex.source,
          placeholderRegex.flags + 'g',
        )
        text = text.replace(globalRegex, (match, text) => {
          let tagText = decodeNeeded
            ? Base64.decode(text).replace(/</g, '&lt').replace(/>/g, '&gt') // Forza conversione angolari in &lt o &gt [XLIFF 2.0] Tag senza dataref
            : selfClosing
              ? text
              : match
          return (
            '<span contenteditable="false" class="tag small ' +
            (isRtl && styleRTL ? styleRTL : style) +
            '">' +
            tagText +
            '</span>'
          )
        })
      } else if (regex) {
        let globalRegex = new RegExp(regex)
        text = text.replace(globalRegex, (match) => {
          let tagText = placeholder ? placeholder : match
          return (
            '<span contenteditable="false" class="tag small ' +
            (isRtl && styleRTL ? styleRTL : style) +
            '">' +
            tagText +
            '</span>'
          )
        })
      }
    }
    text = matchTag(text)
  } catch (e) {
    console.error('Error parsing tag in transformTagsToHtml function')
  }
  return text
}

export const transformTagsToText = (text) => {
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
    console.error('Error parsing tag in transformTagsToHtml function')
  }
  return text
}

export const transformTagsToLexiqaText = (text) => {
  const tagsStruct = matchTagStructure(text).sort((a, b) =>
    a.offset > b.offset ? 1 : -1,
  )

  let textNormalized = text
  const {tags, text: tempText} = TextUtils.replaceTempTags(text)
  textNormalized = decodeHtmlEntities(tempText)
  textNormalized = TextUtils.restoreTempTags(tags, textNormalized)

  return tagsStruct.reduce(
    (acc, {data}) => {
      const {value, difference} = acc
      const {convertToLexiqaIgnoreAnglesBrackets, lexiqaText, decodeNeeded} =
        tagSignatures[data.name]

      const offsetStart = data.originalOffset - difference
      const offsetEnd = offsetStart + data.encodedText.length

      const placeholderText = isToReplaceForLexiqa(data.name)
        ? lexiqaText
        : decodeNeeded
          ? data.decodedText
          : data.id !== ''
            ? data.id
            : data.placeholder

      const shouldIgnoreAngleBrackets = convertToLexiqaIgnoreAnglesBrackets

      const placeholder = `${!shouldIgnoreAngleBrackets ? '<' : ''}${placeholderText}${!shouldIgnoreAngleBrackets ? '>' : ''}`
      const newValue = `${value.slice(0, offsetStart)}${placeholder}${value.substring(offsetEnd)}`

      return {
        value: newValue,
        difference: difference + (value.length - newValue.length),
      }
    },
    {value: textNormalized, difference: 0},
  ).value
}

// Associate tag of type g with integer id
const matchTag = (tx) => {
  const openRegex = tagSignatures['g'].regex
  const closeRegex = tagSignatures['gCl'].regex
  try {
    let openingMatchArr
    let openings = []
    while ((openingMatchArr = openRegex.exec(tx)) !== null) {
      const openingGTag = {}
      openingGTag.length = openingMatchArr[0].length
      openingGTag.id = openingMatchArr[1]
      openingGTag.offset = openingMatchArr.index
      openings.push(openingGTag)
    }

    let closingMatchArr
    let closings = []
    while ((closingMatchArr = closeRegex.exec(tx)) !== null) {
      const closingGTag = {}
      closingGTag.length = closingMatchArr[0].length
      closingGTag.offset = closingMatchArr.index
      closings.push(closingGTag)
    }

    openings.sort((a, b) => {
      return b.offset - a.offset
    })
    closings.sort((a, b) => {
      return a.offset - b.offset
    })

    closings.forEach((closingTag) => {
      let i = 0,
        notFound = true
      while (i < openings.length && notFound) {
        if (closingTag.offset > openings[i].offset && !openings[i].closeTagId) {
          notFound = !notFound
          openings[i].closeTagId = true
          // Closing tag has no ID, so take the one available inside open tag
          closingTag.id = openings[i].id
        }
        i++
      }
      // display every orphan closure as '?'
      if (notFound) closingTag.id = '?'
    })

    tx = tx.replace(openRegex, function () {
      return (
        String.fromCharCode(parseInt('200B', 16)) +
        openings.pop().id +
        String.fromCharCode(parseInt('200B', 16))
      )
    })

    tx = tx.replace(closeRegex, function () {
      return (
        String.fromCharCode(parseInt('200B', 16)) +
        closings.shift().id +
        String.fromCharCode(parseInt('200B', 16))
      )
    })
  } catch (e) {
    console.error('Error matching tag g in TagUtils.matchTag function')
  }
  return tx
}

export const decodePlaceholdersToPlainText = (str) => {
  return str
    .replace(config.lfPlaceholderRegex, tagSignatures['lineFeed'].placeholder)
    .replace(
      config.crPlaceholderRegex,
      tagSignatures['carriageReturn'].placeholder,
    )
    .replace(
      config.crlfPlaceholderRegex,
      `${tagSignatures['carriageReturn'].placeholder}${tagSignatures['lineFeed'].placeholder}`,
    )
    .replace(config.tabPlaceholderRegex, tagSignatures['tab'].placeholder)
    .replace(config.nbspPlaceholderRegex, tagSignatures['nbsp'].placeholder)
}

export const removePlaceholdersForGlossary = (str) => {
  return str.replace(config.nbspPlaceholderRegex, ' ')
}

export const decodeHtmlEntities = (text) => {
  return (
    text
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

export const getIdAttributeRegEx = () => {
  return /id="(-?\w+)"/g
}

/**
 *
 * @param segmentString
 * @returns {*}
 */
export const removeTagsFromText = (segmentString) => {
  const regExp = getXliffRegExpression()
  if (segmentString) {
    return segmentString.replace(regExp, '')
  }
  return segmentString
}

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTMLinTags = (escapedHTML) => {
  try {
    return escapedHTML
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;amp;/g, '&')
      .replace(/&amp;/g, '&')
      .replace(/&nbsp;/g, ' ')
      .replace(/&apos;/g, "'")
      .replace(/&quot;/g, '"')
  } catch (e) {
    return ''
  }
}

export const unescapeHTMLRecursive = (escapedHTML) => {
  const regex = /&amp;|&lt;|&gt;|&nbsp;|&apos;|&quot;/

  try {
    while (regex.exec(escapedHTML) !== null) {
      escapedHTML = unescapeHTMLinTags(escapedHTML)
    }
  } catch (e) {
    console.error('Error unescapeHTMLRecursive')
  }

  return escapedHTML
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
