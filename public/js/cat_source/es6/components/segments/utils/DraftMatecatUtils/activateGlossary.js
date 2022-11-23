import _ from 'lodash'

import GlossaryComponent from '../../GlossaryComponents/GlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils.js'
import * as DraftMatecatConstants from './editorConstants'

export const activateGlossary = (glossary, sid) => {
  const generateGlossaryDecorator = ({regex, regexCallback}) => {
    return {
      name: DraftMatecatConstants.GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '' && regexCallback) {
          regexCallback(regex, contentBlock, callback)
        }
      },
      component: GlossaryComponent,
      props: {
        glossary,
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

  const findWithRegexWordSeparator = (regex, contentBlock, callback) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length

      const isPreviousBreakWord =
        (start > 0 && /(\s+|[-+*\\/]|\d+|,|\.|;|\\:)/.test(text[start - 1])) ||
        start === 0
      const isNextBreakWord =
        /(\s+|[-+*\\/]|\d+|,|\.|;|\\:)/.test(text[end]) || !text[end]

      if (isPreviousBreakWord && isNextBreakWord) callback(start, end)
    }
  }

  const createGlossaryRegex = (glossaryArray) => {
    let matches = []
    glossaryArray.forEach((item) => {
      if (!item.missingTerm) {
        const arrayMatches = item.matching_words
        matches = [...matches, ...arrayMatches]
      }
    })
    matches = [...new Set(matches)]
    if (!matches.length) return ''

    try {
      const escapedMatches = matches.map((match) =>
        TextUtils.escapeRegExp(match),
      )

      const regex =
        TextUtils.isSupportingRegexLookAheadLookBehind() && !config.isCJK
          ? new RegExp('(^|\\s)' + escapedMatches.join('|') + '(?=\\s|$)', 'gi')
          : new RegExp('(' + escapedMatches.join('|') + ')', 'gi')

      return {
        regex,
        regexCallback:
          TextUtils.isSupportingRegexLookAheadLookBehind() && !config.isCJK
            ? findWithRegex
            : findWithRegexWordSeparator,
      }
    } catch (e) {
      return {}
    }
  }

  const result = createGlossaryRegex(glossary)
  return generateGlossaryDecorator(result)
}

export default activateGlossary
