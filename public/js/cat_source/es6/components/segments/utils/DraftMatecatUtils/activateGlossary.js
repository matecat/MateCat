import _ from 'lodash'

import GlossaryComponent from '../../GlossaryComponents/GlossaryHighlight.component'
import TextUtils from '../../../../utils/textUtils.js'
import * as DraftMatecatConstants from './editorConstants'
import canDecorateRange from './canDecorateRange'

export const activateGlossary = (
  editorState,
  glossary,
  text,
  sid,
  segmentAction,
) => {
  const generateGlossaryDecorator = (regex, sid) => {
    return {
      name: DraftMatecatConstants.GLOSSARY_DECORATOR,
      strategy: (contentBlock, callback, contentState) => {
        if (regex !== '') {
          findWithRegex(
            regex,
            contentState,
            contentBlock,
            callback,
            DraftMatecatConstants.GLOSSARY_DECORATOR,
          )
        }
      },
      component: GlossaryComponent,
      props: {
        sid: sid,
        onClickAction: segmentAction,
      },
    }
  }

  const findWithRegex = (
    regex,
    contentState,
    contentBlock,
    callback,
    decoratorName,
  ) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length
      const canDecorate = canDecorateRange(
        start,
        end,
        contentBlock,
        contentState,
        decoratorName,
      )
      if (canDecorate) callback(start, end)
      //callback(start, end);
    }
  }

  const createGlossaryRegex = (glossaryObj, text) => {
    let re
    try {
      const matches = _.map(glossaryObj, (elem) =>
        elem.raw_segment ? elem.raw_segment : elem.segment,
      )
      const matchToExclude = findInclusiveMatches(matches)
      let matchToUse = []
      _.forEach(matches, (match) => {
        if (matchToExclude.indexOf(match) === -1) {
          matchToUse.push(match)
        }
      })

      const escapedMatches = matchToUse.map((match) =>
        TextUtils.escapeRegExp(match),
      )

      if (escapedMatches.length == 0) {
        throw new Error('Empty matches list')
      }

      re = new RegExp('\\b(' + escapedMatches.join('|') + ')\\b', 'gi')

      //If source language is Cyrillic or CJK
      if (config.isCJK) {
        re = new RegExp('(' + escapedMatches.join('|') + ')', 'gi')
      }
    } catch (ignore) {}

    // this regexp used as default value do not match anything
    // return this instead of null, null value causes the application crash
    return re ? re : new RegExp('(?!.*)', 'gi')
  }
  /**
   * This function returns an array of strings that are already contained in other strings.
   *
   * Example:
   *      input ['canestro', 'cane', 'gatto']
   *      returns [ 'cane' ]
   *
   * @param matches
   * @returns {Array}
   */
  const findInclusiveMatches = (matches) => {
    var inclusiveMatches = []
    $.each(matches, function (index) {
      $.each(matches, function (ind) {
        if (index !== ind) {
          if (
            _.startsWith(matches[index].toLowerCase(), this.toLowerCase()) &&
            matches[index].toLowerCase() !== this.toLowerCase()
          ) {
            inclusiveMatches.push(this)
          }
        }
      })
    })
    return inclusiveMatches
  }

  const regex = createGlossaryRegex(glossary, text)
  return generateGlossaryDecorator(regex, sid)
}

export default activateGlossary
