import * as DraftMatecatConstants from './editorConstants'
import parse from 'format-message-parse'
import {IcuHighlight} from '../../IcuHighlight'
export const createICUDecorator = (text = '', sid) => {
  const tokens = []
  try {
    parse(text, {tokens: tokens})
  } catch (e) {}
  const tokensWithIndex = parseIcuTokens(tokens)

  return {
    name: DraftMatecatConstants.ICU_DECORATOR,
    strategy: (contentBlock, callback) => {
      const currentText = contentBlock.getText()
      tokensWithIndex.forEach((token) => {
        if (token.end <= currentText.length && token.type !== 'text') {
          callback(token.start, token.end)
        }
      })
    },
    component: IcuHighlight,
    props: {
      text,
      sid,
    },
  }
}

const parseIcuTokens = (tokens) => {
  let index = 0
  return tokens.map((token) => {
    const value = {
      type: token[0],
      text: token[1],
      start: index,
      end: index + token[1].length,
    }
    index = index + token[1].length
    return value
  })
}
