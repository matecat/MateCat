import React from 'react'
import {render, screen, act} from '@testing-library/react'
import SubHeaderContainer from './SubHeaderContainer'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import SearchUtils from './search/searchUtils'
import SegmentFilterUtils from './segment_filter/segment_filter'

// jsdom has no ResizeObserver; SubHeaderContainer uses it (via useResizeObserver) to track
// the active panel's height. Captures the callback so tests can simulate a real resize (the
// base stub used by Accordion.test.js is a no-op and can't do that).
let resizeObserverCallback
global.ResizeObserver = class ResizeObserver {
  constructor(callback) {
    resizeObserverCallback = callback
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

const simulateContentResize = (height) => {
  act(() => {
    resizeObserverCallback([
      {borderBoxSize: {blockSize: height, inlineSize: 0}, target: {}},
    ])
  })
}

// Simulates the browser finishing the #header-bars-wrapper height-collapse animation,
// which is what actually unmounts the panel that just closed (see SubHeaderContainer.js).
// Built by hand rather than via fireEvent.transitionEnd: jsdom has no native
// TransitionEvent, so testing-library falls back to a plain Event whose constructor
// silently drops unrecognized init keys like propertyName.
const dispatchTransitionEnd = (propertyName) => {
  act(() => {
    const event = new Event('transitionend', {bubbles: true})
    event.propertyName = propertyName
    // eslint-disable-next-line testing-library/no-node-access
    document.getElementById('header-bars-wrapper').dispatchEvent(event)
  })
}
const endCloseTransition = () => dispatchTransitionEnd('height')

jest.mock('./search/Search', () => ({active}) => (
  <div data-testid="search" data-active={String(!!active)} />
))
jest.mock('./segment_filter/SegmentsFilter', () => ({active}) => (
  <div data-testid="segments-filter" data-active={String(!!active)} />
))
jest.mock('./QAComponent', () => ({active}) => (
  <div data-testid="qa-component" data-active={String(!!active)} />
))
jest.mock('./bulk_selection_bar/BulkSelectionBar', () => ({active}) => (
  <div data-testid="bulk-selection-bar" data-active={String(!!active)} />
))

beforeEach(() => {
  global.config = {isReview: false, searchable_statuses: []}
})

test('always renders search, qa and the bulk selection bar', () => {
  render(<SubHeaderContainer filtersEnabled={false} />)
  expect(screen.getByTestId('search')).toBeInTheDocument()
  expect(screen.getByTestId('qa-component')).toBeInTheDocument()
  expect(screen.getByTestId('bulk-selection-bar')).toBeInTheDocument()
  expect(screen.getByTestId('bulk-selection-bar')).toHaveAttribute(
    'data-active',
    'true',
  )
})

test('only renders the segments filter when filtersEnabled is true', () => {
  const {rerender} = render(<SubHeaderContainer filtersEnabled={false} />)
  expect(screen.queryByTestId('segments-filter')).not.toBeInTheDocument()

  rerender(<SubHeaderContainer filtersEnabled={true} />)
  expect(screen.getByTestId('segments-filter')).toBeInTheDocument()
})

test('shows the requested container and hides the others when told to via the store', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'false',
  )

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'qaComponent')
  })
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'true',
  )
  // search stays mounted until the close-transition confirms the panel collapsed
  endCloseTransition()
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
  })
  expect(screen.getByTestId('segments-filter')).toHaveAttribute(
    'data-active',
    'true',
  )
})

test('toggles a container open and closed via the store', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'search')
  })
  endCloseTransition()
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')
})

test('closes every container when the subheader is closed', () => {
  render(<SubHeaderContainer filtersEnabled={true} />)

  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

  act(() => {
    CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
  })
  endCloseTransition()
  expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')
  expect(screen.getByTestId('qa-component')).toHaveAttribute(
    'data-active',
    'false',
  )
  expect(screen.getByTestId('segments-filter')).toHaveAttribute(
    'data-active',
    'false',
  )
})

describe('close-transition unmount timing', () => {
  test('keeps the panel mounted until the close transition ends, instead of unmounting it immediately', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })
    // the bug this guards against: unmounting (active -> false) right away, before the
    // height-collapse animation has anything left to animate
    expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')

    endCloseTransition()
    expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'false')
  })

  test('ignores a transitionend for an unrelated CSS property', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })

    dispatchTransitionEnd('opacity')
    expect(screen.getByTestId('search')).toHaveAttribute('data-active', 'true')
  })

  test('re-mounts a panel instantly when reopened before the close transition ends', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
    })

    expect(screen.getByTestId('segments-filter')).toHaveAttribute(
      'data-active',
      'true',
    )
  })

  test('keeps the QA panel mounted until its own close transition ends too', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'qaComponent')
    })
    expect(screen.getByTestId('qa-component')).toHaveAttribute(
      'data-active',
      'true',
    )

    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })
    expect(screen.getByTestId('qa-component')).toHaveAttribute(
      'data-active',
      'true',
    )

    endCloseTransition()
    expect(screen.getByTestId('qa-component')).toHaveAttribute(
      'data-active',
      'false',
    )
  })
})

