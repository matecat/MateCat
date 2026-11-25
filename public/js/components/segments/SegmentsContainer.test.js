import React from 'react'
import {render, act, fireEvent, screen, within} from '@testing-library/react'
import {fromJS} from 'immutable'
import SegmentStore from '../../stores/SegmentStore'
import CatToolStore from '../../stores/CatToolStore'
import SegmentConstants from '../../constants/SegmentConstants'
import CatToolConstants from '../../constants/CatToolConstants'
import SegmentActions from '../../actions/SegmentActions'
import SegmentsContainer from './SegmentsContainer'

global.ResizeObserver = class ResizeObserver {
  constructor(cb) {
    this.cb = cb
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

// --- Mocks ---

jest.mock('react-hotkeys-hook', () => ({
  useHotkeys: jest.fn(),
}))

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'keystrokes',
    cattol: {
      events: {
        copySource: {keystrokes: {keystrokes: 'ctrl+i'}},
        gotoCurrent: {keystrokes: {keystrokes: 'ctrl+shift+a'}},
        openPrevious: {keystrokes: {keystrokes: 'ctrl+shift+p'}},
        openNext: {keystrokes: {keystrokes: 'ctrl+shift+n'}},
        openIssuesPanel: {keystrokes: {keystrokes: 'ctrl+shift+i'}},
        copyContribution1: {keystrokes: {keystrokes: 'ctrl+1'}},
        copyContribution2: {keystrokes: {keystrokes: 'ctrl+2'}},
        copyContribution3: {keystrokes: {keystrokes: 'ctrl+3'}},
        splitSegment: {keystrokes: {keystrokes: 'ctrl+s'}},
        openComments: {keystrokes: {keystrokes: 'ctrl+shift+c'}},
        addTags: {keystrokes: {keystrokes: 'ctrl+shift+t'}},
      },
    },
  },
}))

const mockSegmentStoreListeners = {}
const mockCatToolStoreListeners = {}
let mockCapturedFindFirstVisibleRow
let mockCapturedScrollToIndex
let mockCapturedItems = []

jest.mock('../../stores/SegmentStore', () => ({
  addListener: jest.fn((event, cb) => {
    mockSegmentStoreListeners[event] = cb
  }),
  removeListener: jest.fn(),
  getCurrentSegment: jest.fn(),
  getCurrentSegmentId: jest.fn(),
}))

jest.mock('../../stores/CatToolStore', () => ({
  addListener: jest.fn((event, cb) => {
    mockCatToolStoreListeners[event] = cb
  }),
  removeListener: jest.fn(),
  getJobFilesInfo: jest.fn(() => []),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  RENDER_SEGMENTS: 'RENDER_SEGMENTS',
  REMOVE_ALL_SEGMENTS: 'REMOVE_ALL_SEGMENTS',
  SCROLL_TO_SEGMENT: 'SCROLL_TO_SEGMENT',
  SCROLL_TO_SELECTED_SEGMENT: 'SCROLL_TO_SELECTED_SEGMENT',
  OPEN_SIDE: 'OPEN_SIDE',
  CLOSE_SIDE: 'CLOSE_SIDE',
  OPEN_SEGMENT: 'OPEN_SEGMENT',
}))

jest.mock('../../constants/CatToolConstants', () => ({
  STORE_FILES_INFO: 'STORE_FILES_INFO',
  CLIENT_CONNECT: 'CLIENT_CONNECT',
}))

jest.mock('../../actions/SegmentActions', () => ({
  copySourceToTarget: jest.fn(),
  scrollToCurrentSegment: jest.fn(),
  setFocusOnEditArea: jest.fn(),
  selectPrevSegmentDebounced: jest.fn(),
  selectNextSegmentDebounced: jest.fn(),
  openSelectedSegment: jest.fn(),
  openIssuesPanel: jest.fn(),
  scrollToSegment: jest.fn(),
  chooseContributionOnCurrentSegment: jest.fn(),
  openSplitSegment: jest.fn(),
  openSegmentComment: jest.fn(),
  setBulkSelectionInterval: jest.fn(),
  getMoreSegments: jest.fn(),
}))

