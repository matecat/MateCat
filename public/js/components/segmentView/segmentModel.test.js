import {Base64} from 'js-base64'
import {diffTokens, tokenize, visibleText} from './segmentModel'
import {
  initTagSignature,
  setTagSignatureMiddleware,
} from '../segments/utils/DraftMatecatUtils/tagModel'

// The space placeholder is a user option (show_whitespace), off by default.
const showWhitespace = (on) => initTagSignature({show_whitespace: on ? 1 : 0})
beforeEach(() => showWhitespace(false))
afterAll(() => setTagSignatureMiddleware('space', () => undefined))

const ph = (ctype, html, id) =>
  `<ph id="${id}" ctype="${ctype}" equiv-text="base64:${Base64.encode(html)}"/>`

const tags = (tokens) => tokens.filter((t) => t.type === 'tag')
const ZWSP = '​'

describe('tokenize', () => {
  test('returns no tokens for an empty or missing segment', () => {
    expect(tokenize('')).toEqual([])
    expect(tokenize(null)).toEqual([])
  })

  test('decodes entities in text so markup in the segment stays text', () => {
    expect(tokenize('&lt;script&gt;x&lt;/script&gt; &amp;amp; &quot;')).toEqual(
      [{type: 'text', text: '<script>x</script> &amp; "'}],
    )
  })

  test('drops raw XLIFF markup that no tag signature renders', () => {
    // A ph with no equiv-text has nothing to show: the browser parsed it as
    // an empty element, so only the surrounding text is visible.
    expect(visibleText('a<ph id="1"/>b')).toBe('ab')
  })

  test('shows a ph as its decoded equiv-text, with the ph style', () => {
    const [tag] = tags(tokenize(`a ${ph('x-html', '&lt;br/&gt;', 'm1')}b`))
    expect(tag).toMatchObject({
      name: 'ph',
      label: '<br/>',
      className: 'tag small tag-selfclosed tag-ph',
    })
    expect(tag.raw).toMatch(/^<ph id="m1"/)
  })

  test('labels <g> with its id and pairs each </g> with the nearest open <g>', () => {
    const labels = tags(tokenize('<g id="1">a <g id="2">b</g> c</g></g>')).map(
      (t) => t.label,
    )
    expect(labels).toEqual([
      `${ZWSP}1${ZWSP}`,
      `${ZWSP}2${ZWSP}`,
      `${ZWSP}2${ZWSP}`,
      `${ZWSP}1${ZWSP}`,
      `${ZWSP}?${ZWSP}`,
    ])
  })

  test('swaps the open and close <g> styles for RTL', () => {
    const segment = '<g id="1">a</g>'
    expect(tags(tokenize(segment)).map((t) => t.className)).toEqual([
      'tag small tag-open',
      'tag small tag-close',
    ])
    expect(
      tags(tokenize(segment, {isRtl: true})).map((t) => t.className),
    ).toEqual(['tag small tag-close', 'tag small tag-open'])
  })

  test('numbers compressible pc tags in document order, open and close sharing the number', () => {
    const segment =
      ph('x-original_pc_open', '&lt;b&gt;', 'm1') +
      'bold' +
      ph('x-original_pc_close', '&lt;/b&gt;', 'm2') +
      ph('x-pc_open_data_ref', '&lt;i&gt;', 's1_1') +
      'it' +
      ph('x-pc_close_data_ref', '&lt;/i&gt;', 's1_2')
    expect(
      tags(tokenize(segment)).map(({index, role, label}) => ({
        index,
        role,
        label,
      })),
    ).toEqual([
      {index: 0, role: 'open', label: '<b>'},
      {index: 0, role: 'close', label: ''},
      {index: 1, role: 'open', label: '<i>'},
      {index: 1, role: 'close', label: ''},
    ])
    expect(visibleText(segment)).toBe('1<b>bold12<i>it2')
  })

  test('a compressible <g> takes a number and its equiv-text, a plain <g> keeps its id', () => {
    const equiv = Base64.encode('[#$dp4]')
    const result = tags(
      tokenize(`<g id="1" equiv-text="base64:${equiv}">a</g><g id="7">b</g>`),
    )
    expect(result[0]).toMatchObject({index: 0, role: 'open', label: '[#$dp4]'})
    expect(result[0].className).toContain('tag-numbered tag-paired-open')
    expect(result[1]).toMatchObject({index: 0, role: 'close'})
    expect(result[2].label).toBe(`${ZWSP}7${ZWSP}`)
    expect(result[2].index).toBeUndefined()
  })

  test('renders the special-character placeholders', () => {
    expect(
      tags(tokenize('a##$_A0$##b##$_09$##c##$_0A$##d')).map((t) => [
        t.name,
        t.label,
      ]),
    ).toEqual([
      ['nbsp', '°'],
      ['tab', '⇥'],
      ['lineFeed', '\n'],
    ])
  })

  test('nests the space placeholder inside a ph label, as the legacy renderer does', () => {
    showWhitespace(true)
    const [tag] = tags(tokenize(ph('x-html', 'a b', 'm1')))
    expect(tag.label).toBe('a·b')
    expect(tag.children.map((t) => t.type)).toEqual(['text', 'tag', 'text'])
  })
})

describe('diffTokens', () => {
  const diff = (oldSegment, newSegment) =>
    diffTokens(tokenize(oldSegment), tokenize(newSegment)).map(
      ({op, tokens}) => [
        op,
        tokens
          .map((t) => (t.type === 'text' ? t.text : `[${t.label}]`))
          .join(''),
      ],
    )

  test('reports identical segments as a single equal chunk', () => {
    expect(diff('a<x id="1"/>b', 'a<x id="1"/>b')).toEqual([['equal', 'a[1]b']])
  })

  test('marks text removed and added', () => {
    expect(diff('Ciao##$_A0$##mondo', 'Ciao##$_A0$##mondo tradotto')).toEqual([
      ['equal', 'Ciao[°]mondo'],
      ['insert', ' tradotto'],
    ])
  })

  test('never splits a tag: a changed tag is deleted and inserted whole', () => {
    const result = diffTokens(
      tokenize('a<x id="12"/>b'),
      tokenize('a<x id="13"/>b'),
    )
    expect(result.map(({op}) => op)).toEqual([
      'equal',
      'delete',
      'insert',
      'equal',
    ])
    expect(result[1].tokens).toEqual([
      expect.objectContaining({type: 'tag', label: '12'}),
    ])
    expect(result[2].tokens).toEqual([
      expect.objectContaining({type: 'tag', label: '13'}),
    ])
  })

  test('does not confuse a tag with private-use characters in the text', () => {
    const result = diffTokens(
      [{type: 'text', text: ''}],
      tokenize('<x id="1"/>'),
    )
    expect(result.map(({op}) => op)).toEqual(['delete', 'insert'])
    expect(result[0].tokens).toEqual([{type: 'text', text: ''}])
  })
})
