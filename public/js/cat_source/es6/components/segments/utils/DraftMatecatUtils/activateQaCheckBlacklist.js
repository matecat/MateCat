import * as DraftMatecatConstants from './editorConstants'
import QaCheckBlacklistHighlight from '../../GlossaryComponents/QaCheckBlacklistHighlight.component'
import TextUtils from '../../../../utils/textUtils'
import {regexWordDelimiter} from './textUtils'

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
      start = matchArr.index + 1
      end = start + matchArr[2].length

      const isPreviousBreakWord =
        (start > 0 && regexWordDelimiter.test(text[start - 1])) || start === 0
      const isNextBreakWord = regexWordDelimiter.test(text[end]) || !text[end]

      if (isPreviousBreakWord && isNextBreakWord) callback(start, end)
    }
  }

  const createGlossaryRegex = (blacklistArray) => {
    const matches = blacklistArray.reduce(
      (acc, {matching_words}) => [...acc, ...matching_words],
      [],
    )

    if (!matches.length) return ''

    try {
      const escapedMatches = matches.map((match) =>
        TextUtils.escapeRegExp(match),
      )

      const regex =
          (TextUtils.isSupportingRegexLookAheadLookBehind() && !config.isCJK) ||
          config.isCJK
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
  const regexInstruction = createGlossaryRegex(blackListedTerms)
  return generateGlossaryDecorator(regexInstruction)
}

export default activateQaCheckBlacklist
