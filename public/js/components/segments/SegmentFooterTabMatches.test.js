import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import SegmentFooterTabMatches from './SegmentFooterTabMatches'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'

jest.mock('../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, ...args) => listeners[event] && listeners[event](...args),
  }
})

jest.mock('../../stores/CatToolStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, ...args) => listeners[event] && listeners[event](...args),
    getJobTmKeys: jest.fn(() => []),
  }
})

jest.mock('../../actions/SegmentActions', () => ({
  getContributions: jest.fn(),
  getContribution: jest.fn(),
  setFocusOnEditArea: jest.fn(),
  disableTPOnSegment: jest.fn(),
  setChoosenSuggestion: jest.fn(),
  deleteContribution: jest.fn(),
}))

jest.mock('./utils/translationMatches', () => ({
  __esModule: true,
  default: {
    getPercentTextForMatch: jest.fn(() => '95%'),
    getPercentageClass: jest.fn(() => 'per-green'),
    copySuggestionInEditarea: jest.fn(),
  },
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToHtml: jest.fn((text) => text),
  },
}))

beforeAll(() => {
  global.config = {
    ...global.config,
    isSourceRTL: false,
    isTargetRTL: false,
    source_rfc: 'en-US',
    target_rfc: 'it-IT',
    mt_enabled: true,
  }
  global.navigator.clipboard = {writeText: jest.fn(() => Promise.resolve())}
})

afterEach(() => {
  jest.clearAllMocks()
  CatToolStore.getJobTmKeys.mockReturnValue([])
})

const baseSegment = {
  sid: '20',
  original_sid: '20',
  segment: 'Hello world',
  translation: 'Ciao mondo',
  unlocked: false,
}

const makeMatch = (overrides = {}) => ({
  id: '1',
  segment: 'source text',
  translation: 'target text',
  created_by: 'MyMemory',
  match: '95',
  source: 'en-US',
  target: 'it-IT',
  ...overrides,
})

const renderComponent = (props = {}, contextValue = {clientConnected: true}) =>
  render(
    <SegmentContext.Provider value={contextValue}>
      <SegmentFooterTabMatches
        code="tm"
        active_class="active"
        tab_class="matches"
        segment={baseSegment}
        {...props}
      />
    </SegmentContext.Provider>,
  )

