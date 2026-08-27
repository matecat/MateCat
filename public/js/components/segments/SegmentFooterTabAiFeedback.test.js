import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
import {SegmentFooterTabAiFeedback} from './SegmentFooterTabAiFeedback'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

// --- Mocks ---

jest.mock('../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    addListener: jest.fn((event, cb) => {
      listeners[event] = cb
    }),
    removeListener: jest.fn(),
    __emit: (event, data) => listeners[event] && listeners[event](data),
  }
})

jest.mock('../../stores/CatToolStore', () => ({
  getJobMetadata: () => ({
    project: {mt_extra: {lara_style: 'faithful'}},
  }),
}))

jest.mock('../../api/aiFeedback/aiFeedback', () => ({
  aiFeedback: jest.fn(() => new Promise(() => {})),
}))

// The component keeps a module-level MemoizeRequest cache keyed by
// segment/source/target/style. Every test here reuses the same defaultSegment,
// so a real cache would leak a cached response from one test into the next
// (e.g. a later "retry after error" test synchronously receiving an earlier
// test's cached success payload instead of re-entering the loading state).
// Back the mock with a store cleared in afterEach so each test starts empty
// while the one test that exercises caching directly still sees real behavior.
const mockCacheStore = new Map()

jest.mock('../../utils/MemoizeRequest', () => ({
  MemoizeRequest: jest.fn().mockImplementation(() => ({
    get: (key) => mockCacheStore.get(JSON.stringify(key)),
    set: (key, value) => mockCacheStore.set(JSON.stringify(key), value),
  })),
}))

jest.mock('../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToText: jest.fn((text) => text),
    excludeSomeTagsFromText: jest.fn((text) => text),
  },
}))

jest.mock('./utils/DraftMatecatUtils/tagUtils', () => ({
  __esModule: true,
  decodeTagsToUnicodeChar: jest.fn((text) => text),
}))

jest.mock('../common/Button/Button', () => ({
  Button: ({children, onClick}) => (
    <button onClick={onClick}>{children}</button>
  ),
  BUTTON_MODE: {OUTLINE: 'outline'},
  BUTTON_TYPE: {DEFAULT: 'default'},
}))

const defaultSegment = {
  sid: '1',
  segment: 'Hello world',
  translation: 'Ciao mondo',
  decodedSource: 'Hello world',
}

const defaultProps = {
  code: 'aifeedback',
  active_class: 'active',
  tab_class: 'ai-feedback',
  segment: defaultSegment,
}

const renderComponent = (props = {}) =>
  render(<SegmentFooterTabAiFeedback {...defaultProps} {...props} />)

beforeAll(() => {
  global.config = {
    ...global.config,
    source_code: 'en-US',
    target_code: 'it-IT',
  }
})

afterEach(() => {
  jest.clearAllMocks()
  mockCacheStore.clear()
})

describe('SegmentFooterTabAiFeedback', () => {
  test('renders loading spinner initially', () => {
    renderComponent()
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('registers and unregisters listeners', () => {
    const {unmount} = renderComponent()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.AI_FEEDBACK,
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.AI_FEEDBACK_SUGGESTION,
      expect.any(Function),
    )

    unmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.AI_FEEDBACK,
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.AI_FEEDBACK_SUGGESTION,
      expect.any(Function),
    )
  })

  test('renders feedback content when request resolves', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Could Be Improved', comment: 'Nice try'},
        },
      })
    })

    expect(screen.getByText('Could Be Improved')).toBeInTheDocument()
    expect(screen.getByText('Nice try')).toBeInTheDocument()
  })

  test('renders red badge for "Does Not Match Source" category', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Does Not Match Source', comment: 'Bad'},
        },
      })
    })

    expect(screen.getByText('Does Not Match Source')).toBeInTheDocument()
  })

  test('renders green badge for unknown category', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Great', comment: 'Perfect'},
        },
      })
    })

    expect(screen.getByText('Great')).toBeInTheDocument()
  })

  test('renders error and allows retry when request fails', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {has_error: true, message: 'boom'},
      })
    })

    expect(
      screen.getByText('Something went wrong. Please try again in a moment.'),
    ).toBeInTheDocument()

    fireEvent.click(screen.getByText('Retry'))
    // retry re-triggers the requestFeedback flow, resetting to loading state
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('submitting like feedback shows thank you message', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Could Be Improved', comment: 'Nice try'},
        },
      })
    })

    const likeIcon = document.querySelector('.feedback-icons .like')
    fireEvent.click(likeIcon)

    expect(screen.getByText('Thank you!')).toBeInTheDocument()
  })

  test('submitting dislike feedback shows thank you message with dislike icon active', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Could Be Improved', comment: 'Nice try'},
        },
      })
    })

    const dislikeIcon = document.querySelector('.feedback-icons .dislike')
    fireEvent.click(dislikeIcon)

    expect(screen.getByText('Thank you!')).toBeInTheDocument()
  })

  test('uses cached feedback and does not call aiFeedback API again', () => {
    const {aiFeedback} = require('../../api/aiFeedback/aiFeedback')
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK_SUGGESTION, {
        data: {
          has_error: false,
          message: {category: 'Could Be Improved', comment: 'Nice try'},
        },
      })
    })

    aiFeedback.mockClear()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })

    expect(screen.getByText('Nice try')).toBeInTheDocument()
    expect(aiFeedback).not.toHaveBeenCalled()
  })

  test('ignores a second concurrent AI_FEEDBACK request while one is pending', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })
    // Second call while requestingParams.current is still set (no resolution yet)
    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_FEEDBACK, {})
    })

    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })
})