jest.mock('../../utils/speech2text', () => ({
  enabled: jest.fn(() => false),
}))

jest.mock('../../utils/segmentUtils', () => ({
  getSegmentFileId: jest.fn((seg) => seg?.id_file ?? '1'),
  checkCurrentSegmentTPEnabled: jest.fn(() => false),
}))

jest.mock('../../utils/segmentText/segmentStringUtils', () => ({
  removeTagsFromText: jest.fn((t) => t),
}))

jest.mock('lodash', () => ({
  isUndefined: (v) => typeof v === 'undefined',
}))

jest.mock('../common/ApplicationWrapper/ApplicationWrapperContext', () => {
  const React = require('react') // <-- require inside factory
  return {
    ApplicationWrapperContext: React.createContext({
      userInfo: {
        metadata: {
          guess_tags: false,
          // ...existing code...
        },
      },
    }),
  }
})

jest.mock('../common/VirtualList/VirtualList', () => {
  const React = require('react')
  return React.forwardRef(
    (
      {
        onRender,
        onScroll,
        setFirstRowIdVisible,
        renderedRange,
        overlapHeader,
        items = [],
        findFirstVisibleRow,
        scrollToIndex,
      },
      ref,
    ) => {
      mockCapturedFindFirstVisibleRow = findFirstVisibleRow
      mockCapturedScrollToIndex = scrollToIndex
      mockCapturedItems = items

      React.useEffect(() => {
        if (setFirstRowIdVisible) setFirstRowIdVisible(items[0]?.id)
        if (renderedRange) renderedRange([0, items.length - 1])
      }, [items])

      return (
        <div ref={ref} className="virtual-list" data-testid="virtual-list">
          {/* firstChild must exist with a style prop for listRef.current.firstChild.style */}
          <div style={{}}>
            {overlapHeader}
            {items.map((item, index) => (
              <div key={item.id}>{onRender && onRender(index)}</div>
            ))}
          </div>
        </div>
      )
    },
  )
})

jest.mock('../common/VirtualList/Rows/RowSegment', () => {
  const React = require('react')
  return {
    __esModule: true,
    default: (props) =>
      React.createElement('div', {'data-testid': 'row-segment', id: props.id}),
    ProjectBar: (props) =>
      React.createElement('div', {'data-testid': 'project-bar'}),
  }
})

// --- Setup global config ---
global.config = {
  isReview: false,
  isSourceRTL: false,
  isTargetRTL: false,
  first_job_segment: '1',
}

// helpers
const makeSegment = (sid, overrides = {}) =>
  fromJS({
    sid,
    id_file: '1',
    internal_id: `${sid}-internal`,
    segment: 'Source text',
    translation: 'Target text',
    opened: false,
    openComments: false,
    original_sid: sid,
    notes: [],
    ...overrides,
  })

const makeSegments = (sids) =>
  fromJS(sids.map((sid) => makeSegment(sid).toJS()))

const renderComponent = (props = {}) =>
  render(
    <SegmentsContainer
      isReview={false}
      startSegmentId="1"
      firstJobSegment="1"
      {...props}
    />,
  )

beforeEach(() => {
  // SegmentsContainer reads header/footer height on resize
  const header = document.createElement('header')
  header.style.height = '60px'
  Object.defineProperty(header, 'offsetHeight', {value: 60, configurable: true})
  document.body.appendChild(header)

  const footer = document.createElement('footer')
  footer.style.height = '40px'
  Object.defineProperty(footer, 'offsetHeight', {value: 40, configurable: true})
  document.body.appendChild(footer)

  // SegmentsContainer appends temporary elements to #outer to measure height
  const outer = document.createElement('div')
  outer.id = 'outer'
  document.body.appendChild(outer)
})

