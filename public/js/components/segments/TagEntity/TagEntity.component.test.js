import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import TagEntity from './TagEntity.component'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolConstants from '../../../constants/CatToolConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import SearchUtils from '../../header/cattol/search/searchUtils'

jest.mock('../../../stores/SegmentStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../../stores/CatToolStore', () => ({
  isPhTagsCompressed: jest.fn(() => false),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../../actions/SegmentActions', () => ({
  setIsCurrentSearchOccurrenceTag: jest.fn(),
  highlightTags: jest.fn(),
}))

jest.mock('../../header/cattol/search/searchUtils', () => ({
  __esModule: true,
  default: {
    getSearchRegExp: jest.fn((text) => new RegExp(`(${text})`, 'gi')),
  },
}))

jest.mock('../../common/Tooltip', () => ({
  __esModule: true,
  default: ({children, content}) => (
    <div data-testid="tooltip">
      <div data-testid="tooltip-content">{content}</div>
      {children}
    </div>
  ),
}))

const makeContentState = (entityData) => ({
  getEntity: jest.fn(() => ({data: entityData})),
})

// Draft.js hands the entity an array of decorated leaves; the component reads
// `children[0].props.text` off it. A component (rather than a bare span with a
// `text` attribute) keeps that shape without emitting unknown-prop DOM warnings.
const Leaf = ({text}) => <span>{text}</span>

const baseProps = (overrides = {}) => ({
  entityKey: '1',
  offsetkey: '1-0-0',
  contentState: makeContentState({
    id: 'e1',
    placeholder: 'PH',
    index: 0,
    name: 'ph',
  }),
  getSearchParams: jest.fn(() => ({active: false})),
  getUpdatedSegmentInfo: jest.fn(() => ({
    segmentOpened: true,
    tagMismatch: null,
    missingTagsInTarget: [],
    openSplit: false,
  })),
  isTarget: false,
  isRTL: false,
  sid: 1,
  start: 0,
  end: 1,
  onClick: jest.fn(),
  children: [<Leaf key="0" text="hello" />],
  ...overrides,
})

const renderTag = (overrides) => render(<TagEntity {...baseProps(overrides)} />)

// The component registers its store listeners on mount; picking them back out of
// the mock is how these tests drive the behaviour those listeners own. This works
// the same whether the listener is a bound class method or a hook callback.
const listenerFor = (store, constant) => {
  const call = store.addListener.mock.calls.find(([c]) => c === constant)
  if (!call) throw new Error(`no listener registered for ${String(constant)}`)
  return call[1]
}

const tagEl = () => document.querySelector('.tag-container .tag')
const tagClass = () => tagEl().getAttribute('class')

// `updateTagStyle` and `updateTagWarningStyle` are registered debounced (500ms).
const flushDebounce = () => {
  act(() => {
    jest.advanceTimersByTime(500)
  })
}

beforeEach(() => {
  CatToolStore.isPhTagsCompressed.mockReturnValue(false)
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('TagEntity tag styling', () => {
  test('uses the LTR style by default', () => {
    renderTag()
    expect(tagClass()).toContain('tag-selfclosed tag-ph')
  })

  test('uses the RTL style when isRTL and a styleRTL is defined', () => {
    renderTag({
      isRTL: true,
      contentState: makeContentState({id: 'g1', name: 'g'}),
    })
    expect(tagClass()).toContain('tag-close')
  })

  test('adds tag-inactive when the segment is not opened', () => {
    renderTag({
      getUpdatedSegmentInfo: jest.fn(() => ({segmentOpened: false})),
    })
    expect(tagClass()).toContain('tag-inactive')
  })
})

describe('TagEntity children content', () => {
  test('shows the index counter and children for an open ph tag', () => {
    renderTag({
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 2,
        name: 'ph',
      }),
    })
    expect(document.querySelector('.index-counter').textContent).toBe('3')
    expect(tagEl().textContent).toContain('hello')
  })

  test('hides children content when ph tags are compressed', () => {
    CatToolStore.isPhTagsCompressed.mockReturnValue(true)
    renderTag()
    expect(document.querySelector('.index-counter')).toBeTruthy()
    expect(tagEl().textContent).toBe('1')
  })

  test('a closing pc tag never shows its content, even uncompressed', () => {
    renderTag({
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        pcRole: 'close',
      }),
    })
    expect(tagEl().textContent).toBe('1')
  })

  test('renders raw children without an index counter for non-ph tags', () => {
    renderTag({
      contentState: makeContentState({id: 'g1', index: -1, name: 'g'}),
    })
    expect(document.querySelector('.index-counter')).toBeNull()
    expect(tagEl().textContent).toContain('hello')
  })

  test('adds the pc role class for a pc tag', () => {
    renderTag({
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        pcRole: 'open',
      }),
    })
    expect(tagClass()).toContain('tag-pc-open')
  })
})

