import _ from 'lodash'

import * as DraftMatecatConstants from './editorConstants'
import QaCheckGlossaryHighlight from '../../GlossaryComponents/QaCheckGlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils'

const activateQaCheckGlossary = (missingTerms, text, sid) => {
  const generateGlossaryDecorator = (regex) => {
    return {
      name: DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '') {
          findWithRegex(regex, contentBlock, callback)
        }
      },
      component: QaCheckGlossaryHighlight,
      props: {
        missingTerms,
        sid,
      },
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
    // const matches = _.map(glossaryArray, (elem) => elem.matching_words[0])
    const matches = glossaryArray.reduce(
      (acc, {matching_words}) => [...acc, ...matching_words],
      [],
    )

    if (!matches.length) return ''

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

  const regex = createGlossaryRegex(missingTerms)
  return generateGlossaryDecorator(regex, sid)
}

export default activateQaCheckGlossary
