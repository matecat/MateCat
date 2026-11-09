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
// Strip until nothing is left to strip: one pass over `<<b>script>` would leave
// `<script>` behind.
const stripMarkup = (html) => {
  let previous
  let text = html
  do {
    previous = text
    text = text.replace(MARKUP_RE, '')
  } while (text !== previous)
  return text
}

export const decodeHtmlText = (html) =>
  stripMarkup(html)
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

/**
 * Stands for a tag in `markableText`: never part of a word nor of a search
 * term, so a match cannot run across a tag chip.
 */
export const OPAQUE_TAG = '￼'

// Whitespace chips stand for the character they show, so a mark can run
// across them as it runs across the character.
const markableChar = (token) =>
  token.name === 'space'
    ? token.raw
    : (tagSignatures[token.name]?.unicodeChar ?? OPAQUE_TAG)

const tokensMarkableText = (tokens) =>
  tokens
    .map((token) => (token.type === 'text' ? token.text : markableChar(token)))
    .join('')

/**
 * The text that `marks` offsets refer to: the decoded text of the segment,
 * with each tag as one character. Whitespace chips (space, nbsp, tab, line
 * breaks) are the character they stand for, every other tag is `OPAQUE_TAG`.
 * Tag labels are not part of it, so a mark never lands inside a chip.
 */
export const markableText = (segment, options) =>
  tokensMarkableText(tokenize(segment, options))

/**
 * Groups the tokens covered by `marks` ({start, end, className}, offsets in
 * `markableText`). Text is split at mark edges; a tag inside a mark is
 * wrapped whole. Empty and overlapped parts of marks are dropped.
 * @returns {Array} tokens, and {type:'mark', className, tokens} groups
 */
export const applyMarks = (tokens, marks) => {
  const ranges = marks
    .filter(({start, end}) => end > start)
    .sort((a, b) => a.start - b.start)
  const out = []
  let group = null
  let groupRange = null
  let r = 0
  const rangeAt = (at) => {
    while (ranges[r] && ranges[r].end <= at) r++
    return ranges[r]
  }
  const emit = (token, range) => {
    if (!range) {
      group = null
      out.push(token)
      return
    }
    if (!group || groupRange !== range) {
      group = {type: 'mark', className: range.className, tokens: []}
      groupRange = range
      out.push(group)
    }
    group.tokens.push(token)
  }
  let pos = 0
  tokens.forEach((token) => {
    if (token.type !== 'text') {
      const range = rangeAt(pos)
      emit(token, range && range.start <= pos ? range : null)
      pos++
      return
    }
    const {text} = token
    let i = 0
    while (i < text.length) {
      const range = rangeAt(pos + i)
      const inside = range && range.start <= pos + i
      const end = inside
        ? Math.min(text.length, range.end - pos)
        : range
          ? Math.min(text.length, range.start - pos)
          : text.length
      emit({type: 'text', text: text.slice(i, end)}, inside ? range : null)
      i = end
    }
    pos += text.length
  })
  return out
}

const OPEN_DELIMITER = '﷐'
const CLOSE_DELIMITER = '﷑'

/**
 * Turns the parts of `segment` wrapped in `open`…`close` (e.g. `#{`…`}#`)
 * into marks. Returns the segment without the delimiters and the marks on
 * it; a delimiter that has no pair, or sits inside a tag, marks nothing.
 * @returns {{segment: string, marks: Array<{start:number, end:number}>}}
 */
export const marksFromDelimiters = (segment, open, close, options) => {
  if (!segment) return {segment, marks: []}
  const strip = (replacement) =>
    segment.split(open).join(replacement[0]).split(close).join(replacement[1])
  const text = markableText(strip([OPEN_DELIMITER, CLOSE_DELIMITER]), options)
  const marks = []
  let removed = 0
  let start = null
  for (let i = 0; i < text.length; i++) {
    if (text[i] === OPEN_DELIMITER) {
      start = i - removed
      removed++
    } else if (text[i] === CLOSE_DELIMITER) {
      if (start !== null) marks.push({start, end: i - removed})
      start = null
      removed++
    }
  }
  return {segment: strip(['', '']), marks}
}

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