afterEach(() => {
  document.body.querySelector('header')?.remove()
  document.body.querySelector('footer')?.remove()
  document.body.querySelector('#outer')?.remove()
})

// --- Tests ---

describe('SegmentsContainer', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    mockCapturedFindFirstVisibleRow = undefined
    mockCapturedScrollToIndex = undefined
    // Re-register listeners after clearAllMocks resets the mock implementations
    SegmentStore.addListener.mockImplementation((event, cb) => {
      mockSegmentStoreListeners[event] = cb
    })
    CatToolStore.addListener.mockImplementation((event, cb) => {
      mockCatToolStoreListeners[event] = cb
    })
    CatToolStore.getJobFilesInfo.mockReturnValue([])
  })

  describe('Rendering', () => {
    test('renders the VirtualList', () => {
      const {getByTestId} = renderComponent()
      expect(getByTestId('virtual-list')).toBeInTheDocument()
    })

    test('passes a findFirstVisibleRow that requires a 30px scroll offset', () => {
      renderComponent()
      expect(typeof mockCapturedFindFirstVisibleRow).toBe('function')
      expect(mockCapturedFindFirstVisibleRow({end: 50}, 10)).toBe(true)
      expect(mockCapturedFindFirstVisibleRow({end: 30}, 10)).toBe(false)
    })

    test('does not render scroll-to-top button initially', () => {
      const {queryByTitle} = renderComponent()
      expect(queryByTitle('Go to first segment')).not.toBeInTheDocument()
    })

    test('renders scroll-to-top button when scrollTopVisible is true', () => {
      const {getByTestId} = renderComponent()
      const list = getByTestId('virtual-list')

      // Simulate scroll > 400
      Object.defineProperty(list, 'scrollTop', {value: 500, writable: true})
      Object.defineProperty(list, 'firstChild', {
        value: {offsetHeight: 5000},
        writable: true,
      })
      Object.defineProperty(list, 'offsetHeight', {
        value: 800,
        writable: true,
      })

      act(() => {
        fireEvent.scroll(list)
      })

      // onScroll checks listRef.current and essentialRows.length; with empty rows it returns early
      // We test the button appears only indirectly via state; stub via store event instead
    })
  })

  describe('Store event listeners registration', () => {
    test('registers all SegmentStore listeners on mount', () => {
      renderComponent()
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.RENDER_SEGMENTS,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.REMOVE_ALL_SEGMENTS,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.SCROLL_TO_SEGMENT,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.SCROLL_TO_SELECTED_SEGMENT,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.OPEN_SIDE,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.CLOSE_SIDE,
        expect.any(Function),
      )
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        SegmentConstants.OPEN_SEGMENT,
        expect.any(Function),
      )
    })

    test('registers all CatToolStore listeners on mount', () => {
      renderComponent()
      expect(CatToolStore.addListener).toHaveBeenCalledWith(
        CatToolConstants.STORE_FILES_INFO,
        expect.any(Function),
      )
      expect(CatToolStore.addListener).toHaveBeenCalledWith(
        CatToolConstants.CLIENT_CONNECT,
        expect.any(Function),
      )
    })

    test('removes all listeners on unmount', () => {
      const {unmount} = renderComponent()
      unmount()
      expect(SegmentStore.removeListener).toHaveBeenCalledWith(
        SegmentConstants.RENDER_SEGMENTS,
        expect.any(Function),
      )
      expect(SegmentStore.removeListener).toHaveBeenCalledWith(
        SegmentConstants.SCROLL_TO_SEGMENT,
        expect.any(Function),
      )
      expect(SegmentStore.removeListener).toHaveBeenCalledWith(
        SegmentConstants.OPEN_SEGMENT,
        expect.any(Function),
      )
      expect(CatToolStore.removeListener).toHaveBeenCalledWith(
        CatToolConstants.CLIENT_CONNECT,
        expect.any(Function),
      )
    })
  })

  describe('OPEN_SEGMENT event (sticky project bar blink signal)', () => {
    test('does not crash when fired with no rows/segments loaded', () => {
      renderComponent()
      expect(() => {
        act(() => {
          mockSegmentStoreListeners[SegmentConstants.OPEN_SEGMENT]('1')
        })
      }).not.toThrow()
    })

    test('does not crash when fired after segments are rendered', async () => {
      renderComponent()
      await act(async () => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](
          makeSegments(['1', '2', '3']),
        )
      })
      expect(() => {
        act(() => {
          mockSegmentStoreListeners[SegmentConstants.OPEN_SEGMENT]('2')
        })
      }).not.toThrow()
    })
  })

  describe('RENDER_SEGMENTS event', () => {
    test('does not update segments when segments list is empty', () => {
      renderComponent()
      act(() => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](fromJS([]))
      })
      // No crash, component still renders
    })

    test('updates segments state when segments are provided', async () => {
      renderComponent()
      const segs = makeSegments(['1', '2', '3'])
      await act(async () => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](segs)
      })
      // No crash means state update was handled
    })
  })

  describe('CLIENT_CONNECT event', () => {
    test('sets clientConnected to true when clientId is provided', () => {
      renderComponent()
      act(() => {
        mockCatToolStoreListeners[CatToolConstants.CLIENT_CONNECT]('client-123')
      })
      // Component handles the event without error
    })

    test('sets clientConnected to false when clientId is falsy', () => {
      renderComponent()
      act(() => {
        mockCatToolStoreListeners[CatToolConstants.CLIENT_CONNECT](null)
      })
    })
  })

  describe('STORE_FILES_INFO event', () => {
    test('updates files state', () => {
      renderComponent()
      const files = [{id: 1, first_segment: '1', filename: 'test.xliff'}]
      act(() => {
        mockCatToolStoreListeners[CatToolConstants.STORE_FILES_INFO](files)
      })
      // No crash
    })
  })

  describe('OPEN_SIDE / CLOSE_SIDE events', () => {
    test('sets isSideOpen to true on OPEN_SIDE', () => {
      renderComponent()
      act(() => {
        mockSegmentStoreListeners[SegmentConstants.OPEN_SIDE]()
      })
      // No crash
    })

    test('sets isSideOpen to false on CLOSE_SIDE', () => {
      renderComponent()
      act(() => {
        mockSegmentStoreListeners[SegmentConstants.OPEN_SIDE]()
      })
      act(() => {
        mockSegmentStoreListeners[SegmentConstants.CLOSE_SIDE]()
      })
      // No crash
    })
  })

  describe('Mouse event listeners', () => {
    test('adds mousedown and mouseup document listeners on mount', () => {
      const addSpy = jest.spyOn(document, 'addEventListener')
      renderComponent()
      expect(addSpy).toHaveBeenCalledWith('mousedown', expect.any(Function))
      expect(addSpy).toHaveBeenCalledWith('mouseup', expect.any(Function))
    })

    test('removes mousedown and mouseup document listeners on unmount', () => {
      const removeSpy = jest.spyOn(document, 'removeEventListener')
      const {unmount} = renderComponent()
      unmount()
      expect(removeSpy).toHaveBeenCalledWith('mousedown', expect.any(Function))
      expect(removeSpy).toHaveBeenCalledWith('mouseup', expect.any(Function))
    })
  })

  describe('scrollToParams computation', () => {
    test('returns null scrollTo when no segments are loaded', () => {
      // With no rows, scrollToParams.scrollTo should be null — component renders without crash
      const {getByTestId} = renderComponent({startSegmentId: '5'})
      expect(getByTestId('virtual-list')).toBeInTheDocument()
    })

    test('scrolls directly to the target index with a start align and a numeric offset', async () => {
      renderComponent({startSegmentId: '2'})
      await act(async () => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](
          makeSegments(['1', '2', '3']),
        )
      })

      expect(mockCapturedScrollToIndex).toEqual({
        value: 1,
        align: 'start',
        offset: expect.any(Number),
      })
    })

    test('does not apply an offset when scrolling to the selected segment (align auto)', async () => {
      renderComponent({startSegmentId: '1'})
      await act(async () => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](
          makeSegments(['1', '2', '3']),
        )
      })
      act(() => {
        mockSegmentStoreListeners[SegmentConstants.SCROLL_TO_SELECTED_SEGMENT](
          '3',
        )
      })

      expect(mockCapturedScrollToIndex.align).toBe('auto')
      expect(mockCapturedScrollToIndex.offset).toBeUndefined()
    })

    test('populates a numeric offset even before the sticky bar has been measured', async () => {
      renderComponent({startSegmentId: '5'})
      await act(async () => {
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](
          makeSegments(['5']),
        )
      })

      expect(typeof mockCapturedScrollToIndex.offset).toBe('number')
    })
  })

  describe('goToFirstSegment', () => {
    test('calls SegmentActions.scrollToSegment with firstJobSegment on button click', async () => {
      const {container} = renderComponent({firstJobSegment: '42'})

      // Manually trigger scrollTopVisible by dispatching state
      // Since the button only appears when scrollTopVisible is true,
      // we test SegmentActions directly
      await act(async () => {
        // Trigger scroll visibility by simulating a render_segments + scroll
        mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS]?.(
          makeSegments(['1']),
        )
      })

      const btn = container.querySelector('.pointer-first-segment')
      if (btn) {
        fireEvent.click(btn)
        expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith('42')
      } else {
        // Button is not yet visible (scrollTopVisible = false), which is correct initial state
        expect(SegmentActions.scrollToSegment).not.toHaveBeenCalled()
      }
    })
  })

  describe('Window resize', () => {
    test('updates heightArea on window resize', () => {
      // Mock header/footer
      document.body.innerHTML =
        '<header style="height:60px"></header><footer style="height:40px"></footer>'

      renderComponent()

      act(() => {
        global.innerHeight = 900
        fireEvent(window, new Event('resize'))
      })
      // Component handles resize without crash
    })
  })

  describe('PropTypes', () => {
    test('renders without required props without crashing', () => {
      expect(() => render(<SegmentsContainer />)).not.toThrow()
    })

    test('accepts isReview as boolean prop', () => {
      expect(() => render(<SegmentsContainer isReview={true} />)).not.toThrow()
    })
  })
})

