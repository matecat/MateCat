import {Base64} from 'js-base64'
import {
  OPAQUE_TAG,
  applyMarks,
  readSegmentClipboard,
  segmentClipboardHtml,
  sliceSegment,
  diffTokens,
  markableText,
  marksFromDelimiters,
  tokenize,
  visibleText,
} from './segmentModel'
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

describe('markableText', () => {
  test('decodes the text and stands each tag for one opaque character', () => {
    expect(
      markableText(`a &amp; <g id="1">b</g>${ph('x-html', '<br>', 'p')}`),
    ).toBe(`a & ${OPAQUE_TAG}b${OPAQUE_TAG}${OPAQUE_TAG}`)
  })

  test('stands whitespace chips for the character they show', () => {
    expect(markableText('a##$_A0$##b##$_09$##c')).toBe('a\u00A0b\tc')
    showWhitespace(true)
    expect(markableText('a b')).toBe('a b')
  })
})

describe('applyMarks', () => {
  const marked = (segment, marks) =>
    applyMarks(tokenize(segment), marks).map((item) =>
      item.type === 'mark'
        ? {mark: item.className, text: visibleTextOf(item.tokens)}
        : visibleTextOf([item]),
    )
  const visibleTextOf = (tokens) =>
    tokens.map((t) => (t.type === 'text' ? t.text : `[${t.label}]`)).join('')

  test('splits text at the mark edges', () => {
    expect(marked('foo bar baz', [{start: 4, end: 7, className: 'm'}])).toEqual(
      ['foo ', {mark: 'm', text: 'bar'}, ' baz'],
    )
  })

  test('wraps a tag inside a mark whole, with the text around it', () => {
    showWhitespace(true)
    const items = marked('foo bar', [{start: 0, end: 7, className: 'm'}])
    expect(items).toHaveLength(1)
    expect(items[0].mark).toBe('m')
    expect(items[0].text).toMatch(/^foo\[.*\]bar$/)
  })

  test('counts a tag as one character for the offsets that follow it', () => {
    expect(
      marked('<x id="1"/>ab<x id="2"/>cd', [
        {start: 4, end: 5, className: 'm'},
      ]),
    ).toEqual(['[1]', 'ab', '[2]', {mark: 'm', text: 'c'}, 'd'])
  })

  test('keeps two adjacent marks apart and ignores empty ones', () => {
    expect(
      marked('abcd', [
        {start: 2, end: 4, className: 'b'},
        {start: 1, end: 1, className: 'empty'},
        {start: 0, end: 2, className: 'a'},
      ]),
    ).toEqual([
      {mark: 'a', text: 'ab'},
      {mark: 'b', text: 'cd'},
    ])
  })
})

describe('marksFromDelimiters', () => {
  test('turns delimited parts into marks on the stripped segment', () => {
    const {segment, marks} = marksFromDelimiters(
      'the #{cat}# &amp; <x id="1"/>#{dog}#',
      '#{',
      '}#',
    )
    expect(segment).toBe('the cat &amp; <x id="1"/>dog')
    const text = markableText(segment)
    expect(marks.map(({start, end}) => text.slice(start, end))).toEqual([
      'cat',
      'dog',
    ])
  })

  test('marks nothing for an unpaired delimiter', () => {
    expect(marksFromDelimiters('a }# b #{ c', '#{', '}#').marks).toEqual([])
  })
})

describe('sliceSegment', () => {
  const slice = (segment, start, end, options) =>
    sliceSegment(segment, start, end, options)

  test('gives a tag as its markup and text encoded as stored', () => {
    // markable text: "a&b", the x tag, "<c"; the x chip shows its id
    const segment = 'a&amp;b<x id="1"/>&lt;c'
    expect(slice(segment, 0, 6)).toEqual({raw: segment, text: 'a&b1<c'})
    expect(slice(segment, 1, 3).raw).toBe('&amp;b')
    expect(slice(segment, 4, 5).raw).toBe('&lt;')
  })

  test('takes a tag whole when its character is in the range', () => {
    const segment = 'ab<x id="7"/>cd'
    expect(slice(segment, 1, 3).raw).toBe('b<x id="7"/>')
    expect(slice(segment, 2, 3).raw).toBe('<x id="7"/>')
    expect(slice(segment, 3, 5).raw).toBe('cd')
  })

  test('gives whitespace chips back as their placeholders, and their character as text', () => {
    const segment = 'a##$_A0$##b##$_09$##c##$_0A$##d'
    expect(slice(segment, 0, 7)).toEqual({
      raw: segment,
      text: 'a\u00a0b\tc\nd',
    })
  })

  test('keeps a g pair and the text inside it, or just one side of it', () => {
    const segment = 'x<g id="1">bold</g>y'
    expect(slice(segment, 1, 7).raw).toBe('<g id="1">bold</g>')
    expect(slice(segment, 2, 7).raw).toBe('bold</g>')
    expect(slice(segment, 0, 3).raw).toBe('x<g id="1">b')
  })

  test('keeps a ph whole, equiv-text included, and shows its label as text', () => {
    const tag = ph('x-html', '&lt;br/&gt;', 'p1')
    const {raw, text} = slice(`a ${tag}b`, 2, 3)
    expect(raw).toBe(tag)
    expect(text).toContain('<br/>')
  })

  test('maps the same offsets in a right-to-left segment', () => {
    const segment = 'שלום<g id="1">עולם</g>'
    expect(slice(segment, 4, 10, {isRtl: true}).raw).toBe('<g id="1">עולם</g>')
  })

  test('gives nothing for an empty range', () => {
    expect(slice('abc', 1, 1)).toEqual({raw: '', text: ''})
  })
})

describe('segment clipboard', () => {
  const roundTrip = (raw) =>
    readSegmentClipboard(segmentClipboardHtml({raw, text: 'shown'}))

  test('reads back the slice a copy wrote', () => {
    const raw = `a &amp; <g id="1">é</g>${ph('x-html', '&lt;b&gt;', 'p')}##$_A0$##`
    expect(roundTrip(raw)).toBe(raw)
  })

  test('writes the plain text, encoded, as the element content', () => {
    expect(segmentClipboardHtml({raw: 'x', text: '<b>&'})).toMatch(
      />&lt;b&gt;&amp;<\/span>$/,
    )
  })

  test('finds the slice in html wrapped by the browser', () => {
    const html = `<meta charset="utf-8">${segmentClipboardHtml({raw: '<x id="1"/>', text: ''})}`
    expect(readSegmentClipboard(html)).toBe('<x id="1"/>')
  })

  test('is null for html that carries no slice', () => {
    expect(readSegmentClipboard('<b>bold</b>')).toBeNull()
    expect(readSegmentClipboard(undefined)).toBeNull()
  })

  test('turns markup that is not a segment tag into text', () => {
    expect(roundTrip('<img src=x onerror=alert(1)>a<x id="1"/>')).toBe(
      '&lt;img src=x onerror=alert(1)&gt;a<x id="1"/>',
    )
    expect(roundTrip('<g id="1" foo')).toBe('&lt;g id="1" foo')
    expect(roundTrip('<ph id="1" equiv-text="base64:PGI+">')).toBe(
      '&lt;ph id="1" equiv-text="base64:PGI+"&gt;',
    )
    expect(roundTrip('a > b')).toBe('a &gt; b')
  })
})
