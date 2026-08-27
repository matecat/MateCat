import CommonUtils, {switchArrayIndex, executeOnce} from './commonUtils'

jest.mock('../actions/ModalsActions', () => ({
  __esModule: true,
  default: {showModalComponent: jest.fn()},
}))
jest.mock('../actions/SegmentActions', () => ({
  __esModule: true,
  default: {openSegment: jest.fn()},
}))
jest.mock('../setTranslationUtil', () => ({
  __esModule: true,
  isTranslationTailEmpty: jest.fn(() => true),
}))
jest.mock('./offlineUtils', () => ({
  __esModule: true,
  default: {offline: false},
}))
jest.mock('../stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    getSegmentByIdToJS: jest.fn(),
    getCurrentSegment: jest.fn(),
  },
}))
jest.mock('./segmentLocalStorage', () => ({
  __esModule: true,
  getLastSegmentFromLocalStorage: jest.fn(),
  setLastSegmentFromLocalStorage: jest.fn(),
}))

import ModalsActions from '../actions/ModalsActions'
import OfflineUtils from './offlineUtils'
import SegmentStore from '../stores/SegmentStore'

beforeEach(() => {
  global.config = {
    id_job: 2,
    support_mail: 'support@matecat.com',
    job_is_splitted: true,
    flash_messages: {
      service: [
        {key: 'a', val: 1},
        {key: 'b', val: 2},
      ],
    },
  }
  localStorage.clear()
  sessionStorage.clear()
  CommonUtils.localStorageArray = []
  OfflineUtils.offline = false
})

describe('millisecondsToTime', () => {
  it('converts milliseconds into [minutes, seconds]', () => {
    expect(CommonUtils.millisecondsToTime(65000)).toEqual([1, 5])
  })
})

describe('isJobCompleted', () => {
  it('is true only when draft and new are zero', () => {
    expect(CommonUtils.isJobCompleted({raw: {draft: 0, new: 0}})).toBe(true)
    expect(CommonUtils.isJobCompleted({raw: {draft: 2, new: 0}})).toBe(false)
  })
})

describe('levenshteinDistance', () => {
  it('returns 0 for equal strings', () => {
    expect(CommonUtils.levenshteinDistance('abc', 'abc')).toBe(0)
  })
  it('returns the other length when one string is empty', () => {
    expect(CommonUtils.levenshteinDistance('', 'abc')).toBe(3)
    expect(CommonUtils.levenshteinDistance('abc', '')).toBe(3)
  })
  it('computes the edit distance', () => {
    expect(CommonUtils.levenshteinDistance('kitten', 'sitting')).toBe(3)
  })
})

describe('toTitleCase', () => {
  it('capitalizes each word', () => {
    expect(CommonUtils.toTitleCase('hello world')).toBe('Hello World')
  })
})

describe('genericErrorAlertMessage', () => {
  it('opens an alert modal with the support mail', () => {
    CommonUtils.genericErrorAlertMessage()
    expect(ModalsActions.showModalComponent).toHaveBeenCalled()
    const text = ModalsActions.showModalComponent.mock.calls[0][1].text
    expect(text).toContain('support@matecat.com')
  })
})

describe('goodbye', () => {
  it('warns about pending operations', () => {
    document.body.innerHTML = '<a id="action-download" class="disabled"></a>'
    const message = CommonUtils.goodbye({})
    expect(message).toContain('pending operation')
  })
  it('warns about offline pending translations', () => {
    document.body.innerHTML = ''
    OfflineUtils.offline = true
    const setUtil = require('../setTranslationUtil')
    setUtil.isTranslationTailEmpty.mockReturnValue(false)
    const message = CommonUtils.goodbye({
      stopPropagation: jest.fn(),
      preventDefault: jest.fn(),
    })
    expect(message).toContain('offline mode')
  })
  it('returns undefined when nothing pending', () => {
    document.body.innerHTML = ''
    OfflineUtils.offline = false
    expect(CommonUtils.goodbye({})).toBeUndefined()
  })
})

