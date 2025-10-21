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
  const blockMap = editorState.getCurrentContent().getBlockMap()
  const blocks = blockMap.toArray()
  let updatedTokens = []
  blocks.forEach((block) => {
    const text = block.getText()
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
        key: block.getKey(),
      }
      console.log(e, error, text, tokens)
    }
    let index = 0
    let blockTokens = tokens.map((token) => {
      const value = {
        type: token[0],
        text: token[1],
        start: index,
        end: index + token[1].length,
        key: block.getKey(),
      }
      index = index + token[1].length
      return value
    })
    if (error) {
      if (error.text === 'end of message pattern') {
        blockTokens = blockTokens.map((token) => {
          if (token.end === error.start) {
            token.type = 'error'
            token.message = error.message
          }
          return token
        })
      } else {
        blockTokens.push(error)
      }
    }
    updatedTokens = updatedTokens.concat(blockTokens)
  })
  console.log('updatedTokens', updatedTokens)
  return updateOffsetBasedOnEditorState(editorState, updatedTokens)
}

export const isEqualICUTokens = (tokens, otherTokens) => {
  const filterTokensFn = (token) => token.type !== 'text'
  return isEqual(
    tokens.filter(filterTokensFn),
    otherTokens.filter(filterTokensFn),
  )
}
