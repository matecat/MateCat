import * as DraftMatecatConstants from './editorConstants'
import parse from 'format-message-parse'
import {IcuHighlight} from '../../IcuHighlight'
import {isEqual} from 'lodash'
import updateOffsetBasedOnEditorState from './updateOffsetBasedOnEditorState'
export const createICUDecorator = (tokens = []) => {
  return {
    name: DraftMatecatConstants.ICU_DECORATOR,
    strategy: (contentBlock, callback) => {
      const currentText = contentBlock.getText()
      tokens.forEach((token) => {
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
      tokens,
    },
  }
}

export const createIcuTokens = (text, editorState) => {
  const tokens = []
  let error
  try {
    parse(text, {tokens: tokens})
  } catch (e) {
    error = {
      type: 'error',
      text: e.found,
      start: e.column,
      end: e.column + e.found.length,
      message: e.message,
    }
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
  if (error) updatedTokens.push(error)
  return updateOffsetBasedOnEditorState(editorState, updatedTokens)
}

export const isEqualICUTokens = (tokens, otherTokens) => {
  const filterTokensFn = (token) => token.type !== 'text'
  return isEqual(
    tokens.filter(filterTokensFn),
    otherTokens.filter(filterTokensFn),
  )
}