describe('getFileIcon', () => {
  it.each([
    'docx',
    'pptx',
    'html',
    'pdf',
    'xls',
    'srt',
    'png',
    'txt',
    'xliff',
    'idml',
    'zip',
    'json',
    'unknownext',
  ])('returns an element for %s', (ext) => {
    const el = CommonUtils.getFileIcon(ext)
    expect(el).toBeTruthy()
    expect(el.type).toBeTruthy()
  })
})

describe('local/session storage helpers', () => {
  it('detects localStorage support', () => {
    expect(CommonUtils.isLocalStorageNameSupported()).toBe(true)
  })

  it('reports isSafari / isPrivateSafari as booleans', () => {
    expect(typeof CommonUtils.isSafari).toBe('boolean')
    expect(typeof CommonUtils.isPrivateSafari()).toBe('boolean')
  })

  it('stores and reads via localStorage in the standard branch', () => {
    CommonUtils.addInStorage('k1', 'v1', 'k')
    expect(CommonUtils.getFromStorage('k1')).toBe('v1')
    CommonUtils.removeFromStorage('k1')
    expect(CommonUtils.getFromStorage('k1')).toBeNull()
  })

  it('stores and reads via sessionStorage in the standard branch', () => {
    CommonUtils.addInSessionStorage('s1', 'v1', 's')
    expect(CommonUtils.getFromSessionStorage('s1')).toBe('v1')
    CommonUtils.removeFromSessionStorage('s1')
    expect(CommonUtils.getFromSessionStorage('s1')).toBeNull()
  })

  it('uses the in-memory array under private Safari', () => {
    jest.spyOn(CommonUtils, 'isPrivateSafari').mockReturnValue(true)
    CommonUtils.addInStorage('p1', 'val', 'op')
    expect(CommonUtils.getFromStorage('p1')).toBe('val')
    CommonUtils.removeFromStorage('p1')
    expect(CommonUtils.getFromStorage('p1')).toBe(false)

    CommonUtils.addInSessionStorage('ps1', 'sval', 'op')
    expect(CommonUtils.getFromSessionStorage('ps1')).toBe('sval')
    CommonUtils.removeFromSessionStorage('ps1')
    expect(CommonUtils.getFromSessionStorage('ps1')).toBe(false)
    CommonUtils.isPrivateSafari.mockRestore()
  })

  it('clears keys by prefix', () => {
    localStorage.setItem('contribution-1', 'a')
    localStorage.setItem('other-1', 'b')
    CommonUtils.clearStorage('contribution')
    expect(localStorage.getItem('contribution-1')).toBeNull()
    expect(localStorage.getItem('other-1')).toBe('b')
  })
})

describe('addCommas', () => {
  it('adds thousands separators', () => {
    expect(CommonUtils.addCommas(1234567)).toBe('1,234,567')
    expect(CommonUtils.addCommas('1234.56')).toBe('1,234.56')
  })
})

describe('getParameterByName', () => {
  it('reads a query parameter from an explicit url', () => {
    expect(CommonUtils.getParameterByName('foo', 'http://x/?foo=bar')).toBe(
      'bar',
    )
  })
  it('returns empty string when the parameter has no value', () => {
    expect(CommonUtils.getParameterByName('foo', 'http://x/?foo')).toBe('')
  })
  it('returns null when the parameter is absent', () => {
    expect(CommonUtils.getParameterByName('foo', 'http://x/?baz=1')).toBeNull()
  })
})

describe('removeParam', () => {
  it('removes a query parameter and pushes the new url', () => {
    // jsdom's location.href is unforgeable, but same-origin history.pushState
    // is honoured and updates document.location.href — which is what
    // removeParam reads.
    window.history.pushState({}, '', '/page?keep=1&drop=2')
    const result = CommonUtils.removeParam('drop')
    expect(result).toContain('keep=1')
    expect(result).not.toContain('drop=2')
    window.history.pushState({}, '', '/')
  })
})

