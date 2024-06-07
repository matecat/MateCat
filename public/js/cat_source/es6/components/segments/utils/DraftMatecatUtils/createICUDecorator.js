import * as DraftMatecatConstants from './editorConstants'
import parse from 'format-message-parse'
import {IcuHighlight} from '../../IcuHighlight'
import {isEqual} from 'lodash'
import updateLexiqaWarnings from './updateLexiqaWarnings'
export const createICUDecorator = (text = '', editorState) => {
  let tokensWithIndex = []
  tokensWithIndex = createIcuTokens(text, editorState)

  return {
    name: DraftMatecatConstants.ICU_DECORATOR,
    strategy: (contentBlock, callback) => {
      const currentText = contentBlock.getText()
      tokensWithIndex.forEach((token) => {
        const subString = currentText.substring(token.start, token.end)
        if (
          token.end <= currentText.length &&
          token.type !== 'text' &&
          subString === token.text
        ) {
          callback(token.start, token.end)
        }
      })
    },
    component: IcuHighlight,
    props: {
      text,
    },
  }
}

export const createIcuTokens = (text, editorState) => {
  const tokens = []
  try {
    parse(text, {tokens: tokens})
  } catch (e) {
    console.log(e)
  }
  let index = 0
  const updatedTokens = tokens.map((token) => {
    const value = {
      type: token[0],
      text: token[1],
      start: index,
      end: index + token[1].length,
    }
    index = index + token[1].length
    return value
  })
  return updateLexiqaWarnings(editorState, updatedTokens)
}

export const isEqualICUTokens = (tokens, otherTokens) => {
  const filterTokensFn = (token) => token.type !== 'text'
  return isEqual(
    tokens.filter(filterTokensFn),
    otherTokens.filter(filterTokensFn),
  )
}
