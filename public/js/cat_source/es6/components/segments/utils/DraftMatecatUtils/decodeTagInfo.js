import {tagSignatures} from './tagModel'
import {getIdAttributeRegEx, unescapeHTMLRecursive} from './textUtils'
import {Base64} from 'js-base64'

/**
 *
 * @param tag, tagName
 * @returns {{id: string, content: string}}
 */

const decodeTagInfo = (tag) => {
  let decodedTagData = {
    id: '',
    content: '',
  }
  const {name: tagName, encodedText: tagEncodedText} = tag.data || {}
  // if Tag is defined
  if (tagName in tagSignatures) {
    const {
      placeholderRegex,
      decodeNeeded,
      placeholder: tagPlaceholder,
    } = tagSignatures[tagName]
    // Catch ID attribute
    const idMatch = getIdAttributeRegEx().exec(tagEncodedText)
    if (idMatch && idMatch.length > 1) {
      decodedTagData.id = decodedTagData.id + idMatch[1]
    }
    // Catch Content - if regex exists, try to search, else put placeholder
    if (placeholderRegex) {
      const contentMatch = placeholderRegex.exec(tagEncodedText)
      if (contentMatch && contentMatch.length > 1) {
        decodedTagData.content = decodeNeeded
          ? Base64.decode(contentMatch[1])
          : contentMatch[1]
        decodedTagData.content = unescapeHTMLRecursive(
          decodedTagData.content,
        ).replace(/\n/g, ' ')
      } else if (tagPlaceholder) {
        decodedTagData.content = tagSignatures[tagName].placeholder
      }
    } else {
      decodedTagData.content = tagPlaceholder
    }
  } else {
    decodedTagData.content = '?'
  }
  return decodedTagData
}

export default decodeTagInfo