describe('email validation', () => {
  it('validates a single email', () => {
    expect(CommonUtils.checkEmail('a@b.com')).toBe(true)
    expect(CommonUtils.checkEmail('nope')).toBe(false)
  })
  it('validates a comma separated list', () => {
    expect(CommonUtils.validateEmailList('a@b.com,c@d.com').result).toBe(true)
    const bad = CommonUtils.validateEmailList('a@b.com,broken')
    expect(bad.result).toBe(false)
    expect(bad.emails).toBe('broken')
  })
})

describe('getUserShortName', () => {
  it('builds initials from first and last name', () => {
    expect(
      CommonUtils.getUserShortName({first_name: 'John', last_name: 'Doe'}),
    ).toBe('JD')
  })
  it('falls back to AU when name is missing', () => {
    expect(CommonUtils.getUserShortName(null)).toBe('AU')
  })
})

describe('date helpers', () => {
  it('getGMTDate returns formatted date parts', () => {
    const result = CommonUtils.getGMTDate('2020-01-15 10:00:00', 0)
    expect(result).toHaveProperty('day')
    expect(result).toHaveProperty('month')
    expect(result).toHaveProperty('year', '2020')
    expect(result).toHaveProperty('gmt')
  })
  it('formatDate returns a year-month-day time string', () => {
    const result = CommonUtils.formatDate('2020-01-15T10:00:00')
    expect(result).toMatch(/^2020-1-15 /)
  })
  it('getGMTZoneString returns a GMT string', () => {
    expect(CommonUtils.getGMTZoneString()).toMatch(/^GMT /)
  })
})

describe('misc helpers', () => {
  it('checkJobIsSplitted reads config', () => {
    expect(CommonUtils.checkJobIsSplitted()).toBe(true)
  })
  it('parseFiles returns the input', () => {
    const files = [1, 2]
    expect(CommonUtils.parseFiles(files)).toBe(files)
  })
  it('fileHasInstructions reflects metadata', () => {
    expect(
      CommonUtils.fileHasInstructions({metadata: {instructions: 'x'}}),
    ).toBe('x')
    expect(CommonUtils.fileHasInstructions(null)).toBeFalsy()
  })
  it('isAllowedLinkRedirect is false', () => {
    expect(CommonUtils.isAllowedLinkRedirect()).toBe(false)
  })
  it('lookupFlashServiceParam filters flash services', () => {
    expect(CommonUtils.lookupFlashServiceParam('a')).toEqual([
      {key: 'a', val: 1},
    ])
  })
})

describe('custom event dispatchers', () => {
  it.each([
    [
      'dispatchTrackingError',
      'track-error',
      () => CommonUtils.dispatchTrackingError('m', {}),
    ],
    [
      'dispatchCustomEvent',
      'my-event',
      () => CommonUtils.dispatchCustomEvent('my-event', {a: 1}),
    ],
    [
      'dispatchTrackingEvents',
      'track-event',
      () => CommonUtils.dispatchTrackingEvents('n', 'm'),
    ],
    [
      'dispatchAnalyticsEvents',
      'dataLayer-event',
      () => CommonUtils.dispatchAnalyticsEvents({a: 1}),
    ],
  ])('%s dispatches %s', (_name, eventName, fire) => {
    const handler = jest.fn()
    document.addEventListener(eventName, handler)
    fire()
    expect(handler).toHaveBeenCalled()
    document.removeEventListener(eventName, handler)
  })
})

