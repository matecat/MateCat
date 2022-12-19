import * as DraftMatecatConstants from './editorConstants'
import QaCheckBlacklistHighlight from '../../GlossaryComponents/QaCheckBlacklistHighlight.component'
import TextUtils from '../../../../utils/textUtils'

const activateQaCheckBlacklist = (blackListedTerms, sid) => {
  const generateGlossaryDecorator = ({regex, regexCallback}) => {
    return {
      name: DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '' && regexCallback) {
          regexCallback(regex, contentBlock, callback)
        }
      },
      component: QaCheckBlacklistHighlight,
      props: {
        blackListedTerms,
        sid,
      },
    }
  }

  const createGlossaryRegex = (blacklistArray) => {
    const matches = blacklistArray.reduce(
      (acc, {matching_words}) => [...acc, ...matching_words],
      [],
    )

    if (!matches.length) return ''
    return TextUtils.getGlossaryMatchRegex(matches)
  }
  const regexInstruction = createGlossaryRegex(blackListedTerms)
  return generateGlossaryDecorator(regexInstruction)
}

export default activateQaCheckBlacklist