describe('SegmentFooterTabMatches', () => {
  test('calls getContributions on mount', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent()
    expect(SegmentActions.getContributions).toHaveBeenCalledWith(
      '20',
      undefined,
    )
  })

  test('registers and unregisters listeners on mount/unmount', () => {
    const {unmount} = renderComponent()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      expect.any(Function),
    )
    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      CatToolConstants.UPDATE_TM_KEYS,
      expect.any(Function),
    )

    unmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      expect.any(Function),
    )
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      CatToolConstants.UPDATE_TM_KEYS,
      expect.any(Function),
    )
  })

  test('renders loader when clientConnected and no matches yet', () => {
    renderComponent()
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('renders error component when clientConnected is false', () => {
    renderComponent({}, {clientConnected: false})
    expect(
      screen.getByText(/unable to provide access to language resources/i),
    ).toBeInTheDocument()
  })

  test('renders "no matches" message with support link when mt_enabled is true', () => {
    global.config.mt_enabled = true
    renderComponent({
      segment: {...baseSegment, contributions: {matches: []}},
    })
    expect(
      screen.getByText(/No matches could be found for this segment/i),
    ).toBeInTheDocument()
  })

  test('renders "no matches" message without support link when mt_enabled is false', () => {
    global.config.mt_enabled = false
    renderComponent({
      segment: {...baseSegment, contributions: {matches: []}},
    })
    expect(
      screen.getByText('No match found for this segment'),
    ).toBeInTheDocument()
    global.config.mt_enabled = true
  })

  test('renders match items with origin info', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch()]},
      },
    })
    expect(document.querySelector('.suggestion-item')).toBeInTheDocument()
    expect(screen.getByText('MyMemory')).toBeInTheDocument()
  })

  test('renders the trash icon for a publicly deletable match and calls deleteContribution', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch({id: '5'})]},
      },
    })
    const trash = document.querySelector('.trash')
    expect(trash).toBeInTheDocument()
    fireEvent.click(trash)
    expect(SegmentActions.deleteContribution).toHaveBeenCalledWith(
      'source text',
      'target text',
      '5',
      '20',
    )
  })

  test('does not render trash icon for disabled (id 0) matches', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch({id: '0'})]},
      },
    })
    expect(document.querySelector('.trash')).not.toBeInTheDocument()
  })

  test('renders trash icon for an owned TM key match', () => {
    CatToolStore.getJobTmKeys.mockReturnValue([
      {key: 'memkey', w: 1},
    ])
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {
          matches: [makeMatch({id: '2', memory_key: 'memkey'})],
        },
      },
    })
    expect(document.querySelector('.trash')).toBeInTheDocument()
  })

  test('double click on suggestion triggers SegmentActions with delay', () => {
    jest.useFakeTimers()
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch()]},
      },
    })
    fireEvent.doubleClick(document.querySelector('.suggestion-item'))
    act(() => {
      jest.advanceTimersByTime(200)
    })
    expect(SegmentActions.setFocusOnEditArea).toHaveBeenCalled()
    expect(SegmentActions.disableTPOnSegment).toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('shows More/Fewer toggle button when more than 3 matches exist', () => {
    const matches = Array.from({length: 5}).map((_, i) =>
      makeMatch({id: String(i)}),
    )
    renderComponent({
      segment: {...baseSegment, contributions: {matches}},
    })
    const moreButton = screen.getByText('More')
    expect(moreButton).toBeInTheDocument()
    fireEvent.click(moreButton)
    expect(screen.getByText('Fewer')).toBeInTheDocument()
  })

  test('renders engine error and warning messages', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {
          matches: [],
          error: true,
          errors: [
            {code: '-2001', message: 'Engine failed'},
            {code: '-2002', message: 'Engine degraded'},
            {code: '-9999', message: 'ignored code'},
          ],
        },
      },
    })
    expect(screen.getByText('Error: Engine failed')).toBeInTheDocument()
    expect(screen.getByText('Warning: Engine degraded')).toBeInTheDocument()
    expect(screen.queryByText(/ignored code/)).not.toBeInTheDocument()
  })

  test('applies yellow variant class and penalty tooltip for cross-language match', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {
          matches: [makeMatch({source: 'fr-FR', penalty: 0.05})],
        },
      },
    })
    expect(document.querySelector('.per-yellow-variant')).toBeInTheDocument()
    expect(document.querySelector('.per-red-outline')).toBeInTheDocument()
    expect(screen.getByText('-5%')).toBeInTheDocument()
  })

  test('CHOOSE_CONTRIBUTION event for matching sid triggers suggestion selection', () => {
    jest.useFakeTimers()
    const TranslationMatches = require('./utils/translationMatches').default
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch()]},
      },
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.CHOOSE_CONTRIBUTION, '20', 0)
    })
    act(() => {
      jest.advanceTimersByTime(200)
    })
    expect(TranslationMatches.copySuggestionInEditarea).toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('copying selected text calls clipboard.writeText', async () => {
    const getSelectionSpy = jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({toString: () => 'clip text'})
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {matches: [makeMatch()]},
      },
    })
    const container = document.querySelector('#segment-20-matches')
    await act(async () => {
      fireEvent.copy(container)
    })
    expect(global.navigator.clipboard.writeText).toHaveBeenCalledWith(
      'clip text',
    )
    getSelectionSpy.mockRestore()
  })

  test('renders quality info when sentence_confidence is present', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        contributions: {
          matches: [makeMatch({match: 'MT', sentence_confidence: 0.9})],
        },
      },
    })
    expect(document.querySelector('.graysmall-details')).toBeInTheDocument()
  })
})

describe('SegmentFooterTabMatches.prototype.copyText', () => {
  afterEach(() => {
    jest.restoreAllMocks()
  })

  test('does not reject when the browser denies clipboard permission', async () => {
    jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({toString: () => 'some matched text'})
    navigator.clipboard = {
      writeText: jest
        .fn()
        .mockRejectedValue(new DOMException('denied', 'NotAllowedError')),
    }

    await expect(
      SegmentFooterTabMatches.prototype.copyText({preventDefault: jest.fn()}),
    ).resolves.not.toThrow()

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      'some matched text',
    )
  })
})