describe('TagEntity search highlighting', () => {
  const activeSearch = (overrides = {}) => ({
    active: true,
    currentActive: true,
    textToReplace: 'ell',
    params: {ingnoreCase: true, exactMatch: false},
    occurrences: [{searchProgressiveIndex: 1, matchPosition: 0}],
    currentInSearchIndex: 1,
    ...overrides,
  })

  test('does not highlight anything when search is not active', () => {
    renderTag()
    expect(tagEl().querySelector('span[style]')).toBeNull()
    expect(SearchUtils.getSearchRegExp).not.toHaveBeenCalled()
  })

  test('wraps matches and flags the current occurrence via SegmentActions', () => {
    renderTag({
      start: 0,
      end: 5,
      getSearchParams: jest.fn(() => activeSearch()),
    })

    expect(SegmentActions.setIsCurrentSearchOccurrenceTag).toHaveBeenCalledWith(
      true,
    )
    expect(SearchUtils.getSearchRegExp).toHaveBeenCalledWith('ell', true, false)
    expect(tagEl().querySelector('span[style]')).toBeTruthy()
  })

  test('does not flag the current occurrence when the match is outside the tag range', () => {
    renderTag({
      start: 10,
      end: 15,
      getSearchParams: jest.fn(() => activeSearch()),
    })

    expect(
      SegmentActions.setIsCurrentSearchOccurrenceTag,
    ).not.toHaveBeenCalled()
  })

  test('keeps a hidden copy of the tag content while search is active', () => {
    renderTag({
      start: 0,
      end: 5,
      getSearchParams: jest.fn(() => activeSearch()),
    })

    const hidden = tagEl().querySelector('span[style*="display: none"]')
    expect(hidden).toBeTruthy()
    expect(hidden.textContent).toContain('hello')
  })
})

