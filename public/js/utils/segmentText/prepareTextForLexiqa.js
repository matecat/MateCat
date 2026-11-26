import {transformTagsToLexiqaText} from './tagUtils'

const prepareTextForLexiqa = (textSource) => {
  let newText = transformTagsToLexiqaText(textSource)
  return newText
}

export default prepareTextForLexiqa
