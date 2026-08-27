window.config = {
  lxq_partnerid: 'test',
  lxq_license: 'lic',
  lexiqaServer: 'http://lexiqa',
  id_job: 2,
  password: 'pw',
  source_code: 'en-US',
  target_code: 'fr-FR',
  lexiqa_languages: ['en-US', 'fr-FR'],
}

jest.mock('../actions/segmentDispatchActions', () => ({
  qaComponentsetLxqIssues: jest.fn(),
  addLexiqaHighlight: jest.fn(),
}))
jest.mock('../actions/segmentQaActions', () => ({getSegmentsQa: jest.fn()}))
jest.mock('../api/toggleTagLexica', () => ({
  toggleTagLexica: jest.fn(() => Promise.resolve()),
}))
jest.mock('../api/getLexiqaWarnings', () => ({
  getLexiqaWarnings: jest.fn(() => Promise.resolve({errors: 0})),
}))
jest.mock('../api/lexiqaIgnoreError', () => ({lexiqaIgnoreError: jest.fn()}))
jest.mock('../api/lexiqaTooltipwarnings', () => ({
  lexiqaTooltipwarnings: jest.fn(() => Promise.resolve({})),
}))
jest.mock('../api/getLexiqaQa', () => ({
  getLexiqaQa: jest.fn(() => Promise.resolve({qaData: []})),
}))
jest.mock('../stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    getSegmentByIdToJS: jest.fn(),
    getCurrentSegment: jest.fn(),
    getAllSegments: jest.fn(() => []),
  },
}))
jest.mock('../stores/UserStore', () => ({
  __esModule: true,
  default: {getUserMetadata: jest.fn(() => ({lexiqa: 1}))},
}))

import LXQ from './lxq.main'
import {
  qaComponentsetLxqIssues,
  addLexiqaHighlight,
} from '../actions/segmentDispatchActions'
import {getSegmentsQa} from '../actions/segmentQaActions'
import {toggleTagLexica} from '../api/toggleTagLexica'
import {getLexiqaWarnings as getLexiqaWarningsApi} from '../api/getLexiqaWarnings'
import {lexiqaIgnoreError} from '../api/lexiqaIgnoreError'
import {lexiqaTooltipwarnings} from '../api/lexiqaTooltipwarnings'
import {getLexiqaQa} from '../api/getLexiqaQa'
import SegmentStore from '../stores/SegmentStore'
import UserStore from '../stores/UserStore'

const emptyErrorsMap = () => ({
  numbers: [],
  punctuation: [],
  spaces: [],
  urls: [],
  spelling: [],
  specialchardetect: [],
  mspolicheck: [],
  glossary: [],
  blacklist: [],
})

const range = (overrides = {}) => ({
  myClass: '',
  errorid: '',
  suggestions: ['fixed word'],
  start: 3,
  end: 8,
  ...overrides,
})

beforeEach(() => {
  LXQ.canActivate = undefined
  LXQ.initialized = false
  LXQ.notCheckedSegments = []
  LXQ.lexiqaData = {
    lexiqaWarnings: {},
    enableHighlighting: true,
    lexiqaFetching: false,
    segments: [],
    segmentsInfo: {},
  }
  UserStore.getUserMetadata.mockReturnValue({lexiqa: 1})
})

// ---------------------------------------------------------------------------
// buildTooltipMessages (existing coverage)
// ---------------------------------------------------------------------------
describe('buildTooltipMessages', () => {
  test('does not add suggestion messages for the source segment', () => {
    expect(LXQ.buildTooltipMessages(range(), 1, true)).toEqual([])
  })

  test('adds a suggestion message for the target segment', () => {
    expect(LXQ.buildTooltipMessages(range(), 1, false)).toEqual([
      {msg: 'fixed word', start: 3, end: 8, type: 'suggestion'},
    ])
  })

  test('adds one suggestion message per suggestion', () => {
    expect(
      LXQ.buildTooltipMessages(
        range({suggestions: ['first', 'second']}),
        1,
        false,
      ),
    ).toEqual([
      {msg: 'first', start: 3, end: 8, type: 'suggestion'},
      {msg: 'second', start: 3, end: 8, type: 'suggestion'},
    ])
  })

  test('does not add anything when there are no suggestions', () => {
    expect(
      LXQ.buildTooltipMessages(range({suggestions: []}), 1, false),
    ).toEqual([])
  })

  test('builds module messages for a source range', () => {
    LXQ.lexiqaData.lexiqaWarnings[7] = {}
    const messages = LXQ.buildTooltipMessages(
      range({myClass: 'n1 s1', errorid: 'a b'}),
      7,
      true,
    )
    expect(messages.length).toBeGreaterThan(0)
    expect(messages[0]).toHaveProperty('msg')
  })

  test('substitutes the word for a g3g glossary module', () => {
    LXQ.warningMessages = {
      ...LXQ.warningMessages,
      g3g: {t: 'found #xxx#', s: ''},
    }
    LXQ.lexiqaData.lexiqaWarnings[9] = {e0: {msg: 'apple'}}
    const messages = LXQ.buildTooltipMessages(
      range({myClass: 'g3g g0', errorid: 'e0 e1'}),
      9,
      false,
    )
    expect(messages[0].msg).toContain('apple')
  })

  test('substitutes extra text for an o1 module', () => {
    LXQ.warningMessages = {...LXQ.warningMessages, o1: {t: 'val XXXX', s: ''}}
    LXQ.lexiqaData.lexiqaWarnings[10] = {e0: {tootipExtraText: 'ZZ'}}
    const messages = LXQ.buildTooltipMessages(
      range({myClass: 'o1 o0', errorid: 'e0 e1'}),
      10,
      false,
    )
    expect(messages[0].msg).toContain('ZZ')
  })
})