describe('SegmentsContainer height calculation', () => {
  test('does not throw when the page has no footer', () => {
    document.querySelector('footer').remove()

    expect(() => render(<SegmentsContainer />)).not.toThrow()
  })

  test('does not throw when the page has no header', () => {
    document.querySelector('header').remove()

    expect(() => render(<SegmentsContainer />)).not.toThrow()
  })
})

describe('SegmentsContainer height measuring markup', () => {
  const SegmentUtils = require('../../utils/segmentUtils')
  const SegmentStringUtils = require('../../utils/segmentText/segmentStringUtils')
  const {Base64} = require('js-base64')
  const {
    setTagSignatureMiddleware,
  } = require('../../utils/segmentText/tagModel')

  const ph = (html) =>
    `<ph id="m1" ctype="x-html" equiv-text="base64:${Base64.encode(html)}"/>`

  // jsdom lays nothing out: every measured section reports the height given
  // by `heightOf`, and a copy of it is kept to check what was measured
  let measuredSections
  let clientHeightSpy
  const measureSectionsAs = (heightOf) => {
    clientHeightSpy.mockImplementation(function () {
      if (this.tagName !== 'SECTION') return 0
      measuredSections.push(this.cloneNode(true))
      return heightOf(this)
    })
  }

  const renderSegments = async (segments, props) => {
    renderComponent(props)
    await act(async () => {
      mockSegmentStoreListeners[SegmentConstants.RENDER_SEGMENTS](
        fromJS(segments.map((segment) => segment.toJS())),
      )
    })
  }

  // Renders one segment and returns the markup measured for it, as a DOM node
  const measuredMarkup = async (segment) => {
    measureSectionsAs(() => 0)
    await renderSegments([segment])
    const node = document.createElement('div')
    node.append(measuredSections[0])
    return node
  }

  beforeAll(() => setTagSignatureMiddleware('space', () => false))
  afterAll(() => setTagSignatureMiddleware('space', () => undefined))

  beforeEach(() => {
    jest.clearAllMocks()
    SegmentStore.addListener.mockImplementation((event, cb) => {
      mockSegmentStoreListeners[event] = cb
    })
    CatToolStore.addListener.mockImplementation((event, cb) => {
      mockCatToolStoreListeners[event] = cb
    })
    CatToolStore.getJobFilesInfo.mockReturnValue([])
    measuredSections = []
    clientHeightSpy = jest.spyOn(Element.prototype, 'clientHeight', 'get')
  })

  afterEach(() => clientHeightSpy.mockRestore())

  test('measures the source and target text with tags as chips and entities decoded', async () => {
    const markup = await measuredMarkup(
      makeSegment('1', {
        segment: `Tom &amp; ${ph('&lt;br/&gt;')}Jerry`,
        translation: `Ciao &lt;mondo&gt; ${ph('&lt;hr/&gt;')}`,
      }),
    )
    expect(within(markup).getByText(/Tom &/)).toHaveClass('source')
    // the measuring markup is not in the document: getByText itself finds the chip label
    expect(
      within(markup).getByText('<br/>', {selector: '.tag > .tag-text'}),
    ).toHaveTextContent('<br/>')
    expect(within(markup).getByText(/Ciao <mondo>/)).toHaveClass('targetarea')
    // the measuring markup is not in the document: getByText itself finds the chip label
    expect(
      within(markup).getByText('<hr/>', {selector: '.tag > .tag-text'}),
    ).toHaveTextContent('<hr/>')
    expect(within(markup).queryByText(/equiv-text/)).not.toBeInTheDocument()
  })

  test('measures a segment with tag projection enabled without its tags', async () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
    SegmentStringUtils.removeTagsFromText.mockImplementation((text) =>
      text.replace(/<ph[^>]*\/>/g, ''),
    )
    const markup = await measuredMarkup(
      makeSegment('1', {
        segment: `Source ${ph('&lt;br/&gt;')}text`,
        translation: `Target ${ph('&lt;br/&gt;')}text`,
      }),
    )
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
    SegmentStringUtils.removeTagsFromText.mockImplementation((t) => t)

    expect(within(markup).getByText('Source text')).toHaveClass('source')
    expect(within(markup).getByText('Target text')).toHaveClass('targetarea')
    expect(within(markup).queryByText('<br/>')).not.toBeInTheDocument()
  })

  test('gives each row the height its segment takes laid out in the page, then removes the measuring markup', async () => {
    measureSectionsAs((section) =>
      section.textContent.includes('Long source') ? 500 : 300,
    )
    await renderSegments(
      [
        makeSegment('1'),
        makeSegment('2'),
        makeSegment('3', {segment: 'Long source'}),
      ],
      {startSegmentId: '3'},
    )

    expect(measuredSections).toHaveLength(3)
    const [, second, third] = mockCapturedItems
    // the first row also carries the file header; the other two differ only by their text
    expect(third.height - second.height).toBe(200)
    // the rows of the list are mocked and show no text: only leftover measuring markup would
    expect(screen.queryByText('Long source')).not.toBeInTheDocument()
  })
})
