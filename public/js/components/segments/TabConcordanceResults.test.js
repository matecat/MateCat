import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
import {TabConcordanceResults} from './TabConcordanceResults'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

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

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToHtml: jest.fn((text) => text),
  },
}))

jest.mock('js-cookie', () => ({
  get: jest.fn(),
  set: jest.fn(),
}))

beforeAll(() => {
  global.config = {
    ...global.config,
    isSourceRTL: false,
    isTargetRTL: false,
    target_rfc: 'it-IT',
    source_rfc: 'en-US',
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

const segment = {sid: '3'}

describe('TabConcordanceResults', () => {
  test('renders nothing before any results arrive', () => {
    const {container} = render(
      <TabConcordanceResults segment={segment} isActive={true} />,
    )
    expect(
      container.querySelector('.segment-footer-tab-concordance-results')
        .childElementCount,
    ).toBe(0)
  })

  test('does not register a listener when isActive is false', () => {
    render(<TabConcordanceResults segment={segment} isActive={false} />)
    expect(SegmentStore.addListener).not.toHaveBeenCalled()
  })

  test('registers and unregisters CONCORDANCE_RESULT listener when active', () => {
    const {unmount} = render(
      <TabConcordanceResults segment={segment} isActive={true} />,
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      SegmentConstants.CONCORDANCE_RESULT,
      expect.any(Function),
    )
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      SegmentConstants.CONCORDANCE_RESULT,
      expect.any(Function),
    )
  })

  test('renders "no matches" message for an empty result set', () => {
    render(<TabConcordanceResults segment={segment} isActive={true} />)
    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', [])
    })
    expect(
      screen.getByText(/Can't find any matches/i),
    ).toBeInTheDocument()
  })

  test('ignores non-array result data', () => {
    render(<TabConcordanceResults segment={segment} isActive={true} />)
    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', null)
    })
    expect(
      screen.getByText(/Can't find any matches/i),
    ).toBeInTheDocument()
  })

  test('renders results and filters out entries missing segment/translation', () => {
    render(<TabConcordanceResults segment={segment} isActive={true} />)
    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', [
        {
          id: '1',
          segment: 'Hello #{world}#',
          translation: 'Ciao #{mondo}#',
          created_by: 'MyMemory',
          last_update_date: '2024-02-01',
          source: 'en-US',
          target: 'it-IT',
        },
        {id: '2', segment: '', translation: ''},
      ])
    })

    expect(screen.getByText('MyMemory')).toBeInTheDocument()
    expect(screen.getByText('mondo')).toBeInTheDocument()
    expect(screen.getByText('world')).toBeInTheDocument()
  })

  test('shows the yellow variant marker when language pair differs from job', () => {
    render(<TabConcordanceResults segment={segment} isActive={true} />)
    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', [
        {
          id: '1',
          segment: 'Hello',
          translation: 'Ciao',
          created_by: 'MyMemory',
          last_update_date: '2024-02-01',
          source: 'fr-FR',
          target: 'it-IT',
        },
      ])
    })

    expect(document.querySelector('.per-yellow-variant')).toBeInTheDocument()
  })

  test('shows the "More"/"Fewer" toggle when results exceed the display limit', () => {
    const Cookies = require('js-cookie')
    Cookies.get.mockReturnValue('false')
    render(<TabConcordanceResults segment={segment} isActive={true} />)

    const results = Array.from({length: 5}).map((_, index) => ({
      id: String(index),
      segment: `Source ${index}`,
      translation: `Target ${index}`,
      created_by: 'MyMemory',
      last_update_date: '2024-02-01',
      source: 'en-US',
      target: 'it-IT',
    }))

    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', results)
    })

    expect(screen.getAllByRole('listitem').length).toBeGreaterThan(0)
    const moreButton = screen.getByText('More')
    fireEvent.click(moreButton)

    expect(Cookies.set).toHaveBeenCalledWith(
      'segment_footer_extendend_concordance',
      true,
      expect.objectContaining({expires: 3650, secure: true}),
    )
  })

  test('initializes as extended when the cookie is already true and toggles back', () => {
    const Cookies = require('js-cookie')
    Cookies.get.mockReturnValue('true')
    render(<TabConcordanceResults segment={segment} isActive={true} />)

    const results = Array.from({length: 5}).map((_, index) => ({
      id: String(index),
      segment: `Source ${index}`,
      translation: `Target ${index}`,
      created_by: 'MyMemory',
      last_update_date: '2024-02-01',
      source: 'en-US',
      target: 'it-IT',
    }))

    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', results)
    })

    const fewerButton = screen.getByText('Fewer')
    fireEvent.click(fewerButton)

    expect(Cookies.set).toHaveBeenCalledWith(
      'segment_footer_extendend_concordance',
      false,
      expect.objectContaining({expires: 3650, secure: true}),
    )
  })

  test('exposes an imperative reset() handle that clears results', () => {
    const ref = React.createRef()
    render(
      <TabConcordanceResults segment={segment} isActive={true} ref={ref} />,
    )
    act(() => {
      SegmentStore.__emit(SegmentConstants.CONCORDANCE_RESULT, '3', [
        {
          id: '1',
          segment: 'Hello',
          translation: 'Ciao',
          created_by: 'MyMemory',
          last_update_date: '2024-02-01',
          source: 'en-US',
          target: 'it-IT',
        },
      ])
    })
    expect(screen.getByText('MyMemory')).toBeInTheDocument()

    act(() => {
      ref.current.reset()
    })

    expect(screen.queryByText('MyMemory')).not.toBeInTheDocument()
  })
})
