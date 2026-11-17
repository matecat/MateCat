import decodeTagInfo from './decodeTagInfo'
import {TagStruct} from './tagModel'
import {tagSignatures} from './tagModel'
/**
 *
 * @param text
 * @param tagName
 * @returns {[]} tagRange - array with all occurrences of tagName in the input text
 */
const findTagWithRegex = (text, tagName) => {
  const tagRange = []
  let matchArray
  try {
    const {type, regex, find} = tagSignatures[tagName]
    const matches = find ? find(text) : text.matchAll(regex)

    for (matchArray of matches) {
      const tag = new TagStruct(
        matchArray.index,
        matchArray[0].length,
        type,
        tagName,
      )
      tag.data.encodedText = matchArray[0]
      const tagInfo = decodeTagInfo(tag)
      tag.data.id = tagInfo.id
      tag.data.placeholder = tagInfo.content
      tag.data.decodedText = tagInfo.content
      tag.data.originalOffset = tag.offset
      tagRange.push(tag)
    }
  } catch (e) {
    console.error('Error finding tags in findTagWithRegex')
  }
  return tagRange
}

export default findTagWithRegex