// ---------------------------------------------------------------------------
// activation / enable / disable
// ---------------------------------------------------------------------------
describe('activation', () => {
  test('checkCanActivate is true when both languages are supported', () => {
    expect(LXQ.checkCanActivate()).toBe(true)
  })

  test('checkCanActivate is false when a language is unsupported', () => {
    LXQ.canActivate = undefined
    window.config.lexiqa_languages = ['en-US']
    expect(LXQ.checkCanActivate()).toBe(false)
    window.config.lexiqa_languages = ['en-US', 'fr-FR']
  })

  test('enabled reflects user metadata', () => {
    LXQ.canActivate = undefined
    expect(LXQ.enabled()).toBe(true)
    UserStore.getUserMetadata.mockReturnValue({lexiqa: 0})
    LXQ.canActivate = undefined
    expect(LXQ.enabled()).toBe(false)
  })

  test('enabled honours an explicit lexiqa argument', () => {
    LXQ.canActivate = undefined
    expect(LXQ.enabled({lexiqa: 1})).toBe(true)
  })

  test('enable re-issues issues when already initialized', async () => {
    LXQ.initialized = true
    LXQ.lexiqaData.segments = [1, 2]
    await LXQ.enable()
    await Promise.resolve()
    expect(toggleTagLexica).toHaveBeenCalledWith({enabled: true})
    expect(getSegmentsQa).toHaveBeenCalled()
  })

  test('disable clears the issues', async () => {
    await LXQ.disable()
    await Promise.resolve()
    expect(toggleTagLexica).toHaveBeenCalledWith({enabled: false})
    expect(qaComponentsetLxqIssues).toHaveBeenCalledWith([])
  })
})

// ---------------------------------------------------------------------------
// warning list bookkeeping
// ---------------------------------------------------------------------------
describe('warning list management', () => {
  test('updateWarningsUI publishes the segments that still have warnings', () => {
    LXQ.lexiqaData.segments = [2, 1]
    LXQ.lexiqaData.lexiqaWarnings = {1: {}, 2: {}}
    LXQ.updateWarningsUI()
    expect(qaComponentsetLxqIssues).toHaveBeenCalledWith([1, 2])
  })

  test('removeSegmentWarning drops the segment and its warnings', () => {
    LXQ.lexiqaData.segments = [5]
    LXQ.lexiqaData.lexiqaWarnings = {5: {e: {}}}
    LXQ.removeSegmentWarning(5)
    expect(LXQ.lexiqaData.segments).not.toContain(5)
    expect(LXQ.lexiqaData.lexiqaWarnings[5]).toBeUndefined()
  })

  test('getVisibleWarningsCountForSegment ignores c3 and ignored errors', () => {
    LXQ.lexiqaData.lexiqaWarnings = {
      3: {
        a: {module: 'n1', ignored: false},
        b: {module: 'c3'},
        c: {module: 'p1', ignored: true},
      },
    }
    expect(LXQ.getVisibleWarningsCountForSegment(3)).toBe(1)
    expect(LXQ.getVisibleWarningsCountForSegment(999)).toBe(0)
  })

  test('getWarningForModule returns source or target text or null', () => {
    expect(LXQ.getWarningForModule('u2', true)).toBe(
      'email missing from target',
    )
    expect(LXQ.getWarningForModule('u2', false)).toBe(
      'email not found in source',
    )
    expect(LXQ.getWarningForModule('does-not-exist', true)).toBeNull()
  })

  test('redoHighlighting rebuilds highlights from stored warnings', () => {
    LXQ.lexiqaData.lexiqaWarnings = {
      4: {
        a: {ignored: false, insource: true, category: 'numbers'},
        b: {ignored: false, insource: false, category: 'urls'},
        c: {ignored: true, insource: true, category: 'spelling'},
      },
    }
    LXQ.redoHighlighting(4, true)
    expect(addLexiqaHighlight).toHaveBeenCalled()
    const highlights = addLexiqaHighlight.mock.calls.pop()[1]
    expect(highlights.source.numbers.length).toBe(1)
    expect(highlights.target.urls.length).toBe(1)
  })

  test('lxqRemoveSegmentFromWarningList clears highlight and warning', () => {
    LXQ.lexiqaData.segments = [8]
    LXQ.lexiqaData.lexiqaWarnings = {8: {e: {}}}
    LXQ.lxqRemoveSegmentFromWarningList(8)
    expect(addLexiqaHighlight).toHaveBeenCalledWith(8, {})
    expect(LXQ.lexiqaData.segments).not.toContain(8)
  })

  test('postIgnoreError forwards to the api', () => {
    LXQ.postIgnoreError('err_1_s')
    expect(lexiqaIgnoreError).toHaveBeenCalledWith({errorId: 'err_1_s'})
  })

  test('ignoreError marks the error ignored and removes empty segments', () => {
    LXQ.lexiqaData.segments = ['5']
    LXQ.lexiqaData.lexiqaWarnings = {
      5: {err_5_x_s: {ignored: false, module: 'n1', insource: true}},
    }
    LXQ.ignoreError('err_5_x_s')
    expect(LXQ.lexiqaData.lexiqaWarnings['5']).toBeUndefined()
    expect(lexiqaIgnoreError).toHaveBeenCalled()
  })
})