describe('TagEntity search param listeners', () => {
  test('ignores an ADD_SEARCH_RESULTS event for a different segment', () => {
    const getSearchParams = jest
      .fn()
      .mockReturnValueOnce({active: false})
      .mockReturnValue({active: true, isTarget: true, textToReplace: 'ell'})
    renderTag({sid: 1, isTarget: true, getSearchParams})

    act(() => {
      listenerFor(SegmentStore, SegmentConstants.ADD_SEARCH_RESULTS)(2)
    })

    expect(tagEl().querySelector('span[style]')).toBeNull()
  })

  test('applies an ADD_SEARCH_RESULTS event targeting this segment and side', () => {
    const getSearchParams = jest.fn().mockReturnValue({
      active: true,
      isTarget: true,
      currentActive: false,
      textToReplace: 'ell',
      params: {ingnoreCase: true, exactMatch: false},
      occurrences: [],
      currentInSearchIndex: 1,
    })
    renderTag({sid: 1, isTarget: true, getSearchParams})

    act(() => {
      listenerFor(SegmentStore, SegmentConstants.ADD_SEARCH_RESULTS)(1)
    })

    expect(tagEl().querySelector('span[style]')).toBeTruthy()
  })

  test('ignores an ADD_CURRENT_SEARCH event when search is not active here', () => {
    const getSearchParams = jest.fn(() => ({active: false}))
    renderTag({sid: 1, getSearchParams})

    act(() => {
      listenerFor(SegmentStore, SegmentConstants.ADD_CURRENT_SEARCH)(1, 3)
    })

    expect(tagEl().querySelector('span[style]')).toBeNull()
  })

  test('applies an ADD_CURRENT_SEARCH event when search is active here', () => {
    const getSearchParams = jest.fn(() => ({
      active: true,
      currentActive: true,
      textToReplace: 'ell',
      params: {ingnoreCase: true, exactMatch: false},
      occurrences: [{searchProgressiveIndex: 5, matchPosition: 0}],
      currentInSearchIndex: 1,
    }))
    renderTag({sid: 1, start: 0, end: 5, getSearchParams})
    SegmentActions.setIsCurrentSearchOccurrenceTag.mockClear()

    act(() => {
      listenerFor(SegmentStore, SegmentConstants.ADD_CURRENT_SEARCH)(1, 5)
    })

    // currentInSearchIndex is now 5, which matches the occurrence's
    // searchProgressiveIndex, so this tag reports itself as the current one.
    expect(SegmentActions.setIsCurrentSearchOccurrenceTag).toHaveBeenCalledWith(
      true,
    )
  })

  test('re-reads the search params on REMOVE_SEARCH_RESULTS while active', () => {
    const getSearchParams = jest
      .fn()
      .mockReturnValueOnce({
        active: true,
        currentActive: false,
        textToReplace: 'ell',
        params: {ingnoreCase: true, exactMatch: false},
        occurrences: [],
        currentInSearchIndex: 1,
      })
      .mockReturnValue({active: false})
    renderTag({getSearchParams})
    expect(tagEl().querySelector('span[style]')).toBeTruthy()

    act(() => {
      listenerFor(SegmentStore, SegmentConstants.REMOVE_SEARCH_RESULTS)()
    })

    expect(tagEl().querySelector('span[style]')).toBeNull()
  })
})

describe('TagEntity ph compression toggle', () => {
  test('re-reads the compressed flag when the store toggles it', () => {
    renderTag()
    expect(tagEl().textContent).toContain('hello')

    CatToolStore.isPhTagsCompressed.mockReturnValue(true)
    act(() => {
      listenerFor(CatToolStore, CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED)()
    })

    expect(tagClass()).toContain('tag-compressed')
    expect(tagEl().textContent).toBe('1')
  })
})

describe('TagEntity highlightTags', () => {
  const highlight = (...args) =>
    act(() => {
      listenerFor(SegmentStore, SegmentConstants.HIGHLIGHT_TAGS)(...args)
    })

  test('turns on the clicked style when this entity is the trigger', () => {
    renderTag()
    highlight('e1', 'PH', '1')
    expect(tagClass()).toContain('tag-clicked')
  })

  test('turns on the clicked style for a matching sibling entity', () => {
    renderTag()
    highlight('e1', 'PH', 'different-key')
    expect(tagClass()).toContain('tag-clicked')
  })

  test('turns off the clicked style when a different tag id is targeted', () => {
    renderTag()
    highlight('e1', 'PH', '1')
    expect(tagClass()).toContain('tag-clicked')

    highlight('other-id', 'ph', 'some-key')
    expect(tagClass()).not.toContain('tag-clicked')
  })

  test('does nothing for an unrelated tag/entity combination', () => {
    renderTag()
    const before = tagClass()
    highlight('other-id', 'OTHER', 'different-key')
    expect(tagClass()).toBe(before)
  })
})