describe('setBrowserHistoryBehavior', () => {
  it('wires popstate and history listeners and cleans up the hash', () => {
    window.location.hash = '#42,edit'
    CommonUtils.setBrowserHistoryBehavior()
    expect(CommonUtils.parsedHash.segmentId).toBe('42')
    expect(CommonUtils.parsedHash.action).toBe('edit')

    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: 42, opened: false})
    SegmentStore.getCurrentSegment.mockReturnValue({sid: 1})
    window.dispatchEvent(new PopStateEvent('popstate'))

    window.dispatchEvent(new Event('historyChangeState'))
    expect(CommonUtils.parsedHash).toBeTruthy()
  })

  it('logs when popstate handling throws', () => {
    const spy = jest.spyOn(console, 'error').mockImplementation()
    window.location.hash = '#7'
    CommonUtils.setBrowserHistoryBehavior()
    SegmentStore.getSegmentByIdToJS.mockImplementation(() => {
      throw new Error('boom')
    })
    window.dispatchEvent(new PopStateEvent('popstate'))
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })
})

describe('String.prototype.splice', () => {
  it('splices a string at an index', () => {
    expect('hello'.splice(0, 1, 'J')).toBe('Jello')
  })
})

describe('DetectTripleClick', () => {
  it('fires the callback on a triple click inside the selection bounds', () => {
    jest.useFakeTimers()
    const target = document.createElement('div')
    const callback = jest.fn()
    const rect = {x: 0, width: 100, left: 0, right: 100}
    jest.spyOn(window, 'getSelection').mockReturnValue({
      focusNode: {parentNode: {getBoundingClientRect: () => rect}},
      rangeCount: 1,
      getRangeAt: () => ({
        cloneRange: () => ({getBoundingClientRect: () => rect}),
      }),
    })
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)
    for (let i = 0; i < 3; i++) {
      target.dispatchEvent(
        new MouseEvent('mousedown', {clientX: 50, bubbles: true}),
      )
    }
    expect(callback).toHaveBeenCalled()
    window.getSelection.mockRestore()
    jest.useRealTimers()
  })

  it('resets the counter after the timeout when fewer than three clicks', () => {
    jest.useFakeTimers()
    const target = document.createElement('div')
    const callback = jest.fn()
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)
    target.dispatchEvent(new MouseEvent('mousedown', {clientX: 10}))
    jest.runAllTimers()
    expect(callback).not.toHaveBeenCalled()
    jest.useRealTimers()
  })
})

describe('switchArrayIndex', () => {
  it('moves an item forward', () => {
    expect(switchArrayIndex([1, 2, 3, 4], 0, 2)).toEqual([2, 3, 1, 4])
  })
  it('moves an item backward', () => {
    expect(switchArrayIndex([1, 2, 3, 4], 3, 1)).toEqual([1, 4, 2, 3])
  })
})

describe('executeOnce', () => {
  it('runs the callback only the first time', () => {
    const once = executeOnce()
    const cb = jest.fn()
    once(cb)
    once(cb)
    expect(cb).toHaveBeenCalledTimes(1)
  })
})

describe('CommonUtils.DetectTripleClick', () => {
  afterEach(() => {
    jest.restoreAllMocks()
  })

  const tripleClick = (target) => {
    for (let i = 0; i < 3; i++) {
      target.dispatchEvent(
        new MouseEvent('mousedown', {clientX: 10, bubbles: true}),
      )
    }
  }

  test('does not throw when the selection lands on a node without getBoundingClientRect (e.g. a browser-extension-injected node)', () => {
    const target = document.createElement('div')
    const callback = jest.fn()
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)

    jest.spyOn(window, 'getSelection').mockReturnValue({
      focusNode: {parentNode: {}},
    })

    expect(() => tripleClick(target)).not.toThrow()
    expect(callback).not.toHaveBeenCalled()
  })

  test('calls the callback when the click lands within the selection bounds', () => {
    const target = document.createElement('div')
    const callback = jest.fn()
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)

    jest.spyOn(window, 'getSelection').mockReturnValue({
      rangeCount: 1,
      getRangeAt: () => ({
        cloneRange: () => ({
          getBoundingClientRect: () => ({left: 0, right: 20}),
        }),
      }),
      focusNode: {
        parentNode: {
          getBoundingClientRect: () => ({x: 0, width: 20}),
        },
      },
    })

    tripleClick(target)

    expect(callback).toHaveBeenCalled()
  })
})
