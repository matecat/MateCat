import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
import {SegmentFooterTabAiAssistant} from './SegmentFooterTabAiAssistant'
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
    getAiSuggestion: jest.fn(() => undefined),
    helpAiAssistantWords: undefined,
  }
})

jest.mock('../../api/aiSuggestion/aiSuggestion', () => ({
  aiSuggestion: jest.fn(),
}))

jest.mock('../../api/getConcordance', () => ({
  getConcordance: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))

jest.mock('../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('./TabConcordanceResults', () => {
  const ReactLib = require('react')
  return {
    TabConcordanceResults: ReactLib.forwardRef((props, ref) =>
      ReactLib.createElement('div', {
        'data-testid': 'tab-concordance-results',
        ref,
      }),
    ),
  }
})

const defaultSegment = {
  sid: '1',
  decodedSource: 'Hello world',
}

const defaultProps = {
  code: 'ai',
  active_class: 'active',
  tab_class: 'ai-assistant',
  segment: defaultSegment,
}

const renderComponent = (props = {}) =>
  render(<SegmentFooterTabAiAssistant {...defaultProps} {...props} />)

beforeAll(() => {
  global.config = {
    ...global.config,
    source_code: 'en-US',
    target_code: 'it-IT',
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('SegmentFooterTabAiAssistant', () => {
  test('renders loading spinner initially', () => {
    renderComponent()
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('registers listeners on mount and removes them on unmount', () => {
    const {unmount} = renderComponent()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.HELP_AI_ASSISTANT,
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.AI_SUGGESTION,
      expect.any(Function),
    )

    unmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.HELP_AI_ASSISTANT,
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.AI_SUGGESTION,
      expect.any(Function),
    )
  })

  test('requests suggestion and renders it on success', () => {
    const {aiSuggestion} = require('../../api/aiSuggestion/aiSuggestion')
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.HELP_AI_ASSISTANT, {
        sid: '1',
        value: 'world',
      })
    })
    expect(aiSuggestion).toHaveBeenCalledWith(
      expect.objectContaining({idSegment: '1', words: 'world'}),
    )

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_SUGGESTION, {
        sid: '1',
        suggestion: 'Meaning of world',
        isCompleted: true,
        hasError: false,
      })
    })

    expect(screen.getByText('Meaning of world')).toBeInTheDocument()
    expect(screen.getByText('Meaning in context')).toBeInTheDocument()
  })

  test('ignores suggestion events for a different segment', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_SUGGESTION, {
        sid: '999',
        suggestion: 'unrelated',
        isCompleted: true,
        hasError: false,
      })
    })

    expect(screen.queryByText('unrelated')).not.toBeInTheDocument()
  })

  test('renders error message when suggestion has error', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.HELP_AI_ASSISTANT, {
        sid: '1',
        value: 'nonexistent-word',
      })
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_SUGGESTION, {
        sid: '1',
        suggestion: 'error text',
        isCompleted: false,
        hasError: true,
      })
    })

    expect(
      screen.getByText(/The service is at capacity right now/i),
    ).toBeInTheDocument()
  })

  test('submitting feedback shows thank you message', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.HELP_AI_ASSISTANT, {
        sid: '1',
        value: 'world',
      })
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_SUGGESTION, {
        sid: '1',
        suggestion: 'Meaning of world',
        isCompleted: true,
        hasError: false,
      })
    })

    const likeIcon = document.querySelector('.feedback-icons .like')
    fireEvent.click(likeIcon)

    expect(screen.getByText('Thank you!')).toBeInTheDocument()
  })

  test('renders TM matches loader while loading', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.HELP_AI_ASSISTANT, {
        sid: '1',
        value: 'world',
      })
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_SUGGESTION, {
        sid: '1',
        suggestion: 'Meaning of world',
        isCompleted: true,
        hasError: false,
      })
    })

    expect(screen.getByText('TM matches')).toBeInTheDocument()
    expect(
      document.querySelector('.tm-matches .loading-container .loader'),
    ).toBeInTheDocument()

    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, {})
    })

    expect(
      document.querySelector('.tm-matches .loading-container'),
    ).not.toBeInTheDocument()
  })
})
