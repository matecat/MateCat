import React from 'react'
import {render, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentHeader from './SegmentHeader'
import SegmentStore from '../../stores/SegmentStore'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../../stores/SegmentStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
  getPrevSegment: jest.fn(),
  getNextSegment: jest.fn(),
  getSegmentByIdToJS: jest.fn(),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  SET_SEGMENT_HEADER: 'SET_SEGMENT_HEADER',
  HIDE_SEGMENT_HEADER: 'HIDE_SEGMENT_HEADER',
  CHARACTER_COUNTER: 'CHARACTER_COUNTER',
}))

jest.mock('./utils/translationMatches', () => ({
  getPercentTextForMatch: jest.fn(() => '100%'),
}))

const renderHeader = (props = {}, contextValue = {}) => {
  const defaultProps = {
    sid: '10-1',
    segmentOpened: false,
    saving: false,
    repetition: false,
    splitted: false,
    autopropagated: false,
  }
  return render(
    <ApplicationWrapperContext.Provider value={contextValue}>
      <SegmentHeader {...defaultProps} {...props} />
    </ApplicationWrapperContext.Provider>,
  )
}

const getListenerCallback = (eventName) => {
  const call = SegmentStore.addListener.mock.calls.find(
    ([event]) => event === eventName,
  )
  return call ? call[1] : undefined
}

