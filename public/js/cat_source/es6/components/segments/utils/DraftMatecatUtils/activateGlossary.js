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
    return TextUtils.getGlossaryMatchRegex(matches)
  }

  const regexInstruction = createGlossaryRegex(glossary)
  return generateGlossaryDecorator(regexInstruction)
}

export default activateGlossary