describe('TagEntity edit-area style refresh', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('skips a target-side update on a source tag', () => {
    const getUpdatedSegmentInfo = jest.fn(() => ({
      segmentOpened: false,
      tagMismatch: null,
      missingTagsInTarget: [],
      openSplit: false,
    }))
    renderTag({isTarget: false, getUpdatedSegmentInfo})
    expect(tagClass()).toContain('tag-inactive')

    // The segment opens, but the event is flagged as a target-side change, so a
    // source tag must ignore it and keep its stale style.
    getUpdatedSegmentInfo.mockReturnValue({
      segmentOpened: true,
      tagMismatch: null,
      missingTagsInTarget: [],
      openSplit: false,
    })
    act(() => {
      listenerFor(SegmentStore, EditAreaConstants.EDIT_AREA_CHANGED)(1, true)
    })
    flushDebounce()

    expect(tagClass()).toContain('tag-inactive')
  })

  test('recomputes the style when it differs', () => {
    const getUpdatedSegmentInfo = jest.fn(() => ({
      segmentOpened: false,
      tagMismatch: null,
      missingTagsInTarget: [],
      openSplit: false,
    }))
    renderTag({isTarget: true, getUpdatedSegmentInfo})
    expect(tagClass()).toContain('tag-inactive')

    getUpdatedSegmentInfo.mockReturnValue({
      segmentOpened: true,
      tagMismatch: null,
      missingTagsInTarget: [],
      openSplit: false,
    })
    act(() => {
      listenerFor(SegmentStore, EditAreaConstants.EDIT_AREA_CHANGED)(1, false)
    })
    flushDebounce()

    expect(tagClass()).not.toContain('tag-inactive')
  })
})

describe('TagEntity warning styling', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  const fireWarnings = () => {
    act(() => {
      listenerFor(SegmentStore, SegmentConstants.SET_SEGMENT_WARNINGS)()
    })
    flushDebounce()
  }

  test('flags a target tag whose encoded text is in the target mismatch list', () => {
    renderTag({
      isTarget: true,
      getUpdatedSegmentInfo: jest.fn(() => ({
        segmentOpened: true,
        tagMismatch: {target: ['x'], source: [], order: []},
      })),
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        encodedText: 'x',
      }),
    })

    fireWarnings()

    expect(tagClass()).toContain('tag-mismatch-error')
  })

  test('flags an out-of-order target tag as a warning', () => {
    renderTag({
      isTarget: true,
      getUpdatedSegmentInfo: jest.fn(() => ({
        segmentOpened: true,
        tagMismatch: {target: [], source: [], order: ['x']},
      })),
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        encodedText: 'x',
      }),
    })

    fireWarnings()

    expect(tagClass()).toContain('tag-mismatch-warning')
  })

  test('leaves a tag unflagged when nothing mismatches', () => {
    renderTag({
      isTarget: true,
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        encodedText: 'x',
      }),
    })

    fireWarnings()

    expect(tagClass()).not.toContain('tag-mismatch')
  })
})

describe('TagEntity focus handling', () => {
  const focus = (payload) =>
    act(() => {
      listenerFor(SegmentStore, SegmentConstants.FOCUS_TAGS)(payload)
    })

  test('clears the focused style when no tags are selected', () => {
    jest.useFakeTimers()
    renderTag()

    focus({tagsSelected: [{entityKey: '1'}]})
    act(() => {
      jest.advanceTimersByTime(100)
    })
    expect(tagClass()).toContain('tag-focused')

    focus({tagsSelected: []})
    expect(tagClass()).not.toContain('tag-focused')
    jest.useRealTimers()
  })

  test('defers the focus update by 100ms', () => {
    jest.useFakeTimers()
    renderTag()

    focus({tagsSelected: [{entityKey: '1'}]})
    expect(tagClass()).not.toContain('tag-focused')

    act(() => {
      jest.advanceTimersByTime(100)
    })
    expect(tagClass()).toContain('tag-focused')
    jest.useRealTimers()
  })

  test('applies focus synchronously after a click sets skipTmOut', () => {
    jest.useFakeTimers()
    renderTag()

    // Clicking the tag records skipTmOut, so the next focus event applies
    // without waiting for the 100ms timeout.
    fireEvent.click(tagEl())
    focus({tagsSelected: [{entityKey: '1'}]})

    expect(tagClass()).toContain('tag-focused')
    jest.useRealTimers()
  })

  test('clears a pending focus timeout before scheduling a new one', () => {
    jest.useFakeTimers()
    const clearSpy = jest.spyOn(global, 'clearTimeout')
    renderTag()

    focus({tagsSelected: [{entityKey: 'not-this-one'}]})
    focus({tagsSelected: [{entityKey: '1'}]})

    // The second event must clear the timer the first one scheduled, otherwise
    // the stale callback would land after it and overwrite the new focus state.
    expect(clearSpy).toHaveBeenCalled()

    act(() => {
      jest.advanceTimersByTime(100)
    })
    expect(tagClass()).toContain('tag-focused')

    clearSpy.mockRestore()
    jest.useRealTimers()
  })
})

