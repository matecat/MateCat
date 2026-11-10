import prepareTextForLexiqa from '../../../segments/utils/DraftMatecatUtils/prepareTextForLexiqa'
import {createSegmentState} from '../../core/createSegmentEditor'
import {serialize} from '../../core/docModel'
import {initTagSignature} from '../../../../utils/segmentText/tagModel'
import {
  alignLexiqaText,
  LEXIQA_CLASS,
  lexiqaDocRanges,
  lexiqaRanges,
} from './lexiqa'

const mockGetRanges = jest.fn()
const mockBuildTooltipMessages = jest.fn()
jest.mock('../../../../utils/lxq.main', () => ({
  __esModule: true,
  default: {
    getRanges: (...args) => mockGetRanges(...args),
    buildTooltipMessages: (...args) => mockBuildTooltipMessages(...args),
  },
}))

// The Lexiqa text Matecat sends for a segment (what the store keeps as lxqDecodedSource), and a warning on `word`.
const lexiqaTextOf = (segment) => prepareTextForLexiqa(segment)
const warningOn = (lexiqaText, word, extra = {}) => {
  const start = lexiqaText.indexOf(word)
  if (start < 0) throw new Error(`"${word}" not in "${lexiqaText}"`)
  return {start, end: start + word.length, ...extra}
}

const placed = (segment, word) => {
  const state = createSegmentState(segment)
  const text = lexiqaTextOf(serialize(state))
  return lexiqaDocRanges(state, [warningOn(text, word)], text).map(
    ({from, to}) => state.sliceDoc(from, to),
  )
}

describe('lexiqaDocRanges: Lexiqa offsets back on the document', () => {
  test.each([
    ['plain text', 'one tow three', 'tow'],
    ['after a paired tag with an id', 'a <g id="1">b</g> wrld', 'wrld'],
    ['inside a paired tag', 'a <g id="1">wrld</g> c', 'wrld'],
    ['after self-closing tags', 'x<x id="1"/> <bx id="2"/>wrld', 'wrld'],
    [
      'after a ph with equiv-text',
      'a <ph id="1" equiv-text="base64:JXM="/> wrld',
      'wrld',
    ],
    ['after a non-breaking space', 'a##$_A0$##wrld', 'wrld'],
    ['after a tab', 'a##$_09$##wrld', 'wrld'],
    ['on a later line', 'one##$_0A$##two wrld', 'wrld'],
    ['after a carriage return', 'one##$_0D$##wrld', 'wrld'],
    ['after decoded entities', 'A &amp; B &lt;c&gt; wrld', 'wrld'],
  ])('%s', (_, segment, word) => {
    expect(placed(segment, word)).toEqual([word])
  })

  describe('with visible spaces off (spaces are text, not tags)', () => {
    beforeAll(() => initTagSignature({show_whitespace: 0}))
    afterAll(() => initTagSignature({show_whitespace: 1}))

    test.each([
      ['plain text', 'one tow three', 'tow'],
      ['after a paired tag', 'a <g id="1">b</g> wrld', 'wrld'],
      ['after a non-breaking space', 'a##$_A0$##wrld', 'wrld'],
    ])('%s', (_, segment, word) => {
      expect(lexiqaTextOf(segment)).toContain(' ')
      expect(placed(segment, word)).toEqual([word])
    })
  })

  test('a warning covering a tag Lexiqa does not check is not shown', () => {
    const state = createSegmentState('a <g id="1">b</g> c')
    const text = lexiqaTextOf(serialize(state))
    expect(
      lexiqaDocRanges(state, [{start: 0, end: text.length}], text),
    ).toEqual([])
  })

  test('a stale Lexiqa text places nothing', () => {
    const state = createSegmentState('one two')
    expect(alignLexiqaText(state, 'one three')).toBeNull()
    expect(lexiqaDocRanges(state, [{start: 0, end: 3}], 'one three')).toEqual(
      [],
    )
  })

  test('a Lexiqa text where a tag has no closing bracket places nothing', () => {
    const state = createSegmentState('a<x id="1"/>b')
    expect(alignLexiqaText(state, 'a<1b')).toBeNull()
  })

  test('empty and out of range warnings are dropped', () => {
    const state = createSegmentState('abc')
    expect(
      lexiqaDocRanges(
        state,
        [
          {start: 1, end: 1},
          {start: 5, end: 6},
        ],
        'abc',
      ),
    ).toEqual([])
  })
})

