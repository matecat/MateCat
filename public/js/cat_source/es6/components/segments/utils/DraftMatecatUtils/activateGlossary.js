import _ from 'lodash'

import GlossaryComponent from '../../GlossaryComponents/GlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils.js'
import * as DraftMatecatConstants from './editorConstants'

export const activateGlossary = (glossary, sid) => {
  const generateGlossaryDecorator = (regex) => {
    return {
      name: DraftMatecatConstants.GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (regex !== '') {
          findWithRegex(regex, contentBlock, callback)
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

  const regex = createGlossaryRegex(glossary)
  return generateGlossaryDecorator(regex, sid)
}

export default activateGlossary
