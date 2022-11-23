import _ from 'lodash'

import * as DraftMatecatConstants from './editorConstants'
import QaCheckGlossaryHighlight from '../../GlossaryComponents/QaCheckGlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils'
import {regexWordDelimiter} from './textUtils'

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

  const findWithRegex = (regex, contentBlock, callback) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = start = matchArr.index > 0 ? matchArr.index + 1 : 0
      end = start + matchArr[2].length
      callback(start, end)
    }
  }

  const findWithRegexWordSeparator = (regex, contentBlock, callback) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length

      const isPreviousBreakWord =
        (start > 0 && regexWordDelimiter.test(text[start - 1])) || start === 0
      const isNextBreakWord = regexWordDelimiter.test(text[end]) || !text[end]

      if (isPreviousBreakWord && isNextBreakWord) callback(start, end)
    }
  }

  const createGlossaryRegex = (glossaryArray) => {
    // const matches = _.map(glossaryArray, (elem) => elem.matching_words[0])
    const matches = glossaryArray.reduce(
      (acc, {matching_words}) => [...acc, ...matching_words],
      [],
    )

    if (!matches.length) return ''

    try {
      const escapedMatches = matches.map((match) =>
        TextUtils.escapeRegExp(match),
      )

      const regex =
        TextUtils.isSupportingRegexLookAheadLookBehind() && !config.isCJK
          ? new RegExp(
              '(^|\\W)(' + escapedMatches.join('|') + ')(?=\\W|$)',
              'gi',
            )
          : new RegExp('(' + escapedMatches.join('|') + ')', 'gi')

      return {
        regex,
        regexCallback:
          (TextUtils.isSupportingRegexLookAheadLookBehind() && !config.isCJK) ||
          config.isCJK
            ? findWithRegex
            : findWithRegexWordSeparator,
      }
    } catch (e) {
      return {}
    }
  }

  const regexInstruction = createGlossaryRegex(missingTerms)
  return generateGlossaryDecorator(regexInstruction)
}

export default activateQaCheckGlossary