describe('lexiqaRanges (activateLexiqa on the source)', () => {
  const segment = {
    sid: '5',
    lexiqa: {source: {spelling: [{}]}},
    lxqDecodedSource: prepareTextForLexiqa('one tow'),
  }

  beforeEach(() => {
    mockGetRanges.mockReset()
    mockBuildTooltipMessages.mockReset()
  })

  test('a warning with an error shows its color and carries its tooltip messages', () => {
    const start = segment.lxqDecodedSource.indexOf('tow')
    mockGetRanges.mockReturnValue([
      {
        start,
        end: start + 3,
        myClass: 'spelling s0',
        errorid: 'e1',
        color: 'red',
      },
    ])
    mockBuildTooltipMessages.mockReturnValue([{msg: 'Spelling'}])
    const state = createSegmentState('one tow')
    const [range] = lexiqaRanges(state, segment)
    expect(state.sliceDoc(range.from, range.to)).toBe('tow')
    expect(range).toMatchObject({
      className: LEXIQA_CLASS,
      attributes: {style: 'background-color: red'},
      data: {type: 'lexiqa', messages: [{msg: 'Spelling'}]},
    })
    expect(mockGetRanges).toHaveBeenCalledWith(
      segment.lexiqa.source,
      segment.lxqDecodedSource,
      true,
    )
    expect(mockBuildTooltipMessages).toHaveBeenCalledWith(
      expect.objectContaining({errorid: 'e1'}),
      '5',
      true,
    )
  })

  test('a warning without an error id is marked but not colored', () => {
    const start = segment.lxqDecodedSource.indexOf('tow')
    mockGetRanges.mockReturnValue([{start, end: start + 3, color: 'red'}])
    const [range] = lexiqaRanges(createSegmentState('one tow'), segment)
    expect(range.attributes).toBeUndefined()
    expect(range.data.messages).toBeUndefined()
  })

  test('no Lexiqa result for the source, no ranges', () => {
    const state = createSegmentState('one tow')
    expect(lexiqaRanges(state, {...segment, lexiqa: {}})).toEqual([])
    expect(lexiqaRanges(state, {...segment, lexiqa: {source: {}}})).toEqual([])
    expect(mockGetRanges).not.toHaveBeenCalled()
  })

  test('Lexiqa reporting nothing gives no ranges', () => {
    mockGetRanges.mockReturnValue({})
    expect(lexiqaRanges(createSegmentState('one tow'), segment)).toEqual([])
  })
})

describe('lexiqaRanges (activateLexiqa on the target)', () => {
  const target = 'line one##$_0A$##line tow'
  const segment = {
    sid: '5',
    lexiqa: {source: {spelling: [{}]}, target: {spelling: [{}]}},
    lxqDecodedSource: prepareTextForLexiqa('nothing here'),
    lxqDecodedTranslation: prepareTextForLexiqa(target),
  }

  beforeEach(() => {
    mockGetRanges.mockReset()
    mockBuildTooltipMessages.mockReset()
  })

  test('the target results are placed over the Lexiqa text of the translation, on the right line', () => {
    const start = segment.lxqDecodedTranslation.indexOf('tow')
    mockGetRanges.mockReturnValue([
      {start, end: start + 3, myClass: 'spelling', errorid: 'e2'},
    ])
    mockBuildTooltipMessages.mockReturnValue([{msg: 'two', type: 'suggestion'}])
    const state = createSegmentState(target)
    const [range] = lexiqaRanges(state, segment, false)
    expect(state.sliceDoc(range.from, range.to)).toBe('tow')
    expect(state.doc.lineAt(range.from).number).toBe(2)
    expect(range.data.messages).toEqual([{msg: 'two', type: 'suggestion'}])
    expect(mockGetRanges).toHaveBeenCalledWith(
      segment.lexiqa.target,
      segment.lxqDecodedTranslation,
      false,
    )
    expect(mockBuildTooltipMessages).toHaveBeenCalledWith(
      expect.objectContaining({errorid: 'e2'}),
      '5',
      false,
    )
  })

  test('source results are never shown on the target', () => {
    const state = createSegmentState(target)
    expect(
      lexiqaRanges(state, {...segment, lexiqa: {source: {a: [{}]}}}, false),
    ).toEqual([])
    expect(mockGetRanges).not.toHaveBeenCalled()
  })
})
