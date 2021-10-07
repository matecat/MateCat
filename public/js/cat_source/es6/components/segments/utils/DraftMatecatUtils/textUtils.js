import {getXliffRegExpression} from './tagModel'
import {Base64} from 'js-base64'

import TagUtils from '../../../../utils/tagUtils'

/**
 *
 * @param segmentString
 * @returns {*}
 */
export const cleanSegmentString = (segmentString) => {
  const regExp = getXliffRegExpression()
  if (segmentString) {
    return segmentString.replace(regExp, '')
  }
  return segmentString
}

export const getIdAttributeRegEx = () => {
  return /id="(-?\w+)"/g
}

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTML = (escapedHTML) => {
  try {
    return escapedHTML
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
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
      escapedHTML = unescapeHTML(escapedHTML)
    }
  } catch (e) {
    console.error('Error unescapeHTMLRecursive')
  }

  return escapedHTML
}

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTMLLeaveTags = (escapedHTML) => {
  if (escapedHTML) {
    return escapedHTML
      .replace(/&amp;/g, '&')
      .replace(/&nbsp;/g, ' ')
      .replace(/&#39;/g, '’')
      .replace(/&apos;/g, "'")
      .replace(/&quot;/g, '"')
  }
  return escapedHTML
}

export const decodeTagsToPlainText = (text) => {
  let decoded = ''

  if (text) {
    // Match G - temporary until backend put IDs in closing tags </g>
    decoded = TagUtils.matchTag(text)
    // Match Others (x|bx|ex|bpt|ept|ph.*?|it|mrk)
    decoded = decoded.replace(
      /&lt;(?:x|bx|ex|bpt|ept|it|mrk).*?id="(.*?)".*?\/&gt;/gi,
      (match, text) => {
        return text
      },
    )
    // Match PH
    decoded = decoded.replace(
      /&lt;ph.*?equiv-text="base64:(.*?)"\/&gt;/gi,
      (match, text) => {
        try {
          return Base64.decode(text)
        } catch (e) {
          console.error('Fail decoding tags in text', match, text)
        }
      },
    )

    // Convert placeholder (nbsp, tab, lineFeed, carriageReturn)
    decoded = TagUtils.decodePlaceholdersToPlainText(decoded)
    return decoded
  }
  return ''
}

export const formatText = (text, format) => {
  switch (format) {
    case 'uppercase':
      text = text.toUpperCase()
      break
    case 'lowercase':
      text = text.toLowerCase()
      break
    case 'capitalize':
      text = text.charAt(0).toUpperCase() + text.substr(1).toLowerCase()
      break
    default:
      break
  }
  return text
}