describe('TagEntity click behaviour', () => {
  test('calls the onClick prop with the tag range and entity name', () => {
    const onClick = jest.fn()
    renderTag({
      start: 3,
      end: 8,
      onClick,
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
      }),
    })

    fireEvent.click(tagEl())

    expect(onClick).toHaveBeenCalledWith(3, 8, 'ph')
  })

  test('schedules highlightTags when no split is open', () => {
    jest.useFakeTimers()
    renderTag()

    fireEvent.click(tagEl())
    act(() => {
      jest.runOnlyPendingTimers()
    })

    expect(SegmentActions.highlightTags).toHaveBeenCalledWith('e1', 'PH', '1')
    jest.useRealTimers()
  })

  test('does not schedule highlightTags when a split is open', () => {
    jest.useFakeTimers()
    renderTag({
      getUpdatedSegmentInfo: jest.fn(() => ({
        segmentOpened: true,
        tagMismatch: null,
        missingTagsInTarget: [],
        openSplit: true,
      })),
    })

    fireEvent.click(tagEl())
    act(() => {
      jest.runOnlyPendingTimers()
    })

    expect(SegmentActions.highlightTags).not.toHaveBeenCalled()
    jest.useRealTimers()
  })
})

describe('TagEntity tooltip', () => {
  // shouldTooltipOnHover is derived from a real overflow measurement on the
  // rendered leaf span, which jsdom reports as 0/0. These getters make the
  // measured span overflow so the branch is reachable.
  const withOverflowingLeaf = (fn) => {
    const offset = Object.getOwnPropertyDescriptor(
      HTMLElement.prototype,
      'offsetWidth',
    )
    const scroll = Object.getOwnPropertyDescriptor(
      HTMLElement.prototype,
      'scrollWidth',
    )
    Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
      configurable: true,
      get() {
        return this.dataset && this.dataset.text === 'true' ? 10 : 0
      },
    })
    Object.defineProperty(HTMLElement.prototype, 'scrollWidth', {
      configurable: true,
      get() {
        return this.dataset && this.dataset.text === 'true' ? 50 : 0
      },
    })
    try {
      fn()
    } finally {
      if (offset)
        Object.defineProperty(HTMLElement.prototype, 'offsetWidth', offset)
      if (scroll)
        Object.defineProperty(HTMLElement.prototype, 'scrollWidth', scroll)
    }
  }

  const overflowingChildren = [
    <span key="0" data-text="true">
      a very long tag content
    </span>,
  ]

  test('shows the placeholder tooltip when the content overflows', () => {
    withOverflowingLeaf(() => {
      renderTag({children: overflowingChildren})
      expect(screen.getByTestId('tooltip-content').textContent).toBe('PH')
    })
  })

  test('shows no tooltip for a tag type that does not enable one', () => {
    withOverflowingLeaf(() => {
      renderTag({
        children: overflowingChildren,
        contentState: makeContentState({
          id: 'e2',
          placeholder: 'GSC',
          index: 0,
          name: 'gSc',
        }),
      })
      expect(screen.getByTestId('tooltip-content').textContent).toBe('')
    })
  })

  test('shows the tooltip for a compressed ph tag without needing overflow', () => {
    CatToolStore.isPhTagsCompressed.mockReturnValue(true)
    renderTag()
    expect(screen.getByTestId('tooltip-content').textContent).toBe('PH')
  })

  test('never shows a tooltip for a closing pc tag', () => {
    CatToolStore.isPhTagsCompressed.mockReturnValue(true)
    renderTag({
      contentState: makeContentState({
        id: 'e1',
        placeholder: 'PH',
        index: 0,
        name: 'ph',
        pcRole: 'close',
      }),
    })
    expect(screen.getByTestId('tooltip-content').textContent).toBe('')
  })

  describe('re-measuring the overflow after an update', () => {
    // No `data-text` span, so the measurement finds nothing to measure and the
    // tooltip stays off until an update re-measures.
    const nonOverflowingChildren = [<Leaf key="0" text="short" />]

    const renderThenUpdate = (nextProps) => {
      const initial = baseProps({children: nonOverflowingChildren})
      const {rerender} = render(<TagEntity {...initial} />)
      expect(screen.getByTestId('tooltip-content').textContent).toBe('')

      rerender(
        <TagEntity
          {...{...initial, children: overflowingChildren, ...nextProps}}
        />,
      )
      return screen.getByTestId('tooltip-content').textContent
    }

    test('re-measures when entityKey changes', () => {
      withOverflowingLeaf(() => {
        expect(renderThenUpdate({entityKey: '2'})).toBe('PH')
      })
    })

    test('does not re-measure when entityKey is unchanged', () => {
      withOverflowingLeaf(() => {
        expect(renderThenUpdate({offsetkey: '1-0-1'})).toBe('')
      })
    })
  })

  // Wave 4d hit "maximum update depth exceeded" when a parent's state update
  // cascaded into a freshly mounted TagEntity that set state on mount. A
  // segment renders one of these per tag, and Editarea re-renders them
  // wholesale, so the mount measurement must not commit unless it actually
  // changes something.
  describe('mount measurement does not cascade', () => {
    const commitPhases = (children) => {
      const phases = []
      render(
        <React.Profiler
          id="tag"
          onRender={(id, phase) => {
            phases.push(phase)
          }}
        >
          <TagEntity {...baseProps({children})} />
        </React.Profiler>,
      )
      return phases
    }

    test('commits once when the measurement matches the initial state', () => {
      expect(commitPhases([<Leaf key="0" text="short" />])).toEqual(['mount'])
    })

    test('converges after a single update when the measurement differs', () => {
      withOverflowingLeaf(() => {
        expect(commitPhases(overflowingChildren)).toEqual(['mount', 'update'])
      })
    })
  })
})

