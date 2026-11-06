import {Base64} from 'js-base64'
import diff_match_patch from 'diff-match-patch'
import {tagSignatures} from '../segments/utils/DraftMatecatUtils/tagModel'
import {
  createTagNumberer,
  getGEquivText,
} from '../segments/utils/DraftMatecatUtils/compressibleTagUtils'

/**
 * Turns a cattool segment string (layer 2: XLIFF tags as markup, text
 * HTML-encoded, `##$_XX$##` placeholders) into display tokens.
 *
 * The rules mirror `transformTagsToHtml` in DraftMatecatUtils/tagUtils.js,
 * which renders the same string as an HTML string: same signatures, same
 * order, same numbering, same `<g>` id pairing. That function replaces one
 * signature at a time on the growing HTML, so a later signature also matches
 * inside the content of a tag an earlier one produced (e.g. a space inside a
 * ph's equiv-text becomes a space placeholder). To reproduce it, each tag is
 * replaced here by a marker that wraps its content and is bounded by `<`/`>`
 * like the span it stands for, so every later regex sees the same context.
 */

// The signatures whose matches can be compressible (see compressibleTagUtils).
const NUMBERED_TAG_KEYS = ['ph', 'g', 'gCl']
const ZWSP = '​'
const MARK_OPEN = ''
const MARK_CLOSE = ''
const MARKER_RE = /<(\d+)>|<>/g
// What the HTML parser would take as an element tag in a text run.
const MARKUP_RE = /<\/?[A-Za-z][^>]*>/g
const NAMED_ENTITIES = {
  lt: '<',
  gt: '>',
  amp: '&',
  quot: '"',
  apos: "'",
  nbsp: ' ',
}
// lt/gt/amp/quot/nbsp are decoded even without the semicolon, as the HTML
// parser does in text; apos is not a legacy entity and needs it.
const ENTITY_RE =
  /&(?:#(\d+);?|#[xX]([0-9a-fA-F]+);?|(lt|gt|amp|quot|nbsp);?|(apos);)/g

const fromCodePoint = (code) =>
  code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : '�'

/**
 * Decode an HTML text fragment to the text a browser would show: markup
 * dropped, entities decoded, CR/CRLF normalised to LF. The result is plain
 * text, rendered by React as text: nothing in it can become markup.
 */
export const decodeHtmlText = (html) =>
  html
    .replace(MARKUP_RE, '')
    .replace(ENTITY_RE, (match, dec, hex, named, apos) => {
      if (dec) return fromCodePoint(parseInt(dec, 10))
      if (hex) return fromCodePoint(parseInt(hex, 16))
      return NAMED_ENTITIES[named || apos]
    })
    .replace(/\r\n?/g, '\n')

// Equiv-text is HTML-escaped text; its brackets are forced to entities.
const escapeAngleBrackets = (text) =>
  text.replace(/</g, '&lt').replace(/>/g, '&gt')

const styleFor = (signature, isRtl) =>
  isRtl && signature.styleRTL ? signature.styleRTL : signature.style

// transformTagsToHtml replaces one signature at a time; numbering must follow
// document order across ph, g and </g>, so it is computed up front.
const numberTagsInDocumentOrder = (text) => {
  const found = []
  NUMBERED_TAG_KEYS.forEach((key) => {
    const signature = tagSignatures[key]
    if (!signature) return
    const {regex} = signature
    const globalRegex = new RegExp(
      regex.source,
      regex.flags.includes('g') ? regex.flags : regex.flags + 'g',
    )
    let match
    while ((match = globalRegex.exec(text)) !== null) {
      found.push({key, offset: match.index, encodedText: match[0]})
    }
  })
  found.sort((a, b) => a.offset - b.offset)
  const numberTag = createTagNumberer()
  const queues = Object.fromEntries(NUMBERED_TAG_KEYS.map((key) => [key, []]))
  found.forEach(({key, encodedText}) =>
    queues[key].push(numberTag(encodedText)),
  )
  return queues
}

// Replace every tag with a marker wrapping its (HTML) content.
const markTags = (segment, isRtl) => {
  const numbers = numberTagsInDocumentOrder(segment)
  const tags = []
  const wrap = (tag, content) => {
    tags.push(tag)
    return `<${MARK_OPEN}${tags.length - 1}>${content}<${MARK_CLOSE}>`
  }
  const wrapNumbered = (key, raw, {index, role}, content) =>
    wrap(
      {
        name: key,
        className:
          'tag small ' +
          styleFor(tagSignatures.ph, isRtl) +
          ' tag-numbered' +
          (role === 'open' || role === 'close' ? ' tag-paired-' + role : ''),
        index,
        role,
        raw,
      },
      role === 'close' ? '' : content,
    )

  let text = segment
  for (const key in tagSignatures) {
    const signature = tagSignatures[key]
    if (!signature) continue
    const {placeholderRegex, regex, decodeNeeded, selfClosing, placeholder} =
      signature
    const className = 'tag small ' + styleFor(signature, isRtl)
    if (placeholderRegex) {
      const globalRegex = new RegExp(
        placeholderRegex.source,
        placeholderRegex.flags + 'g',
      )
      text = text.replace(globalRegex, (match, captured) => {
        const tagText = decodeNeeded
          ? escapeAngleBrackets(Base64.decode(captured))
          : selfClosing
            ? captured
            : match
        const numbered = numbers[key]?.shift()
        if (numbered) {
          return wrapNumbered(
            key,
            match,
            numbered,
            signature.type === 'g'
              ? escapeAngleBrackets(getGEquivText(match))
              : tagText,
          )
        }
        return wrap({name: key, className, raw: match, id: captured}, tagText)
      })
    } else if (regex) {
      text = text.replace(new RegExp(regex), (match) => {
        const tagText = placeholder ? placeholder : match
        const numbered = numbers[key]?.shift()
        if (numbered) return wrapNumbered(key, match, numbered, tagText)
        return wrap({name: key, className, raw: match}, tagText)
      })
    }
  }
  return {text, tags}
}

// Marked string -> tree of {html} text nodes and tag nodes with children.
const parseMarked = (text, tags) => {
  const root = {children: []}
  const stack = [root]
  let last = 0
  const pushText = (end) => {
    if (end > last) {
      stack[stack.length - 1].children.push({html: text.slice(last, end)})
    }
  }
  let match
  MARKER_RE.lastIndex = 0
  while ((match = MARKER_RE.exec(text)) !== null) {
    pushText(match.index)
    last = match.index + match[0].length
    if (match[1] !== undefined) {
      const node = {...tags[Number(match[1])], children: []}
      stack[stack.length - 1].children.push(node)
      stack.push(node)
    } else if (stack.length > 1) {
      stack.pop()
    }
  }
  pushText(text.length)
  return root.children
}

const walkTags = (nodes, visit) =>
  nodes.forEach((node) => {
    if (node.html !== undefined) return
    visit(node)
    walkTags(node.children, visit)
  })

/**
 * matchTag at the end of transformTagsToHtml: a plain `<g>` shows its id, and
 * a plain `</g>` shows the id of the nearest preceding `<g>` still open, or
 * `?` when there is none. Numbered g tags no longer carry markup and are not
 * part of the pairing.
 */
const matchGTags = (nodes) => {
  const openings = []
  const closings = []
  walkTags(nodes, (node) => {
    if (node.index !== undefined) return
    if (node.name === 'g') openings.push(node)
    else if (node.name === 'gCl') closings.push(node)
  })
  if (!openings.length && !closings.length) return
  const order = new Map()
  let position = 0
  walkTags(nodes, (node) => order.set(node, position++))
  const closed = new Set()
  closings.forEach((closing) => {
    let id = '?'
    for (let i = openings.length - 1; i >= 0; i--) {
      const opening = openings[i]
      if (order.get(opening) < order.get(closing) && !closed.has(opening)) {
        closed.add(opening)
        id = opening.id
        break
      }
    }
    closing.children = [{html: ZWSP + id + ZWSP}]
  })
  openings.forEach((opening) => {
    opening.children = [{html: ZWSP + opening.id + ZWSP}]
  })
}

const flattenText = (tokens) =>
  tokens
    .map((token) =>
      token.type === 'text'
        ? token.text
        : (token.index !== undefined && token.index !== null
            ? String(token.index + 1)
            : '') + token.label,
    )
    .join('')

const toTokens = (nodes) => {
  const tokens = []
  nodes.forEach((node) => {
    if (node.html !== undefined) {
      const text = decodeHtmlText(node.html)
      if (!text) return
      const previous = tokens[tokens.length - 1]
      if (previous?.type === 'text') previous.text += text
      else tokens.push({type: 'text', text})
      return
    }
    const children = toTokens(node.children)
    const token = {
      type: 'tag',
      name: node.name,
      className: node.className,
      label: flattenText(children),
      raw: node.raw,
    }
    if (node.index !== undefined) {
      token.index = node.index
      token.role = node.role
    }
    if (children.some((child) => child.type === 'tag')) {
      token.children = children
    }
    tokens.push(token)
  })
  return tokens
}

/**
 * @param {string} segment cattool segment string
 * @param {{isRtl?: boolean}} [options]
 * @returns {Array<{type:'text', text:string} | {type:'tag', name:string,
 *   className:string, label:string, index?:number, role?:string, raw:string,
 *   children?:Array}>} tokens in document order. `label` is the tag's visible
 *   content; `children` is present only when other tags were matched inside
 *   that content (today's renderer nests them), and then `label` is their
 *   flattened text.
 */
export const tokenize = (segment, {isRtl = false} = {}) => {
  if (!segment) return []
  try {
    const {text, tags} = markTags(segment, !!isRtl)
    const tree = parseMarked(text, tags)
    matchGTags(tree)
    return toTokens(tree)
  } catch (e) {
    console.error('Error parsing tags in SegmentView tokenize', e)
    return [{type: 'text', text: decodeHtmlText(segment)}]
  }
}

/**
 * The text the view shows for a segment (what a copy of the whole rendered
 * segment yields): text, tag labels and tag numbers.
 */
export const visibleText = (segment, options) =>
  flattenText(tokenize(segment, options))

const tagKey = (token) =>
  JSON.stringify([
    token.className,
    token.index ?? null,
    token.label,
    token.children ?? null,
  ])

export const DIFF_DELETE = 'delete'
export const DIFF_INSERT = 'insert'
export const DIFF_EQUAL = 'equal'
const OPS = {[-1]: DIFF_DELETE, 1: DIFF_INSERT, 0: DIFF_EQUAL}

/**
 * Token-level diff from `oldSegment` to `newSegment`. Every tag is mapped to
 * a single private-use character before diffing, so a tag is never split: it
 * is either equal, deleted or inserted as a whole. Tags are equal when they
 * render the same.
 * @returns {Array<{op:'equal'|'delete'|'insert', tokens:Array}>}
 */
export const diffTokens = (oldTokens, newTokens) => {
  const used = new Set()
  ;[...oldTokens, ...newTokens].forEach((token) => {
    if (token.type === 'text') for (const ch of token.text) used.add(ch)
  })
  const charByKey = new Map()
  const tagByChar = new Map()
  let next = 0xe000
  const charFor = (token) => {
    const key = tagKey(token)
    if (!charByKey.has(key)) {
      while (used.has(String.fromCharCode(next))) next++
      if (next > 0xf8ff) throw new Error('Too many distinct tags to diff')
      const ch = String.fromCharCode(next++)
      charByKey.set(key, ch)
    }
    const ch = charByKey.get(key)
    if (!tagByChar.has(ch)) tagByChar.set(ch, token)
    return ch
  }
  const encode = (tokens) =>
    tokens
      .map((token) => (token.type === 'text' ? token.text : charFor(token)))
      .join('')
  const oldText = encode(oldTokens)
  const newText = encode(newTokens)

  const dmp = new diff_match_patch()
  const diffs = dmp.diff_main(oldText, newText)
  dmp.diff_cleanupSemantic(diffs)

  return diffs.map(([op, chunk]) => {
    const tokens = []
    let text = ''
    for (const ch of chunk) {
      const tag = tagByChar.get(ch)
      if (tag) {
        if (text) tokens.push({type: 'text', text})
        text = ''
        tokens.push(tag)
      } else {
        text += ch
      }
    }
    if (text) tokens.push({type: 'text', text})
    return {op: OPS[op], tokens}
  })
}
