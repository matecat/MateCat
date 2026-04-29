import React from 'react'
import {render, screen, act} from '@testing-library/react'
import {SegmentFooterTabAiAlternatives} from './SegmentFooterTabAiAlternatives'
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

jest.mock('../../utils/segmentUtils', () => ({
  getSegmentContext: () => ({contextListBefore: [], contextListAfter: []}),
}))

jest.mock(
  '../../api/aiAlternartiveTranslations/aiAlternartiveTranslations',
  () => ({
    aiAlternartiveTranslations: jest.fn(() => new Promise(() => {})),
  }),
)

jest.mock('../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('../common/ButtonCopy', () => ({
  ButtonCopy: ({onClick, tooltip}) => (
    <button onClick={onClick} title={tooltip}>
      Copy
    </button>
  ),
}))

jest.mock('../common/Button/Button', () => ({
  Button: ({children, onClick}) => (
    <button onClick={onClick}>{children}</button>
  ),
  BUTTON_MODE: {OUTLINE: 'outline'},
  BUTTON_TYPE: {DEFAULT: 'default'},
}))

// --- Helpers ---

const defaultSegment = {
  sid: '1',
  id_job: '42',
  password: 'secret',
  segment: 'Hello world',
  translation: 'Ciao mondo',
  decodedSource: 'Hello world',
}

const defaultProps = {
  code: 'ai',
  active_class: 'active',
  tab_class: 'ai-tab',
  segment: defaultSegment,
}

const renderComponent = (props = {}) =>
  render(<SegmentFooterTabAiAlternatives {...defaultProps} {...props} />)

beforeAll(() => {
  global.config = {
    target_code: 'it-IT',
    source_code: 'en-US',
    isTargetRTL: false,
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

// --- Tests ---

describe('SegmentFooterTabAiAlternatives', () => {
  test('renders loading spinner initially', () => {
    renderComponent()
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('registers and unregisters store listeners on mount/unmount', () => {
    const {unmount} = renderComponent()

    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.AI_ALTERNATIVES,
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.AI_ALTERNATIVES_SUGGESTION,
      expect.any(Function),
    )

    unmount()

    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.AI_ALTERNATIVES,
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.AI_ALTERNATIVES_SUGGESTION,
      expect.any(Function),
    )
  })

  test('shows loading spinner when AI_ALTERNATIVES event fires', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES, {
        text: 'mondo',
      })
    })

    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('renders alternatives when valid data is received', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES, {text: 'mondo'})
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES_SUGGESTION, {
        data: {
          has_error: false,
          message: [
            {alternative: 'Terra', context: 'More formal'},
            {alternative: 'Pianeta', context: 'Poetic'},
          ],
        },
      })
    })

    expect(screen.getByText('Alternatives for:')).toBeInTheDocument()
    expect(screen.getByText('More formal')).toBeInTheDocument()
    expect(screen.getByText('Poetic')).toBeInTheDocument()
  })

  test('renders generic error without retry when has_error is true and no error_code', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES, {text: 'mondo'})
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES_SUGGESTION, {
        data: {has_error: true, message: 'Something broke'},
      })
    })

    expect(
      screen.getByText('Something went wrong. Please try again in a moment.'),
    ).toBeInTheDocument()
    expect(screen.getByText('Retry')).toBeInTheDocument()
  })

  test('renders "no alternatives found" error without retry button when error_code is 1', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES, {text: 'mondo'})
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES_SUGGESTION, {
        data: {has_error: true, error_code: 1, message: 'none'},
      })
    })

    expect(
      screen.getByText('No alternative translations found for:'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Retry')).not.toBeInTheDocument()
  })

  test('renders correct container id', () => {
    const {container} = renderComponent()
    expect(container.querySelector('#segment-1-ai-tab')).toBeInTheDocument()
  })

  test('copy buttons are rendered for each alternative', () => {
    renderComponent()

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES, {text: 'mondo'})
    })

    act(() => {
      SegmentStore.__emit(SegmentConstants.AI_ALTERNATIVES_SUGGESTION, {
        data: {
          has_error: false,
          message: [
            {alternative: 'Terra', context: 'ctx1'},
            {alternative: 'Pianeta', context: 'ctx2'},
          ],
        },
      })
    })

    expect(screen.getAllByTitle('Copy suggestion')).toHaveLength(2)
  })
})
