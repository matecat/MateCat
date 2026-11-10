// Display-only marks for characters a translator cannot otherwise see: the one table shared by the segment editor
// (editor/core/display.js) and the read-only SegmentView, so a segment looks the same open and closed. Nothing here
// changes the text. The styles are in css/sass/components/segment/SpecialCharMarks.scss.
//
// A mark is a span with an `aria-label` (its tooltip: the cattool tooltip, commons/_aria-label-tooltip.scss, owns
// `::before`/`::after` of every element that has one) whose visual parts are child elements that hold no text:
// `special-char-mark-label` draws `label` with its own `::before`, `special-char-mark-<shape>` draws an outline.
// `keepsWidth`: the mark holds the character itself, so it takes its real width and draws over it; every other mark
// stands for a character that has no width of its own (or that would break the line).

const space = (title, className, {label = '', shape = ''} = {}) => ({
  label,
  shape,
  className: `special-char-mark special-char-mark-space ${className}`,
  title,
  keepsWidth: true,
})
const zeroWidth = (title, className) => ({
  label: '',
  shape: '',
  className: `special-char-mark special-char-mark-zero-width ${className}`,
  title,
  keepsWidth: false,
})
const separator = (title, label) => ({
  label,
  shape: '',
  className: 'special-char-mark special-char-mark-separator',
  title,
  keepsWidth: false,
})

/**
 * Every special character that is always marked, character -> {label, shape, className, title, keepsWidth}. `label`
 * is the text the mark shows (none for a bar or an underline), `shape` the outline drawn over a space (`bracket`,
 * `box`), `title` its tooltip. The tab, the line break and the space are `TAB`, `LINE_BREAK` and `SPACE`.
 */
export const SPECIAL_CHARS = {
  ' ': space('Non-breaking space (U+00A0)', 'special-char-nbsp', {
    label: '°', // the nbsp tag placeholder of the cattool
  }),
  ' ': space('Narrow non-breaking space (U+202F)', 'special-char-narrow-nbsp', {
    label: '°',
    shape: 'bracket',
  }),
  ' ': space('En space (U+2002)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Em space (U+2003)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Three-per-em space (U+2004)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Four-per-em space (U+2005)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Six-per-em space (U+2006)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Figure space (U+2007)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Punctuation space (U+2008)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Thin space (U+2009)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space('Hair space (U+200A)', 'special-char-typographic-space', {
    shape: 'bracket',
  }),
  ' ': space(
    'Medium mathematical space (U+205F)',
    'special-char-typographic-space',
    {shape: 'bracket'},
  ),
  '　': space('Ideographic space (U+3000)', 'special-char-ideographic-space', {
    shape: 'box',
  }),
  '⁠': zeroWidth('Word joiner (U+2060)', 'special-char-word-joiner'), // the cattool word joiner tag: a blue bar
  '﻿': zeroWidth('Zero-width non-breaking space (U+FEFF)', 'special-char-bom'),
  '​': zeroWidth('Zero-width space (U+200B)', 'special-char-zwsp'),
  '‌': zeroWidth('Zero-width non-joiner (U+200C)', 'special-char-zwnj'),
  '‍': zeroWidth('Zero-width joiner (U+200D)', 'special-char-zwj'),
  '­': zeroWidth('Soft hyphen (U+00AD)', 'special-char-soft-hyphen'),
  ' ': separator('Line separator (U+2028)', 'LS'),
  ' ': separator('Paragraph separator (U+2029)', 'PS'),
}

/**
 * An ordinary space, marked only with the user's show whitespace option. No tooltip: one on every space of the text
 * would get in the way of reading it.
 */
export const SPACE = space(null, 'special-char-space', {label: '·'})

/** The tab ("\t", the cattool tab tag): its arrow at the start of the tab's width. */
export const TAB = {
  label: '⇥',
  shape: '',
  className: 'special-char-mark special-char-mark-tab',
  title: 'Tab (U+0009)',
  keepsWidth: true,
}

/** The end-of-line mark of a line break ("\n", the cattool line feed tag): the glyph of the cattool `tag-lf` chip. */
export const LINE_BREAK = {
  label: '↵',
  shape: '',
  className: 'special-char-mark special-char-mark-line-break',
  title: 'Line break',
  keepsWidth: false,
}

/**
 * The tags of a segment string that stand for one character, which the editor holds as that character and both
 * views show as its mark, never as a tag chip. The carriage return is not one: the editor keeps it a tag.
 */
export const CHARACTER_TAGS = {
  lineFeed: '\n',
  nbsp: ' ',
  tab: '\t',
  wordJoiner: '⁠',
}

/** The mark of `ch`, or null when it has none. The space has one only with `showWhitespace`. */
export const specialCharMark = (ch, {showWhitespace = false} = {}) => {
  if (ch === '\n') return LINE_BREAK
  if (ch === '\t') return TAB
  if (ch === ' ') return showWhitespace ? SPACE : null
  return SPECIAL_CHARS[ch] ?? null
}

const escape = (ch) => `\\u${ch.charCodeAt(0).toString(16).padStart(4, '0')}`

/**
 * A regular expression character class (as source) that matches every character `specialCharMark` marks with the
 * same `showWhitespace`.
 */
export const specialCharsPattern = ({showWhitespace = false} = {}) =>
  `[${[
    '\t',
    '\n',
    ...Object.keys(SPECIAL_CHARS),
    ...(showWhitespace ? [' '] : []),
  ]
    .map(escape)
    .join('')}]`
