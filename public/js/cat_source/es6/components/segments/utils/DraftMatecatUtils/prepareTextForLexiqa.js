import {transformTagsToLexiqaText} from './tagUtils'

const prepareTextForLexiqa = (textSource, editorState) => {
  let newText = transformTagsToLexiqaText(textSource, editorState)
  return newText
}

export default prepareTextForLexiqa
