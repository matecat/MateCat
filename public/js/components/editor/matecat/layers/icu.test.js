import {createSegmentState} from '../../core/createSegmentEditor'
import {ICU_CLASS, icuRanges, icuTokens} from './icu'

describe('icuTokens (ported from createIcuTokens)', () => {
  test('a variable is an id token', () => {
    const token = icuTokens('Hello {name}', 'en').find((t) => t.text === 'name')
    expect(token.type).toBe('id')
  })

  test('plain text is one text token', () => {
    expect(icuTokens('Hello world', 'en')).toEqual([
      {type: 'text', text: 'Hello world', start: 0, end: 11},
    ])
  })

  test('tokens are contiguous: each starts where the previous one ends', () => {
    let offset = 0
    icuTokens('{count, plural, one {# item} other {# items}}', 'en').forEach(
      (token) => {
        expect(token.start).toBe(offset)
        expect(token.end).toBe(offset + token.text.length)
        offset = token.end
      },
    )
  })

  test('a message ending early marks its last token as the error', () => {
    const tokens = icuTokens('{name', 'en')
    const error = tokens.find((t) => t.type === 'error')
    expect(error).toMatchObject({text: 'name', start: 1, end: 5})
    expect(error.message[0]).toMatch(/end of message pattern/)
  })

  test('a syntax error inside the message is added as an error token where the parser stopped', () => {
    const tokens = icuTokens('{a, plural, one {x} other}', 'en')
    const error = tokens[tokens.length - 1]
    expect(error.type).toBe('error')
    expect(error.start).toBe(25)
  })

  test("a plural block without 'other' is a syntax error", () => {
    const error = icuTokens('{count, plural, one {# item}}', 'en').find(
      (t) => t.type === 'error',
    )
    expect(error.message[0]).toMatch(/"other" sub-message must be specified/)
  })

  test('a category the locale does not have is an error', () => {
    const error = icuTokens(
      '{count, plural, invalid {# items} other {# items}}',
      'en',
    ).find((t) => t.type === 'error')
    expect(error.message).toEqual([
      "Invalid category 'invalid' in plural block for locale 'en'.",
      "Missing required category 'one' in plural block for locale 'en'.",
    ])
  })

  test('exact-value categories (=0) are accepted', () => {
    expect(
      icuTokens(
        '{n, plural, =0 {none} one {# item} other {# items}}',
        'en',
      ).some((t) => t.type === 'error'),
    ).toBe(false)
  })

  test('the locale decides the required categories (selectordinal in English needs few)', () => {
    const error = icuTokens(
      '{n, selectordinal, one {#st} two {#nd} other {#th}}',
      'en',
    ).find((t) => t.type === 'error')
    expect(error.message).toEqual([
      "Missing required category 'few' in selectordinal block for locale 'en'.",
    ])
  })

  test('a complete plural and a select are not errors', () => {
    expect(
      icuTokens('{count, plural, one {# item} other {# items}}', 'en').some(
        (t) => t.type === 'error',
      ),
    ).toBe(false)
    expect(
      icuTokens(
        '{gender, select, male {He} female {She} other {They}}',
        'en',
      ).some((t) => t.type === 'error'),
    ).toBe(false)
  })

  test('a plural nested in a select is checked too', () => {
    const error = icuTokens(
      '{g, select, male {{count, plural, one {# item}}} other {x}}',
      'en',
    ).find((t) => t.type === 'error')
    expect(error.message[0]).toMatch(/"other" sub-message must be specified/)
  })

  test('a nested plural missing a category of the locale is an error', () => {
    const tokens = icuTokens(
      '{g, select, male {{n, plural, few {x} other {y}}} other {z}}',
      'en',
    )
    const errors = tokens.filter((t) => t.type === 'error')
    expect(errors.map((t) => t.message)).toEqual([
      [
        "Invalid category 'few' in plural block for locale 'en'.",
        "Missing required category 'one' in plural block for locale 'en'.",
      ],
    ])
  })
})

describe('icuRanges (the source ICU layer)', () => {
  test('marks every ICU token that is not text, at its document position', () => {
    const state = createSegmentState('Hi {name}!')
    const marked = icuRanges(state, 'en').map(({from, to, className}) => [
      state.sliceDoc(from, to),
      className,
    ])
    expect(marked).toEqual([
      ['{', ICU_CLASS],
      ['name', ICU_CLASS],
      ['}', ICU_CLASS],
    ])
  })

  test('text with no ICU syntax marks nothing', () => {
    expect(icuRanges(createSegmentState('Hello world'), 'en')).toEqual([])
  })

  test('decoded entities and tags keep the marks on the right characters', () => {
    const state = createSegmentState('A &amp; <x id="1"/> {n}')
    const marked = icuRanges(state, 'en').map(({from, to}) =>
      state.sliceDoc(from, to),
    )
    expect(marked).toEqual(['{', 'n', '}'])
  })
})

describe('icuRanges on the target (IcuHighlight with isTarget)', () => {
  test('an error token is marked as an error and keeps its messages for the tooltip', () => {
    // Russian plurals need few and many too
    const text = '{count, plural, one {# item} other {# items}}'
    const state = createSegmentState(text)
    const errors = icuRanges(state, 'ru', {showErrors: true}).filter(
      (r) => r.data,
    )
    expect(errors).toHaveLength(1)
    expect(errors[0].className).toBe('icuItem icuItem-error')
    expect(errors[0].data.type).toBe('icu')
    expect(errors[0].data.messages).toEqual(
      expect.arrayContaining([
        "Missing required category 'few' in plural block for locale 'ru'.",
      ]),
    )
  })

  test('without showErrors (the source) the same token is a plain ICU mark', () => {
    const state = createSegmentState('{count, plural, one {# item}}')
    const ranges = icuRanges(state, 'en-US')
    expect(ranges.every((r) => r.className === 'icuItem' && !r.data)).toBe(true)
  })

  test('a valid message has no error marks on the target either', () => {
    const state = createSegmentState(
      '{count, plural, one {# item} other {# items}}',
    )
    expect(
      icuRanges(state, 'en-US', {showErrors: true}).some((r) => r.data),
    ).toBe(false)
  })
})