describe('TagEntity store subscriptions', () => {
  test('registers every listener on mount', () => {
    renderTag()

    const segmentEvents = SegmentStore.addListener.mock.calls.map(([c]) => c)
    expect(segmentEvents).toEqual(
      expect.arrayContaining([
        SegmentConstants.SET_SEGMENT_WARNINGS,
        SegmentConstants.HIGHLIGHT_TAGS,
        EditAreaConstants.EDIT_AREA_CHANGED,
        SegmentConstants.ADD_SEARCH_RESULTS,
        SegmentConstants.ADD_CURRENT_SEARCH,
        SegmentConstants.REMOVE_SEARCH_RESULTS,
        SegmentConstants.FOCUS_TAGS,
      ]),
    )
    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      expect.any(Function),
    )
  })

  test('removes exactly the handler references it registered on unmount', () => {
    const {unmount} = renderTag()

    const registered = [
      ...SegmentStore.addListener.mock.calls,
      ...CatToolStore.addListener.mock.calls,
    ]
    expect(registered).toHaveLength(8)

    unmount()

    const removed = [
      ...SegmentStore.removeListener.mock.calls,
      ...CatToolStore.removeListener.mock.calls,
    ]
    expect(removed).toHaveLength(8)

    // A leak here means add and remove were handed different function
    // identities for the same event — the bug class this migration keeps hitting.
    registered.forEach(([event, handler]) => {
      expect(removed).toEqual(expect.arrayContaining([[event, handler]]))
    })
  })
})
