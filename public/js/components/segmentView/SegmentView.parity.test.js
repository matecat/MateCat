import React from 'react'
import {render} from '@testing-library/react'
import {Base64} from 'js-base64'
import SegmentView from './SegmentView'
import {transformTagsToHtml} from '../segments/utils/DraftMatecatUtils/tagUtils'
import {
  initTagSignature,
  setTagSignatureMiddleware,
} from '../segments/utils/DraftMatecatUtils/tagModel'

// Regression guarantee for replacing `transformTagsToHtml` + innerHTML with
// <SegmentView>: for real segment strings both must give the same text and the
// same tag elements (classes, attributes, inner structure, labels).
//
// Comparing two DOM trees needs direct node access; it is limited to the two
// helpers below.

const ph = (ctype, html, id = 'mtc_1', extra = '') =>
  `<ph id="${id}" ctype="${ctype}"${extra} equiv-text="base64:${Base64.encode(html)}"/>`

const SEGMENTS = [
  'Hello world',
  'a b&amp;c &lt;b&gt; &quot;q&quot; &apos;s&apos; &#169; &#xA9;',
  'test <g id="1">tag</g> end',
  'a<g id="7">b</g>c</g>',
  '<g id="1">outer <g id="2">inner</g> tail</g>',
  'translated end<g id="1"></g>',
  'self <g id="5"/> closed',
  '<x id="2"/><bx id="3"/>text<ex id="3"/>',
  'a##$_0A$##b##$_0D$##c##$_09$##d##$_A0$##e',
  'value ##$_A0$## end',
  'Opt-in##$_SPLIT$## data',
  'word⁠joiner',
  'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; &amp;gt; <g id="2"/> ' +
    ph('x-html', '&lt;p&gt;') +
    ' ' +
    ph('x-html', '&lt;strong&gt;', 'mtc_2') +
    ' ' +
    ph('x-html', '&lt;/strong&gt;', 'mtc_3') +
    'pippoL&apos; placeholder &nbsp; elle-même',
  'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <p> <strong> </strong>pippoL&apos; placeholder &nbsp; elle-même',
  'They started booking stays through Airbnb.&amp;nbsp;&amp;nbsp;' +
    ph('x-html', '&lt;br /&gt;') +
    ph('x-html', '&lt;br /&gt;', 'mtc_2') +
    'At first, Maria paid',
  // XLIFF 2 pc without and with dataRef, compressible (numbered)
  ph('x-original_pc_open', '&lt;p&gt;') +
    'para' +
    ph('x-original_pc_close', '&lt;/p&gt;', 'mtc_2'),
  ph('x-pc_open_data_ref', '&lt;b class="x"&gt;', 'source1_1') +
    'bold' +
    ph('x-pc_close_data_ref', '&lt;/b&gt;', 'source1_2') +
    ' and ' +
    ph('x-original_x', '[#$dp4]', 'mtc_3'),
  // compressible g (equiv-text) next to a plain g and an orphan close
  '<g id="1" equiv-text="base64:WyMkZHA0XQ==">one</g> <g id="7">Reset</g></g>',
  '<g id="2" equiv-text="base64:PGI+">nested <g id="3">in</g></g>',
  'ph without equiv <ph id="1"/> stays out',
  ph('x-html', 'a&amp;b &lt;i&gt;') + ' tail  double  spaces',
  '&lt;script&gt;alert(1)&lt;/script&gt; text',
  '',
]

const describeTag = (el) => ({
  className: el.className,
  contentEditable: el.getAttribute('contenteditable'),
  text: el.textContent,
  // eslint-disable-next-line testing-library/no-node-access
  children: Array.from(el.children).map((child) => [
    child.tagName,
    child.className,
    child.getAttribute('data-text'),
  ]),
})

// The first intentional difference: the tags that stand for a character (line feed, tab, nbsp, word joiner) and the
// space placeholder are chips in transformTagsToHtml, and in SegmentView the display-only marks of the CodeMirror
// editor (utils/segmentText/specialChars.js), so a segment looks the same open and closed. Both sides are reduced to
// the character itself; everything else must still match.
const CHAR_OF_CHIP = {
  'tag-lf': '\n',
  'tag-tab': '\t',
  'tag-nbsp': '\u00a0',
  'tag-word-joiner': '\u2060',
  'tag-space-placeholder': ' ',
}
const CHIP_SELECTOR = Object.keys(CHAR_OF_CHIP)
  .map((name) => `.tag.${name}`)
  .join(', ')

/* eslint-disable testing-library/no-node-access -- reducing two DOM trees to what they must have in common */
// Only the chips of the text: inside the label of a chip nothing becomes a mark.
const chipsToCharacters = (root) => {
  for (const el of root.querySelectorAll(CHIP_SELECTOR)) {
    if (el.parentElement.closest('.tag')) continue
    const name = Object.keys(CHAR_OF_CHIP).find((n) => el.classList.contains(n))
    el.replaceWith(CHAR_OF_CHIP[name])
  }
}

// The text of a mark is the character it stands for: its drawing holds none.
const marksToCharacters = (root) => {
  for (const el of root.querySelectorAll('.special-char-mark')) {
    el.replaceWith(el.textContent)
  }
}

// The second intentional difference: the label of a chip that has no number sits in a `span.tag-text` (the element
// Tag.scss trims with an ellipsis), where transformTagsToHtml puts it right in the chip.
const unwrapTagText = (root) => {
  for (const el of root.querySelectorAll('.tag > .tag-text')) {
    el.replaceWith(...el.childNodes)
  }
}
/* eslint-enable testing-library/no-node-access */

const snapshotOf = (root) => ({
  text: root.textContent,
  // eslint-disable-next-line testing-library/no-node-access
  tags: Array.from(root.querySelectorAll('.tag')).map(describeTag),
})

const legacySnapshot = (segment, isRtl) => {
  const div = document.createElement('div')
  div.innerHTML = transformTagsToHtml(segment, isRtl)
  chipsToCharacters(div)
  return snapshotOf(div)
}

const viewSnapshot = (segment, isRtl) => {
  const {container, unmount} = render(
    <SegmentView segment={segment} isRtl={isRtl} />,
  )
  marksToCharacters(container)
  unwrapTagText(container)
  const snapshot = snapshotOf(container)
  unmount()
  return snapshot
}

describe('SegmentView parity with transformTagsToHtml', () => {
  test.each(SEGMENTS.map((s) => [s]))('LTR: %s', (segment) => {
    expect(viewSnapshot(segment, false)).toEqual(legacySnapshot(segment, false))
  })

  test.each(SEGMENTS.map((s) => [s]))('RTL: %s', (segment) => {
    expect(viewSnapshot(segment, true)).toEqual(legacySnapshot(segment, true))
  })
})

// The cattool turns the space placeholder off unless the user enabled
// show_whitespace; both renderers must follow the same switch.
describe('SegmentView parity with the space placeholder turned off', () => {
  beforeAll(() => initTagSignature({show_whitespace: 0}))
  afterAll(() => setTagSignatureMiddleware('space', () => undefined))

  test.each(SEGMENTS.map((s) => [s]))('%s', (segment) => {
    expect(viewSnapshot(segment, false)).toEqual(legacySnapshot(segment, false))
  })
})
