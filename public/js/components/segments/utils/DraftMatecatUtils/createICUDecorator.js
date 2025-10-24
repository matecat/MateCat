import * as DraftMatecatConstants from './editorConstants'
import parse from 'format-message-parse'
import {IcuHighlight} from '../../IcuHighlight'
import {isEqual} from 'lodash'
import updateOffsetBasedOnEditorState from './updateOffsetBasedOnEditorState'
export const createICUDecorator = (tokens = [], isTarget = true) => {
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
      isTarget,
    },
  }
}
function validateICUMessage(locale, text) {
  let tree
  try {
    tree = parse(text)
  } catch (err) {
    return [
      {
        node: {type: 'syntax', varName: null},
        issues: [{message: `Syntax error: ${err.message}`}],
      },
    ]
  }

  const results = []

  const nodes = Array.isArray(tree)
    ? tree.filter((n) => Array.isArray(n) && typeof n[1] === 'string')
    : []
  console.log('nodes', nodes)
  nodes.forEach((node) => {
    const varName = node[0]
    const type = node[1]
    const options = node[3] || {}
    const keys = Object.keys(options)
    const blockIssues = []

    /* ---- PLURAL / SELECTORDINAL ---- */
    if (type === 'plural' || type === 'selectordinal') {
      const ruleType = type === 'selectordinal' ? 'ordinal' : 'cardinal'
      const pr = new Intl.PluralRules(locale, {type: ruleType})
      const validCats = new Set(pr.resolvedOptions().pluralCategories)

      // invalid categories
      keys.forEach((k) => {
        if (!validCats.has(k) && k !== 'other' && !k.startsWith('=')) {
          blockIssues.push({
            message: `Invalid category '${k}' in ${type} block for locale '${locale}'.`,
          })
        }
      })

      // missing required categories
      ;[...validCats].forEach((cat) => {
        if (!keys.includes(cat)) {
          blockIssues.push({
            message: `Missing required category '${cat}' in ${type} block for locale '${locale}'.`,
          })
        }
      })

      // must include 'other'
      if (!keys.includes('other')) {
        blockIssues.push({
          message: `Each ${type} block must include 'other'.`,
        })
      }
    }

    /*/!* ---- SELECT (semantic check) ---- *!/
    if (type === 'select') {
      keys.forEach((k) => {
        if (['plural', 'select', 'selectordinal'].includes(k)) {
          const value = options[k]
          const maybeNode = Array.isArray(value[0]) ? value[0] : null
          if (!maybeNode || typeof maybeNode[1] !== 'string') {
            blockIssues.push({
              message: `Key '${k}' in select block '${varName}' is used as a plain key, not as a nested block.`,
            })
          }
        }
      })

      if (!keys.includes('other')) {
        blockIssues.push({
          message: `Each select block must include 'other'.`,
        })
      }
    }*/

    if (blockIssues.length) {
      const pattern = new RegExp(`\\{\\s*${varName}\\s*,\\s*${type}`)
      const match = pattern.exec(text)
      const start = match ? match.index + 1 : 0
      let end = start + varName.length

      results.push({
        node: {varName, type, start, end},
        issues: blockIssues,
      })
    }
  })
  console.log('results', results)
  return results
}
export const createIcuTokens = (editorState, locale) => {
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
        message: [e.message],
        key: block.getKey(),
      }
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
            token.message = [error.message]
          }
          return token
        })
      } else {
        blockTokens.push(error)
      }
    } else {
      const warningIssues = validateICUMessage(locale, text)
      if (warningIssues.length > 0) {
        warningIssues.forEach((issue) => {
          const node = issue.node
          blockTokens = blockTokens.map((token) => {
            if (token.start === node.start && token.text === node.varName) {
              token.type = 'error'
              token.message = issue.issues.map((i) => i.message)
            }
            return token
          })
        })
      }
    }
    updatedTokens = updatedTokens.concat(blockTokens)
  })
  return updateOffsetBasedOnEditorState(editorState, updatedTokens)
}

export const isEqualICUTokens = (tokens, otherTokens) => {
  const filterTokensFn = (token) => token.type !== 'text'
  return isEqual(
    tokens.filter(filterTokensFn),
    otherTokens.filter(filterTokensFn),
  )
}
