import * as DraftMatecatConstants from './editorConstants'
import QaCheckBlacklistHighlight from '../../GlossaryComponents/QaCheckBlacklistHighlight.component'
import TextUtils from '../../../../utils/textUtils'

const activateQaCheckBlacklist = (qaCheckGlossary) => {
  const generateGlossaryDecorator = () => {
    return {
      name: DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
      strategy: (contentBlock, callback) => {
        qaCheckGlossary.forEach((match) => {
          match.positions.forEach((position) => {
            const {start, end} = position
            callback(start, end)
          })
        })
        // if (regex !== '') {
        //   findWithRegex(regex, contentBlock, callback)
        // }
      },
      component: QaCheckBlacklistHighlight,
    }
  }
  return generateGlossaryDecorator()
}

export default activateQaCheckBlacklist