// Text tokens hold decoded text; the segment string stores it HTML-encoded.
const encodeText = (text) =>
  text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

// What a tag chip yields as plain text: whitespace chips the character they
// stand for, every other chip its number and label.
const plainTextOf = (token) => {
  if (token.name === 'space') return token.raw
  const unicodeChar = tagSignatures[token.name]?.unicodeChar
  if (unicodeChar) return unicodeChar
  return (
    (token.index !== undefined && token.index !== null
      ? String(token.index + 1)
      : '') + token.label
  ).replace(new RegExp(ZWSP, 'g'), '')
}

/**
 * The part of `segment` between `start` and `end`, offsets in
 * `markableText(segment)` (every tag one character). A tag is taken whole
 * when its character is in the range.
 * @returns {{raw: string, text: string}} `raw` is the slice as a segment
 *   string (tags as their markup, text HTML-encoded, whitespace chips as
 *   their placeholders); `text` is its plain text
 */
export const sliceSegment = (segment, start, end, options) => {
  let raw = ''
  let text = ''
  let pos = 0
  tokenize(segment, options).forEach((token) => {
    if (token.type === 'text') {
      const from = Math.max(start - pos, 0)
      const to = Math.min(end - pos, token.text.length)
      if (to > from) {
        const part = token.text.slice(from, to)
        raw += encodeText(part)
        text += part
      }
      pos += token.text.length
      return
    }
    if (pos >= start && pos < end) {
      raw += token.raw
      text += plainTextOf(token)
    }
    pos++
  })
  return {raw, text}
}

/**
 * The attribute that carries a segment slice (base64 of the raw string) in
 * the `text/html` flavour of a copy from a SegmentView.
 */
export const CLIPBOARD_ATTRIBUTE = 'data-matecat-segment'
const CLIPBOARD_ATTRIBUTE_RE = new RegExp(
  CLIPBOARD_ATTRIBUTE + '="([A-Za-z0-9+/=_-]*)"',
)

/**
 * The `text/html` flavour of a copy: one element carrying the raw slice in
 * `CLIPBOARD_ATTRIBUTE` and the plain text as content.
 */
export const segmentClipboardHtml = ({raw, text}) =>
  `<span ${CLIPBOARD_ATTRIBUTE}="${Base64.encode(raw)}">${encodeText(text)}</span>`

const MARKUP_TAG_KEYS = ['ph', 'gSc', 'g', 'gCl', 'bx', 'ex', 'x']

const isKnownTag = (markup) =>
  MARKUP_TAG_KEYS.some((key) => {
    const regex = tagSignatures[key]?.regex
    return (
      !!regex &&
      new RegExp(`^(?:${regex.source})$`, regex.flags.replace('g', '')).test(
        markup,
      )
    )
  })

/**
 * Reads the segment slice out of the `text/html` flavour of a paste.
 * The slice is untrusted (any page can put this attribute on the clipboard):
 * only markup that is exactly one of the segment's tags is kept, any other
 * markup and any stray bracket is encoded as text.
 * @returns {string|null} the slice as a segment string, or null when the html
 *   does not carry one
 */
export const readSegmentClipboard = (html) => {
  const match = html ? CLIPBOARD_ATTRIBUTE_RE.exec(html) : null
  if (!match) return null
  let raw
  try {
    raw = Base64.decode(match[1])
  } catch (e) {
    return null
  }
  return raw
    .split(/(<[^<>]*>)/)
    .map((part, i) =>
      i % 2 === 1 && isKnownTag(part)
        ? part
        : part.replace(/</g, '&lt;').replace(/>/g, '&gt;'),
    )
    .join('')
}