describe('SegmentHeader', () => {
  beforeEach(() => {
    SegmentStore.getPrevSegment.mockReturnValue({internal_id: 1})
    SegmentStore.getNextSegment.mockReturnValue({internal_id: 2})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({internal_id: 3})
  })

  test('renders a closed header with no autopropagation/repetition markers', () => {
    const {container} = renderHeader()
    expect(container.querySelector('.header-closed')).toBeInTheDocument()
    expect(container.querySelector('.header.toggle')).not.toBeInTheDocument()
  })

  test('renders a closed header with the autopropagated marker', () => {
    const {container} = renderHeader({autopropagated: true, splitted: false})
    expect(container.textContent).toContain('Autopropagated')
  })

  test('renders a closed header with the repetition marker', () => {
    const {container} = renderHeader({repetition: true})
    expect(container.textContent).toContain('Repetition')
  })

  test('does not show repetition/autopropagated markers when splitted', () => {
    const {container} = renderHeader({
      repetition: true,
      autopropagated: true,
      splitted: true,
    })
    expect(container.textContent).not.toContain('Repetition')
    expect(container.textContent).not.toContain('Autopropagated')
  })

  test('renders the saving indicator in the closed header when saving', () => {
    const {container} = renderHeader({saving: true})
    expect(container.textContent).toContain('Saving')
  })

  test('renders an open header when segmentOpened is true', () => {
    const {container} = renderHeader({segmentOpened: true})
    expect(
      container.querySelector(`#segment-10-1-header`),
    ).toBeInTheDocument()
  })

  test('shows saving indicator inside the open header', () => {
    const {container} = renderHeader({segmentOpened: true, saving: true})
    expect(container.textContent).toContain('Saving')
  })

  test('registers and unregisters SegmentStore listeners on mount/unmount', () => {
    const {unmount} = renderHeader()
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      'SET_SEGMENT_HEADER',
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      'HIDE_SEGMENT_HEADER',
      expect.any(Function),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      'CHARACTER_COUNTER',
      expect.any(Function),
    )
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      'SET_SEGMENT_HEADER',
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      'HIDE_SEGMENT_HEADER',
      expect.any(Function),
    )
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      'CHARACTER_COUNTER',
      expect.any(Function),
    )
  })

  test('marks isGroupByTransUnit when adjacent internal ids match', () => {
    SegmentStore.getPrevSegment.mockReturnValue({internal_id: 5})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({internal_id: 5})
    SegmentStore.getNextSegment.mockReturnValue({internal_id: 9})
    const {container} = renderHeader({segmentOpened: true})
    act(() => {
      const cb = getListenerCallback('CHARACTER_COUNTER')
      cb({sid: '10-1', counter: 2, limit: 10, segmentCharacters: 2})
    })
    expect(container.textContent).toContain('Unit characters')
  })

  test('changePercentuage sets percentage/visible state and renders the h2', () => {
    const {container} = renderHeader({segmentOpened: true})
    act(() => {
      const cb = getListenerCallback('SET_SEGMENT_HEADER')
      cb('10-1', {}, 'perfect-match', 'Jest User')
    })
    const heading = container.querySelector('h2')
    expect(heading).toBeInTheDocument()
    expect(heading).toHaveAttribute('title', 'Created by Jest User')
    expect(heading.textContent).toBe('100%')
  })

  test('changePercentuage ignores updates for a different segment id', () => {
    const {container} = renderHeader({segmentOpened: true, sid: '10-1'})
    act(() => {
      const cb = getListenerCallback('SET_SEGMENT_HEADER')
      cb('other-sid', {}, 'perfect-match', 'Jest User')
    })
    expect(container.querySelector('h2')).not.toBeInTheDocument()
  })

  test('hideHeader hides the visible percentage', () => {
    const {container} = renderHeader({segmentOpened: true, sid: '10-1'})
    act(() => {
      getListenerCallback('SET_SEGMENT_HEADER')('10-1', {}, 'perfect-match', 'Jest User')
    })
    expect(container.querySelector('h2')).toBeInTheDocument()

    act(() => {
      getListenerCallback('HIDE_SEGMENT_HEADER')('10-1')
    })
    expect(container.querySelector('h2')).not.toBeInTheDocument()
  })

  test('shows the character counter with error class when over limit', () => {
    const {container} = renderHeader({segmentOpened: true, sid: '10-1'})
    act(() => {
      getListenerCallback('CHARACTER_COUNTER')({
        sid: '10-1',
        counter: 15,
        limit: 10,
        segmentCharacters: 15,
      })
    })
    expect(
      container.querySelector('.segment-counter-limit-error'),
    ).toBeInTheDocument()
    expect(container.querySelector('.segment-counter-limit').textContent).toBe(
      '10',
    )
  })

  test('sets isActiveCharactersCounter from context userInfo on update', () => {
    const contextValue = {
      userInfo: {metadata: {character_counter: true}},
    }
    const {container, rerender} = render(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={true}
          saving={false}
          repetition={false}
          splitted={false}
          autopropagated={false}
        />
      </ApplicationWrapperContext.Provider>,
    )
    act(() => {
      getListenerCallback('CHARACTER_COUNTER')({
        sid: '10-1',
        counter: 2,
        limit: 0,
        segmentCharacters: 2,
      })
    })
    rerender(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={true}
          saving={false}
          repetition={false}
          splitted={false}
          autopropagated={false}
        />
      </ApplicationWrapperContext.Provider>,
    )
    expect(container.querySelector('.segment-counter')).toBeInTheDocument()
  })

  test('re-forces autopropagated back to true within the same render when the prop becomes true again after being genuinely cleared', () => {
    const contextValue = {}
    const {container, rerender} = renderHeader(
      {segmentOpened: false, autopropagated: true},
      contextValue,
    )
    expect(container.textContent).toContain('Autopropagated')

    // A prop change to false does NOT reset internal state on its own (only the
    // listener callbacks do) -- the marker stays visible purely from prior state.
    rerender(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={false}
          saving={false}
          repetition={false}
          splitted={false}
          autopropagated={false}
        />
      </ApplicationWrapperContext.Provider>,
    )
    expect(container.textContent).toContain('Autopropagated')

    // Now that props.autopropagated is genuinely false, hideHeader can actually clear it.
    act(() => {
      getListenerCallback('HIDE_SEGMENT_HEADER')('10-1')
    })
    expect(container.textContent).not.toContain('Autopropagated')

    // Re-render with the prop true again: a naive useEffect-based sync would commit one
    // frame showing no marker before a follow-up render fixed it. The in-render adjustment
    // must re-force it back to true within this same render pass, with nothing ever
    // committing the intermediate false state.
    rerender(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={false}
          saving={false}
          repetition={false}
          splitted={false}
          autopropagated={true}
        />
      </ApplicationWrapperContext.Provider>,
    )
    expect(container.textContent).toContain('Autopropagated')
  })

  test('memo bails out (skips re-render) when props are shallow-equal', () => {
    const metadataSpy = jest.fn(() => ({character_counter: true}))
    const contextValue = {
      userInfo: {
        get metadata() {
          return metadataSpy()
        },
      },
    }
    const {rerender} = renderHeader(
      {segmentOpened: true, sid: '10-1'},
      contextValue,
    )
    const callsAfterFirstRender = metadataSpy.mock.calls.length
    expect(callsAfterFirstRender).toBeGreaterThan(0)

    rerender(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={true}
          saving={false}
          repetition={false}
          splitted={false}
          autopropagated={false}
        />
      </ApplicationWrapperContext.Provider>,
    )
    expect(metadataSpy.mock.calls.length).toBe(callsAfterFirstRender)
  })

  test('re-renders when a prop actually changes', () => {
    const metadataSpy = jest.fn(() => ({character_counter: true}))
    const contextValue = {
      userInfo: {
        get metadata() {
          return metadataSpy()
        },
      },
    }
    const {rerender} = renderHeader(
      {segmentOpened: true, sid: '10-1', saving: false},
      contextValue,
    )
    const callsAfterFirstRender = metadataSpy.mock.calls.length

    rerender(
      <ApplicationWrapperContext.Provider value={contextValue}>
        <SegmentHeader
          sid="10-1"
          segmentOpened={true}
          saving={true}
          repetition={false}
          splitted={false}
          autopropagated={false}
        />
      </ApplicationWrapperContext.Provider>,
    )
    expect(metadataSpy.mock.calls.length).toBeGreaterThan(callsAfterFirstRender)
  })
})
