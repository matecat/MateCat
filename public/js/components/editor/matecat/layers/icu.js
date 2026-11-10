// ICU message syntax in the source and the target (createICUDecorator): every token that is not plain text is marked. The tokens are
// the ones format-message-parse gives for the document text, so their offsets are document positions.
import parse from 'format-message-parse'

export const ICU_CLASS = 'icuItem'

// Plural and select blocks checked against the locale's plural rules: missing or invalid categories are errors.
const validateICUMessage = (locale, text) => {
  const tree = parse(text)
  const results = []
  const visitNode = (node) => {
    const [varName, type, children, options = {}] = node
    const keys = Object.keys(options)
    const issues = []
    if (type === 'plural' || type === 'selectordinal') {
      const rules = new Intl.PluralRules(locale, {
        type: type === 'selectordinal' ? 'ordinal' : 'cardinal',
      })
      const categories = new Set(rules.resolvedOptions().pluralCategories)
      keys.forEach((k) => {
        if (!categories.has(k) && k !== 'other' && !k.startsWith('=')) {
          issues.push(
            `Invalid category '${k}' in ${type} block for locale '${locale}'.`,
          )
        }
      })
      categories.forEach((cat) => {
        if (!keys.includes(cat)) {
          issues.push(
            `Missing required category '${cat}' in ${type} block for locale '${locale}'.`,
          )
        }
      })
      if (!keys.includes('other')) {
        issues.push(`Each ${type} block must include 'other'.`)
      }
    }
    results.push({node: {varName, type}, issues})
    Object.values(children || {}).forEach((child) => {
      if (Array.isArray(child)) {
        child.forEach((n) => Array.isArray(n) && visitNode(n))
      }
    })
  }
  tree
    .filter((n) => Array.isArray(n) && typeof n[1] === 'string')
    .forEach(visitNode)
  return results
}

/**
 * The ICU tokens of `text`: `{type, text, start, end, message?}`. A syntax error is an `error` token at the point the
 * parser stopped (or the last token, when the message ends early); a plural/select block failing the locale's rules
 * becomes an `error` token carrying the messages.
 */
export const icuTokens = (text, locale) => {
  const raw = []
  let error
  try {
    parse(text, {tokens: raw})
  } catch (e) {
    error = {
      type: 'error',
      text: e.found,
      start: e.column,
      end: e.column + e.found.length,
      message: [e.message],
    }
  }
  let index = 0
  let tokens = raw.map(([kind, value]) => {
    const token = {
      type: kind === 'type' ? value : kind,
      text: value,
      start: index,
      end: index + value.length,
    }
    index += value.length
    return token
  })
  if (error) {
    if (error.text === 'end of message pattern') {
      tokens = tokens.map((token) =>
        token.end === error.start
          ? {...token, type: 'error', message: error.message}
          : token,
      )
    } else {
      tokens.push(error)
    }
    return tokens
  }
  validateICUMessage(locale, text).forEach(({node, issues}) => {
    const i = tokens.findIndex((t) => t.type === node.type && !t.warning)
    if (i < 0) return
    tokens[i] = {
      ...tokens[i],
      type: issues.length ? 'error' : tokens[i].type,
      warning: true,
      message: issues,
    }
  })
  return tokens
}

export const ICU_ERROR_CLASS = 'icuItem-error'

/**
 * The ICU layer of a document. The source shows no error styling; the target (`showErrors`) marks the errors and keeps
 * their messages for the tooltip, as IcuHighlight does with `isTarget`.
 */
export const icuRanges = (state, locale, {showErrors = false} = {}) => {
  const text = state.doc.toString()
  return icuTokens(text, locale)
    .filter(
      (t) =>
        t.type !== 'text' &&
        t.end <= text.length &&
        text.slice(t.start, t.end) === t.text,
    )
    .map(({start, end, type, message}) =>
      showErrors && type === 'error'
        ? {
            from: start,
            to: end,
            className: `${ICU_CLASS} ${ICU_ERROR_CLASS}`,
            data: {type: 'icu', messages: message ?? []},
          }
        : {from: start, to: end, className: ICU_CLASS},
    )
}