describe('SearchUtils/SegmentFilterUtils stay in sync with the real state', () => {
  // Regression for: search open -> switch to filter -> switch back to search
  // sometimes closed *everything* instead of reopening search. SearchButton/
  // SegmentsFilterButton decide whether a click should open or close by reading
  // SearchUtils.searchOpen / SegmentFilterUtils.open; those flags used to only be
  // written by the search/filter code's own open/close paths, so switching to the
  // *other* panel left the dismissed one's flag stuck "open". The next click on that
  // panel's button then saw a stale "already open" and fired a close instead of an
  // open, which closed every panel.
  test('switching from search to the filter clears the stale SearchUtils.searchOpen flag', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    expect(SearchUtils.searchOpen).toBe(true)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
    })
    expect(SearchUtils.searchOpen).toBe(false)
    expect(SegmentFilterUtils.open).toBe(true)
  })

  test('switching from the filter to search clears the stale SegmentFilterUtils.open flag', () => {
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
    })
    expect(SegmentFilterUtils.open).toBe(true)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    expect(SegmentFilterUtils.open).toBe(false)
    expect(SearchUtils.searchOpen).toBe(true)
  })
})

describe('wrapper height tracks the active panel directly, not a class toggle', () => {
  // jsdom never actually lays elements out, so scrollHeight is always 0 unless stubbed.
  const setScrollHeight = (value) => {
    Object.defineProperty(window.HTMLElement.prototype, 'scrollHeight', {
      configurable: true,
      value,
    })
  }
  // eslint-disable-next-line testing-library/no-node-access
  const getWrapper = () => document.getElementById('header-bars-wrapper')

  afterEach(() => {
    delete window.HTMLElement.prototype.scrollHeight
  })

  test('sets an explicit pixel height, measured from the content, when a panel opens', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })

    expect(getWrapper()).toHaveStyle({height: '180px'})
  })

  test('transitions straight to the new height when switching panels, since the wrapper never closes', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    expect(getWrapper()).toHaveStyle({height: '180px'})

    setScrollHeight(260)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
    })
    expect(getWrapper()).toHaveStyle({height: '260px'})
  })

  test('collapses back to 0px once everything closes', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })

    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })
    expect(getWrapper()).toHaveStyle({height: '0px'})
  })

  // Regression: qaComponent used to sit outside this measured/animated wrapper entirely —
  // opening only the QA panel (search and segmentFilter both closed) left the wrapper
  // collapsed at 0 height with QA's content invisible inside it.
  test('opens and measures height for the QA panel too, not just search/filter', () => {
    setScrollHeight(220)
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'qaComponent')
    })

    expect(getWrapper()).toHaveStyle({height: '220px'})
  })
})

describe('wrapper overflow unclips once the open transition settles', () => {
  // Regression for: a fully-open Filters panel clipped its own Data sample popup —
  // the wrapper's height is clamped to the *panel's* measured height, and overflow
  // stayed hidden the whole time, so anything the panel renders beyond that height
  // (an absolutely-positioned dropdown) was invisible even though it had "opened".
  const setScrollHeight = (value) => {
    Object.defineProperty(window.HTMLElement.prototype, 'scrollHeight', {
      configurable: true,
      value,
    })
  }
  // eslint-disable-next-line testing-library/no-node-access
  const getWrapper = () => document.getElementById('header-bars-wrapper')

  afterEach(() => {
    delete window.HTMLElement.prototype.scrollHeight
  })

  test('stays clipped while a panel is opening', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)

    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })

    expect(getWrapper()).toHaveStyle({overflow: 'hidden'})
  })

  test('switches to overflow visible once the open transition finishes', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })

    dispatchTransitionEnd('height') // the browser confirming the open transition finished

    expect(getWrapper()).toHaveStyle({overflow: 'visible'})
  })

  test('clips again once it starts closing', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    dispatchTransitionEnd('height')
    expect(getWrapper()).toHaveStyle({overflow: 'visible'})

    act(() => {
      CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
    })
    expect(getWrapper()).toHaveStyle({overflow: 'hidden'})
  })

  // Regression: isOpen never goes false while switching directly between two panels, so
  // a naive "only reset on close" guard would leave overflow:visible (set by a previous
  // open settling) in place right through the switch's resize transition, letting the
  // incoming panel's content spill out before the box has finished resizing to fit it.
  test('clips again while switching directly between panels, even though it never closes', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    dispatchTransitionEnd('height')
    expect(getWrapper()).toHaveStyle({overflow: 'visible'})

    setScrollHeight(260)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'segmentFilter')
    })
    expect(getWrapper()).toHaveStyle({overflow: 'hidden'})

    dispatchTransitionEnd('height')
    expect(getWrapper()).toHaveStyle({overflow: 'visible'})
  })

  // Regression: the same mounted panel's content quietly growing (e.g. a search-results
  // list) also changes the measured height and restarts the CSS transition, but it must
  // NOT re-clip — doing that flickers anything currently overflowing (e.g. an open Data
  // sample popup) hidden and back visible for no reason, since nothing about which panel
  // is showing actually changed.
  test('does not re-clip when the same mounted panel just grows taller', () => {
    setScrollHeight(180)
    render(<SubHeaderContainer filtersEnabled={true} />)
    act(() => {
      CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
    })
    dispatchTransitionEnd('height')
    expect(getWrapper()).toHaveStyle({overflow: 'visible'})

    simulateContentResize(240)

    expect(getWrapper()).toHaveStyle({overflow: 'visible'})
    expect(getWrapper()).toHaveStyle({height: '180px'}) // unchanged: scrollHeight, not stubbed
  })
})
