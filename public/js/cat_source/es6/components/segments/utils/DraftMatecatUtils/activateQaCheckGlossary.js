import * as DraftMatecatConstants from './editorConstants'
import QaCheckGlossaryHighlight from '../../GlossaryComponents/QaCheckGlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils'

const activateQaCheckGlossary = (missingTerms, text, sid) => {
  const generateGlossaryDecorator = ({regex, regexCallback}) => {
    return {
      name: DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '' && regexCallback) {
          regexCallback(regex, contentBlock, callback)
        }
      },
      component: QaCheckGlossaryHighlight,
      props: {
        missingTerms,
        sid,
      },
    }
  }

  const createGlossaryRegex = (glossaryArray) => {
    // const matches = _.map(glossaryArray, (elem) => elem.matching_words[0])
    const matches = glossaryArray
      .reduce((acc, {matching_words}) => [...acc, ...matching_words], [])
      .sort((a, b) => (a.toLowerCase() < b.toLowerCase() ? 1 : -1)) // Order words alphabetically descending to prioritize composite terms ex. ['Guest favorite', 'guest']
    if (!matches.length) return ''
    return TextUtils.getGlossaryMatchRegex(matches)
  }

  const regexInstruction = createGlossaryRegex(missingTerms)
  return generateGlossaryDecorator(regexInstruction)
}

export default activateQaCheckGlossary