// ---------------------------------------------------------------------------
// isNumeric / getRanges / cleanRanges
// ---------------------------------------------------------------------------
describe('isNumeric', () => {
  test('detects numeric values', () => {
    expect(LXQ.isNumeric('3.5')).toBe(true)
    expect(LXQ.isNumeric('abc')).toBe(false)
  })
})

describe('getRanges / cleanRanges', () => {
  test('returns an empty object when there is nothing to highlight', () => {
    const results = emptyErrorsMap()
    expect(LXQ.getRanges(results, 'hello', false)).toEqual({})
  })

  test('builds a single-error highlight range', () => {
    const results = emptyErrorsMap()
    results.numbers = [{start: 0, end: 2, module: 'n1', errorid: 'n-1'}]
    const out = LXQ.getRanges(results, 'hello world', false)
    expect(Array.isArray(out)).toBe(true)
    const highlighted = out.find((r) => r.myClass)
    expect(highlighted.myClass).toContain('n1')
    expect(highlighted.myClass).toContain('tooltipa')
  })

  test('marks source ranges with the source tooltip class', () => {
    const results = emptyErrorsMap()
    results.numbers = [{start: 0, end: 2, module: 'n1', errorid: 'n-1'}]
    const out = LXQ.getRanges(results, 'hello world', true)
    const highlighted = out.find((r) => r.myClass)
    expect(highlighted.myClass).toContain('tooltipas')
  })

  test('combines overlapping ranges into a multiple-error highlight', () => {
    const results = emptyErrorsMap()
    results.numbers = [{start: 0, end: 3, module: 'n1', errorid: 'n-1'}]
    results.punctuation = [{start: 1, end: 3, module: 'p1', errorid: 'p-1'}]
    const out = LXQ.getRanges(results, 'hello world', false)
    const multi = out.find((r) => r.myClass && r.errors && r.errors.length > 1)
    expect(multi.color).toBe(LXQ.colors.multiple)
    expect(multi.myClass).toContain('m')
  })

  test('adds the invisible class when highlighting is disabled', () => {
    LXQ.lexiqaData.enableHighlighting = false
    const results = emptyErrorsMap()
    results.numbers = [{start: 0, end: 2, module: 'n1', errorid: 'n-1'}]
    const out = LXQ.getRanges(results, 'hello world', false)
    const highlighted = out.find((r) => r.myClass)
    expect(highlighted.myClass).toContain('lxq-invisible')
  })

  test('cleanRanges expands length-based ranges into start/end pairs', () => {
    const result = LXQ.cleanRanges([
      {ranges: [{start: 2, length: 3, module: 'n1'}]},
    ])
    expect(result.out[0].end).toBe(5)
  })

  test('cleanRanges returns null when nothing is provided', () => {
    expect(LXQ.cleanRanges([])).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// doLexiQA
// ---------------------------------------------------------------------------
describe('doLexiQA', () => {
  test('invokes the callback immediately when lexiqa is disabled', async () => {
    UserStore.getUserMetadata.mockReturnValue({lexiqa: 0})
    LXQ.canActivate = undefined
    await new Promise((resolve) => {
      LXQ.doLexiQA({sid: 1}, false, resolve)
    })
    expect(getLexiqaQa).not.toHaveBeenCalled()
  })

  test('processes returned qa data and highlights the segment', async () => {
    getLexiqaQa.mockResolvedValueOnce({
      qaData: [
        {errorid: 'e1', category: 'numbers', insource: true, ignored: false},
        {errorid: 'e2', category: 'urls', insource: false, ignored: false},
      ],
    })
    await new Promise((resolve) => {
      LXQ.doLexiQA(
        {
          sid: 20,
          lxqDecodedSource: 'src',
          lxqDecodedTranslation: 'trg',
        },
        false,
        resolve,
      )
    })
    expect(LXQ.lexiqaData.segments).toContain(20)
    expect(addLexiqaHighlight).toHaveBeenCalled()
  })

  test('removes the segment when no visible errors are returned', async () => {
    getLexiqaQa.mockResolvedValueOnce({qaData: []})
    await new Promise((resolve) => {
      LXQ.doLexiQA(
        {sid: 21, lxqDecodedSource: 's', lxqDecodedTranslation: 't'},
        true,
        resolve,
      )
    })
    expect(addLexiqaHighlight).toHaveBeenCalledWith(21, {})
  })
})

// ---------------------------------------------------------------------------
// getLexiqaWarnings
// ---------------------------------------------------------------------------
describe('getLexiqaWarnings', () => {
  test('calls back immediately when disabled', async () => {
    UserStore.getUserMetadata.mockReturnValue({lexiqa: 0})
    LXQ.canActivate = undefined
    await new Promise((resolve) => LXQ.getLexiqaWarnings(resolve))
    expect(getLexiqaWarningsApi).not.toHaveBeenCalled()
  })

  test('processes warnings from the server', async () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: 30})
    getLexiqaWarningsApi.mockResolvedValueOnce({
      errors: 2,
      segments: [
        {segid: 30, errornum: 1},
        {segid: 31, errornum: 0},
      ],
      results: {
        30: [
          {errorid: 'e1', category: 'numbers', insource: false, ignored: false},
        ],
      },
    })
    await new Promise((resolve) => LXQ.getLexiqaWarnings(resolve))
    expect(LXQ.lexiqaData.lexiqaFetching).toBe(false)
    expect(addLexiqaHighlight).toHaveBeenCalled()
  })
})

// ---------------------------------------------------------------------------
// doQAallSegments / checkNextUncheckedSegment
// ---------------------------------------------------------------------------
describe('doQAallSegments', () => {
  test('queues unchecked segments and requests QA for translated ones', async () => {
    SegmentStore.getAllSegments.mockReturnValue([
      {sid: 40, translation: 'done'},
      {sid: 41, translation: ''},
    ])
    LXQ.lexiqaData.segments = []
    getLexiqaQa.mockResolvedValue({qaData: []})
    LXQ.doQAallSegments()
    await Promise.resolve()
    expect(SegmentStore.getAllSegments).toHaveBeenCalled()
  })

  test('checkNextUncheckedSegment stops when the queue is empty', () => {
    LXQ.notCheckedSegments = []
    expect(() => LXQ.checkNextUncheckedSegment()).not.toThrow()
  })
})

// ---------------------------------------------------------------------------
// init
// ---------------------------------------------------------------------------
describe('init', () => {
  test('registers event handlers and loads tooltip warnings', async () => {
    lexiqaTooltipwarnings.mockResolvedValueOnce({
      p1: {t: 'punct', s: ''},
      b1g: {t: 'gloss', s: ''},
    })
    LXQ.init()
    expect(LXQ.initialized).toBe(true)
    await Promise.resolve()
    await Promise.resolve()
    expect(LXQ.modulesNoHighlight).toContain('b1g')

    // exercise the registered document listeners
    UserStore.getUserMetadata.mockReturnValue({lexiqa: 1})
    LXQ.canActivate = undefined
    getLexiqaQa.mockResolvedValue({qaData: []})

    document.dispatchEvent(
      new CustomEvent('getWarning:local:success', {
        detail: {
          segment: {sid: 50, lxqDecodedSource: 's', lxqDecodedTranslation: 't'},
        },
      }),
    )
    document.dispatchEvent(
      new CustomEvent('setTranslation:success', {
        detail: {
          segment: {sid: 51, lxqDecodedSource: 's', lxqDecodedTranslation: 't'},
        },
      }),
    )
    document.dispatchEvent(new CustomEvent('getWarning:global:success'))
    LXQ.lexiqaData.lexiqaWarnings = {
      60: {e: {ignored: false, insource: false, category: 'numbers'}},
    }
    document.dispatchEvent(
      new CustomEvent('segmentsAdded', {
        detail: {resp: [{segments: [{sid: 60}]}]},
      }),
    )
    await Promise.resolve()
    expect(LXQ.initialized).toBe(true)
  })
})
