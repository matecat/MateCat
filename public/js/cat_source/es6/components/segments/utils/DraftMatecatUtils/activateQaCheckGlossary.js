import _ from 'lodash'
import CompoundDecorator from '../CompoundDecorator'
import {CompositeDecorator, EditorState} from 'draft-js'
import * as DraftMatecatConstants from './editorConstants'
import QaCheckGlossaryHighlight from '../../GlossaryComponents/QaCheckGlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils'

const activateQaCheckGlossary = (qaCheckGlossary, text, sid) => {
  const generateGlossaryDecorator = (regex, sid) => {
    return {
      name: DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '') {
          findWithRegex(regex, contentBlock, callback)
        }
      },
      component: QaCheckGlossaryHighlight,
    }
  }

  const findWithRegex = (regex, contentBlock, callback) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length
      callback(start, end)
    }
  }

  const createGlossaryRegex = (glossaryArray) => {
    const matches = _.map(glossaryArray, (elem) =>
      elem.raw_segment ? elem.raw_segment : elem.segment,
    )
    let re
    try {
      const escapedMatches = matches.map((match) =>
        TextUtils.escapeRegExp(match),
      )
      re = new RegExp('\\b(' + escapedMatches.join('|') + ')\\b', 'gi')
      //If source languace is Cyrillic or CJK
      if (config.isCJK) {
        re = new RegExp('(' + escapedMatches.join('|') + ')', 'gi')
      }
    } catch (e) {
      return null
    }
    return re
  }

  const regex = createGlossaryRegex(qaCheckGlossary)
  return generateGlossaryDecorator(regex, sid)
}

export default activateQaCheckGlossary
