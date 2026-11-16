import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import {EditorState} from 'draft-js'

import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

setTagSignatureMiddleware('space', () => false)

const mockCheckCurrentSegmentTPEnabled = jest.fn(() => false)
const mockGetRanges = jest.fn(() => [])
const mockUpdateOffset = jest.fn(() => [])
const mockGetFragmentFromSelection = jest.fn(() => null)
const mockGetSelectedTextWithoutEntities = jest.fn(() => [])

jest.mock('../../stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    addListener: jest.fn(),
    removeListener: jest.fn(),
    getSegmentByIdToJS: jest.fn(),
  },
}))

jest.mock('../../stores/CatToolStore', () => ({
  __esModule: true,
  default: {addListener: jest.fn(), removeListener: jest.fn()},
}))

jest.mock('../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    activateTab: jest.fn(),
    closeSplitSegment: jest.fn(),
    copyFragmentToClipboard: jest.fn(),
    focusTags: jest.fn(),
    helpAiAssistant: jest.fn(),
    highlightTags: jest.fn(),
    openConcordance: jest.fn(),
    openGlossaryFormPrefill: jest.fn(),
    splitSegment: jest.fn(),
    updateSource: jest.fn(),
  },
}))

jest.mock('../../utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    checkCurrentSegmentTPEnabled: (...args) =>
      mockCheckCurrentSegmentTPEnabled(...args),
  },
}))

jest.mock('../../utils/lxq.main', () => ({
  __esModule: true,
  default: {getRanges: (...args) => mockGetRanges(...args)},
}))

jest.mock('./utils/DraftMatecatUtils/updateOffsetBasedOnEditorState', () => ({
  __esModule: true,
  default: (...args) => mockUpdateOffset(...args),
}))

jest.mock(
  './utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection',
  () => ({
    __esModule: true,
    default: (...args) => mockGetFragmentFromSelection(...args),
  }),
)

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'standard',
    cattol: {
      events: {
        searchInConcordance: {keystrokes: {standard: 'ctrl+k'}},
      },
    },
  },
}))

const mockUseHotKeysComponent = jest.fn(() => null)
jest.mock('../../hooks/UseHotKeysComponent', () => ({
  UseHotKeysComponent: (...args) => mockUseHotKeysComponent(...args),
}))

// Holds the props object SegmentSource hands the tag decorator. The decorator is
// built once and kept in a ref, so this is the *same* object production keeps
// calling for the life of the segment — which is what makes calling it again
// later, after the segment has changed, a meaningful assertion.
let mockTagProps = null

// Stands in for the real TagEntity, reproducing the two interactions the
// component under test depends on: its click handler resolves the entity's name
// from the content state and calls back with the entity's own offsets
// (TagEntity.component.js `onClickBound`), and it calls getUpdatedSegmentInfo()
// on every render to decide its warning styling. Clicking a rendered tag
// therefore drives SegmentSource the same way a user clicking a tag does —
// which is what lets these tests reach selection-dependent behaviour without
// jsdom needing a working contentEditable.
jest.mock('./TagEntity/TagEntity.component', () => ({
  __esModule: true,
  default: ({
    children,
    start,
    end,
    entityKey,
    contentState,
    onClick,
    getUpdatedSegmentInfo,
    getSearchParams,
  }) => {
    const {
      data: {name: entityName},
    } = contentState.getEntity(entityKey)
    mockTagProps = {onClick, getUpdatedSegmentInfo, getSearchParams}
    return (
      <span
        data-testid="tag-entity"
        data-tag-name={entityName}
        onClick={() => onClick(start, end, entityName)}
      >
        {children}
      </span>
    )
  },
}))

jest.mock('./utils/DraftMatecatUtils/createICUDecorator', () => ({
  createIcuTokens: jest.fn(() => []),
  createICUDecorator: jest.fn(() => ({
    name: 'icu',
    strategy: () => {},
    component: () => null,
  })),
}))

jest.mock('./utils/DraftMatecatUtils', () => {
  const actual = jest.requireActual('./utils/DraftMatecatUtils').default
  return {
    __esModule: true,
    default: {
      ...actual,
      activateSearch: jest.fn(() => ({
        name: 'search',
        strategy: () => {},
        component: () => null,
      })),
      activateGlossary: jest.fn(() => ({
        name: 'glossary',
        strategy: () => {},
        component: () => null,
      })),
      activateQaCheckGlossary: jest.fn(() => ({
        name: 'qaCheckGlossary',
        strategy: () => {},
        component: () => null,
      })),
      activateLexiqa: jest.fn(() => ({
        name: 'lexiqa',
        strategy: () => {},
        component: () => null,
      })),
      getEntitiesInFragment: jest.fn(() => ({})),
      getSelectedTextWithoutEntities: (...args) =>
        mockGetSelectedTextWithoutEntities(...args),
    },
  }
})

import SegmentSource, {
  getSearchParams,
  isValidPhraseToAiAssistant,
  preventEdit,
  allowHTML,
  getUpdatedSegmentInfo,
} from './SegmentSource'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentConstants from '../../constants/SegmentConstants'

const TAGGED_SOURCE = 'Hello <g id="1">world</g>'

function makeSegment(overrides = {}) {
  return {
    sid: '10',
    original_sid: '10',
    segment: 'Hello world',
    translation: '',
    opened: true,
    openSplit: false,
    splitted: false,
    split_group: [],
    inSearch: false,
    currentInSearch: false,
    currentInSearchIndex: 0,
    searchParams: {},
    occurrencesInSearch: {occurrences: []},
    glossary: [],
    lexiqa: {},
    lxqDecodedSource: '',
    icu: false,
    warnings: {},
    tagMismatch: {},
    missingTagsInTarget: [],
    ...overrides,
  }
}

function renderSource(segment, contextExtra = {}) {
  const tree = (seg) => (
    <SegmentContext.Provider value={{segment: seg, ...contextExtra}}>
      <SegmentSource segment={seg} />
    </SegmentContext.Provider>
  )
  const utils = render(tree(segment))
  return {
    ...utils,
    update: (nextSegment, nextContextExtra) => {
      if (nextContextExtra) Object.assign(contextExtra, nextContextExtra)
      utils.rerender(tree(nextSegment))
    },
  }
}

// Flushes the `setTimeout(...)` scheduled by the mount effect / refreshTagMap.
async function flushTimers() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 120))
  })
}

// Flushes the zero-delay `setTimeout(...)` the event handlers defer their work
// to, without wrapping the `fireEvent` call itself in `act`.
async function flushDeferred(delay = 0) {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, delay))
  })
}

let originalGetSelection

// jsdom implements `window.getSelection()` but DraftJS also *writes* to it while
// re-rendering leaves (removeAllRanges/addRange/extend). Any stub we install for
// the component's own selection reads therefore has to keep those writers
// present, otherwise the next DraftJS render throws instead of no-op'ing.
function stubSelection(overrides = {}) {
  const selection = {
    anchorNode: null,
    focusNode: null,
    anchorOffset: 0,
    focusOffset: 0,
    rangeCount: 0,
    isCollapsed: true,
    type: 'Caret',
    toString: () => '',
    getRangeAt: () => ({startOffset: 0, endOffset: 0}),
    removeAllRanges: jest.fn(),
    addRange: jest.fn(),
    extend: jest.fn(),
    empty: jest.fn(),
    ...overrides,
  }
  window.getSelection = () => selection
  return selection
}

beforeEach(() => {
  window.config = {
    ...window.config,
    id_job: 2,
    source_code: 'en-US',
    isSourceRTL: false,
    isOpenAiEnabled: false,
  }
  originalGetSelection = window.getSelection
  mockCheckCurrentSegmentTPEnabled.mockReset()
  mockCheckCurrentSegmentTPEnabled.mockReturnValue(false)
  mockGetRanges.mockReset()
  mockGetRanges.mockReturnValue([])
  mockUpdateOffset.mockReset()
  mockUpdateOffset.mockReturnValue([])
  mockGetFragmentFromSelection.mockReset()
  mockGetFragmentFromSelection.mockReturnValue(null)
  mockGetSelectedTextWithoutEntities.mockReset()
  mockGetSelectedTextWithoutEntities.mockReturnValue([])
  jest.clearAllMocks()
})

afterEach(() => {
  window.getSelection = originalGetSelection
})

// Shared DOM lookups, kept in one place so eslint-testing-library's
// no-container/no-node-access rules flag them once here instead of at every
// call site.
const getSourceEl = (container) => container.querySelector('#segment-10-source')
const getSplitNum = (container) => container.querySelector('.splitNum')
const getSplitNumValue = (container) =>
  container.querySelector('.splitNum .num')
const getOptionsToolbar = (container) =>
  container.querySelector('.optionsToolbar')
const getSplitContainer = (container) =>
  container.querySelector('.splitContainer')
const getDraftContent = (container) =>
  container.querySelector('.public-DraftEditor-content')
const getTagEntity = (container, name) =>
  name
    ? container.querySelector(`[data-tag-name="${name}"]`)
    : container.querySelector('[data-testid="tag-entity"]')

// Selects text the only way jsdom allows: clicking a tag makes the component
// force a selection spanning that tag, and the mouse-up that follows is when it
// reads the selection back and decides whether to show the options toolbar.
async function selectTagWithMouse(container) {
  fireEvent.click(getTagEntity(container))
  await flushDeferred()
  fireEvent.mouseUp(getSourceEl(container))
  await flushDeferred()
}

describe('SegmentSource rendering', () => {
  test('renders the source editor with the segment text', async () => {
    const {container} = renderSource(makeSegment())
    await flushTimers()

    const source = getSourceEl(container)
    expect(source).toBeInTheDocument()
    expect(source).toHaveClass('source')
    expect(source).toHaveAttribute('data-original', 'Hello world')
    expect(source).toHaveTextContent('Hello world')
  })

  test('strips tags from the source when tag projection is enabled', async () => {
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()

    expect(mockCheckCurrentSegmentTPEnabled).toHaveBeenCalled()
    expect(getSourceEl(container)).not.toHaveTextContent('<g')
  })

  test('renders tag entities through the tag decorator strategy', async () => {
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()

    expect(getTagEntity(container)).toBeInTheDocument()
  })

  // Regression guard. The tag decorator is built once and kept in a ref, so its
  // props are frozen relative to render — but TagEntity keeps calling them for
  // the life of the segment, re-rendering itself off EDIT_AREA_CHANGED whenever
  // the target is edited. `highlightOnWarnings` turns a tag red once it appears
  // in missingTagsInTarget, which by definition only happens after the
  // decorator was created. If these callbacks close over first-render values,
  // the tag never turns red.
  test('the tag decorator callbacks report the current segment, not the first render one', async () => {
    const {update} = renderSource(
      makeSegment({
        segment: TAGGED_SOURCE,
        missingTagsInTarget: [],
        opened: false,
      }),
    )
    await flushTimers()
    expect(mockTagProps.getUpdatedSegmentInfo()).toMatchObject({
      missingTagsInTarget: [],
      segmentOpened: false,
    })

    const missingTagsInTarget = [{data: {encodedText: '&lt;g id="1"&gt;'}}]
    update(
      makeSegment({segment: TAGGED_SOURCE, missingTagsInTarget, opened: true}),
    )
    await flushTimers()

    expect(mockTagProps.getUpdatedSegmentInfo()).toMatchObject({
      missingTagsInTarget,
      segmentOpened: true,
    })
  })

  test('the tag decorator search params follow the current segment', async () => {
    const {update} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()
    expect(mockTagProps.getSearchParams()).toEqual({active: false})

    update(
      makeSegment({
        segment: TAGGED_SOURCE,
        inSearch: true,
        searchParams: {source: 'world'},
        occurrencesInSearch: {occurrences: [1]},
      }),
    )
    await flushTimers()

    expect(mockTagProps.getSearchParams()).toMatchObject({
      active: true,
      textToReplace: 'world',
    })
  })

  test('renders right-to-left when the source language is RTL', async () => {
    window.config.isSourceRTL = true
    const {container} = renderSource(makeSegment())
    await flushTimers()

    expect(getDraftContent(container)).toBeInTheDocument()
  })

  test('wraps the editor in a split container when openSplit is set', async () => {
    const segment = makeSegment({openSplit: true, split_group: ['10', '11']})
    const {container} = renderSource(segment)
    await flushTimers()

    expect(getSplitContainer(container)).toBeInTheDocument()
    expect(getSplitNumValue(container)).toHaveTextContent('1')

    fireEvent.click(screen.getByText('Cancel'))
    expect(SegmentActions.closeSplitSegment).toHaveBeenCalled()
  })

  test('confirm in split mode dispatches splitSegment with decoded text', async () => {
    const segment = makeSegment({
      openSplit: true,
      split_group: ['10', '11'],
      segment: 'a &lt;b&gt; c',
    })
    renderSource(segment)
    await flushTimers()

    fireEvent.click(screen.getByText('Confirm'))
    expect(SegmentActions.splitSegment).toHaveBeenCalledWith(
      '10',
      expect.stringContaining('<b>'),
      undefined,
    )
  })

  test('hides the split counter and disables confirm when there is no split point', async () => {
    const segment = makeSegment({openSplit: true, split_group: null})
    const {container} = renderSource(segment)
    await flushTimers()

    expect(getSplitNum(container)).toBeNull()
    expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()
  })
})

describe('SegmentSource lifecycle', () => {
  test('registers and unregisters its store listeners', async () => {
    const {unmount} = renderSource(makeSegment())
    await flushTimers()

    const registered = SegmentStore.addListener.mock.calls.map(
      ([event]) => event,
    )
    expect(registered).toContain(SegmentConstants.CLOSE_SPLIT_SEGMENT)
    expect(registered).toContain(SegmentConstants.SET_SEGMENT_TAGGED)
    expect(registered).toContain(SegmentConstants.REFRESH_TAG_MAP)

    unmount()
    const removed = SegmentStore.removeListener.mock.calls.map(
      ([event]) => event,
    )
    expect(removed).toContain(SegmentConstants.CLOSE_SPLIT_SEGMENT)
    expect(removed).toContain(SegmentConstants.REFRESH_TAG_MAP)
  })

  test('pushes the decoded source into the store on mount', async () => {
    renderSource(makeSegment())
    await flushTimers()

    expect(SegmentActions.updateSource).toHaveBeenCalled()
    const [sid, decoded, plainText] = SegmentActions.updateSource.mock.calls[0]
    expect(sid).toBe('10')
    expect(decoded).toContain('Hello world')
    expect(plainText).toContain('Hello world')
  })

  test('does not push the source into the store when the source is empty', async () => {
    renderSource(makeSegment({segment: ''}))
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    // Triggers it the same way production does: broadcast the store event
    // updateSourceInStore is registered against, rather than reaching in.
    const refreshTagMapListener = SegmentStore.addListener.mock.calls.find(
      ([event]) => event === SegmentConstants.REFRESH_TAG_MAP,
    )[1]
    act(() => refreshTagMapListener())
    expect(SegmentActions.updateSource).not.toHaveBeenCalled()
  })

  test('reports the tags covered by the selection when the user clicks one', async () => {
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()
    SegmentActions.focusTags.mockClear()

    fireEvent.click(getTagEntity(container))
    await flushDeferred()

    expect(SegmentActions.focusTags).toHaveBeenCalled()
  })
})

describe('SegmentSource search params', () => {
  // Pure function, tested directly — no rendering needed.
  test('returns the active search descriptor when the source is in search', () => {
    const segment = makeSegment({
      inSearch: true,
      currentInSearch: true,
      currentInSearchIndex: 2,
      searchParams: {source: 'Hello'},
      occurrencesInSearch: {occurrences: [1, 2]},
    })

    expect(getSearchParams(segment)).toEqual({
      active: true,
      currentActive: true,
      textToReplace: 'Hello',
      params: {source: 'Hello'},
      occurrences: [1, 2],
      currentInSearchIndex: 2,
      isTarget: false,
    })
  })

  test('returns an inactive descriptor when the source is not in search', () => {
    expect(getSearchParams(makeSegment())).toEqual({active: false})
  })
})

describe('SegmentSource decorators', () => {
  test('activates the search decorator when the segment enters search', async () => {
    const {update} = renderSource(makeSegment())
    await flushTimers()

    update(
      makeSegment({
        inSearch: true,
        searchParams: {source: 'world'},
        occurrencesInSearch: {occurrences: [1]},
      }),
    )
    await flushTimers()

    expect(DraftMatecatUtils.activateSearch).toHaveBeenCalled()
  })

  test('activates the glossary decorator when the segment gains glossary hits', async () => {
    const {update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{isBlacklist: false, missingTerm: false}]}))
    await flushTimers()

    expect(DraftMatecatUtils.activateGlossary).toHaveBeenCalled()
  })

  test('activates the QA glossary decorator for missing terms', async () => {
    const {update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{missingTerm: true, isBlacklist: false}]}))
    await flushTimers()

    expect(DraftMatecatUtils.activateQaCheckGlossary).toHaveBeenCalled()
  })

  test('activates the lexiqa decorator when warnings resolve to ranges', async () => {
    mockGetRanges.mockReturnValue([{start: 0, end: 2}])
    mockUpdateOffset.mockReturnValue([{start: 0, end: 2}])

    const {update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({lexiqa: {source: [{start: 0, end: 2}]}}))
    await flushTimers()

    expect(DraftMatecatUtils.activateLexiqa).toHaveBeenCalled()
  })

  test('skips the lexiqa decorator when no offsets survive the editor state', async () => {
    mockGetRanges.mockReturnValue([{start: 0, end: 2}])
    mockUpdateOffset.mockReturnValue([])

    const {update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({lexiqa: {source: [{start: 0, end: 2}]}}))
    await flushTimers()

    expect(DraftMatecatUtils.activateLexiqa).not.toHaveBeenCalled()
  })
})

describe('SegmentSource tagged source and tag map', () => {
  function findListener(event) {
    return SegmentStore.addListener.mock.calls.find(
      ([registeredEvent]) => registeredEvent === event,
    )[1]
  }

  async function broadcast(listener, sid) {
    await act(async () => {
      listener(sid)
      await new Promise((resolve) => setTimeout(resolve, 0))
    })
  }

  test('setTaggedSource re-publishes the source for the matching sid', async () => {
    renderSource(makeSegment())
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    await broadcast(findListener(SegmentConstants.SET_SEGMENT_TAGGED), '10')

    expect(SegmentActions.updateSource).toHaveBeenCalled()
  })

  test('setTaggedSource ignores a different sid', async () => {
    renderSource(makeSegment())
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    await broadcast(findListener(SegmentConstants.SET_SEGMENT_TAGGED), '99')

    expect(SegmentActions.updateSource).not.toHaveBeenCalled()
  })

  test('setTaggedSource strips tags when tag projection is enabled', async () => {
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)

    await broadcast(findListener(SegmentConstants.SET_SEGMENT_TAGGED), '10')

    expect(getSourceEl(container)).not.toHaveTextContent('<g')
  })

  test('refreshTagMap re-encodes the content and updates the store', async () => {
    renderSource(makeSegment())
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    await act(async () => {
      findListener(SegmentConstants.REFRESH_TAG_MAP)()
      await new Promise((resolve) => setTimeout(resolve, 120))
    })

    expect(SegmentActions.updateSource).toHaveBeenCalled()
  })
})

describe('SegmentSource concordance', () => {
  // openConcordance is only ever invoked as the callback registered with the
  // (mocked) hotkey hook - extract it the same way the Flux listener tests
  // above extract their registered callback from a mocked addListener.
  const getOpenConcordanceCallback = () =>
    mockUseHotKeysComponent.mock.calls[0][0].callback

  test('opens concordance search for a non-empty range selection', async () => {
    renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Range', toString: () => '  world  '})

    getOpenConcordanceCallback()({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).toHaveBeenCalledWith(
      '10',
      'world',
      false,
    )
  })

  test('ignores a range selection that trims to nothing', async () => {
    renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Range', toString: () => '   '})

    getOpenConcordanceCallback()({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).not.toHaveBeenCalled()
  })

  test('ignores a caret selection', async () => {
    renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Caret', toString: () => ''})

    getOpenConcordanceCallback()({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).not.toHaveBeenCalled()
  })
})

describe('SegmentSource split mode', () => {
  test('shows the joined source when a splitted segment enters split mode', async () => {
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({
      sid,
      segment: `part-${sid}`,
    }))
    const {container, update} = renderSource(
      makeSegment({splitted: true, split_group: ['10', '11']}),
    )
    await flushTimers()

    update(
      makeSegment({splitted: true, split_group: ['10', '11'], openSplit: true}),
    )
    await flushTimers()

    expect(SegmentStore.getSegmentByIdToJS).toHaveBeenCalledWith('10')
    expect(SegmentStore.getSegmentByIdToJS).toHaveBeenCalledWith('11')
    expect(getSourceEl(container)).toHaveTextContent('part-11')
  })

  test('clicking the source in split mode adds a split point and bumps the counter', async () => {
    const {container} = renderSource(
      makeSegment({openSplit: true, split_group: ['10']}),
    )
    await flushTimers()
    stubSelection({anchorNode: null})
    expect(getSplitNum(container)).toBeNull()

    fireEvent.click(getSourceEl(container))
    expect(getSplitNumValue(container)).toHaveTextContent('1')
  })

  test('clicking an inserted split point removes it again', async () => {
    const {container} = renderSource(
      makeSegment({openSplit: true, split_group: ['10']}),
    )
    await flushTimers()
    stubSelection({anchorNode: null})

    fireEvent.click(getSourceEl(container))
    expect(getSplitNumValue(container)).toHaveTextContent('1')

    // Text is selected, so the wrapper's own click handler bails out instead of
    // inserting a second split point, leaving the entity click on its own.
    stubSelection({
      anchorNode: document.createElement('div'),
      getRangeAt: () => ({startOffset: 0, endOffset: 3}),
    })
    fireEvent.click(getTagEntity(container, 'splitPoint'))
    await flushDeferred()

    expect(getSplitNum(container)).toBeNull()
  })

  test('clearing a text selection takes priority over adding a split point', async () => {
    const {container} = renderSource(
      makeSegment({openSplit: true, split_group: ['10']}),
    )
    await flushTimers()
    const {removeAllRanges} = stubSelection({
      anchorNode: document.createElement('div'),
      getRangeAt: () => ({startOffset: 0, endOffset: 3}),
    })
    expect(getSplitNum(container)).toBeNull()

    fireEvent.click(getSourceEl(container))
    expect(removeAllRanges).toHaveBeenCalled()
    expect(getSplitNum(container)).toBeNull()
  })

  test('a collapsed caret inside the editor still adds a split point', async () => {
    const {container} = renderSource(
      makeSegment({openSplit: true, split_group: ['10']}),
    )
    await flushTimers()
    stubSelection({
      anchorNode: document.createElement('div'),
      getRangeAt: () => ({startOffset: 2, endOffset: 2}),
    })
    expect(getSplitNum(container)).toBeNull()

    fireEvent.click(getSourceEl(container))
    expect(getSplitNumValue(container)).toHaveTextContent('1')
  })

  test('closing split mode restores the original split counter', async () => {
    const segment = makeSegment({openSplit: true, split_group: ['10', '11']})
    const {container} = renderSource(segment)
    await flushTimers()
    stubSelection({anchorNode: null})

    fireEvent.click(getSourceEl(container))
    expect(getSplitNumValue(container)).toHaveTextContent('2')

    const endSplitModeListener = SegmentStore.addListener.mock.calls.find(
      ([event]) => event === SegmentConstants.CLOSE_SPLIT_SEGMENT,
    )[1]
    act(() => endSplitModeListener())

    expect(getSplitNumValue(container)).toHaveTextContent('1')
  })
})

describe('SegmentSource editor handlers', () => {
  // Pure functions, tested directly — no rendering needed.
  test('preventEdit reports the event as handled', () => {
    expect(preventEdit()).toBe('handled')
  })

  test('allowHTML wraps a string for dangerouslySetInnerHTML', () => {
    expect(allowHTML('<b>x</b>')).toEqual({__html: '<b>x</b>'})
  })

  test('blurring the source hides the toolbar and clears the tag highlight', async () => {
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()
    await selectTagWithMouse(container)
    expect(getOptionsToolbar(container)).toBeInTheDocument()

    fireEvent.blur(getSourceEl(container))
    await flushDeferred()

    expect(getOptionsToolbar(container)).toBeNull()
    expect(SegmentActions.highlightTags).toHaveBeenCalled()
    expect(SegmentActions.focusTags).toHaveBeenCalledWith([])
  })

  test('cut on the source wrapper is swallowed', async () => {
    const {container} = renderSource(makeSegment())
    await flushTimers()

    const source = getSourceEl(container)
    const cut = new Event('cut', {bubbles: true, cancelable: true})
    fireEvent(source, cut)
    expect(cut.defaultPrevented).toBe(true)
  })

  test('mouse up leaves the toolbar hidden while nothing is selected', async () => {
    const {container} = renderSource(makeSegment())
    await flushTimers()

    fireEvent.mouseUp(getSourceEl(container))
    await flushDeferred()

    expect(getOptionsToolbar(container)).toBeNull()
  })

  test('mouse up does not throw when the editor is unmounted before the deferred read', async () => {
    const {container, unmount} = renderSource(makeSegment())
    await flushTimers()

    const uncaught = jest.fn()
    process.on('uncaughtException', uncaught)

    // The deferred read is scheduled while the editor is still mounted; React
    // nulls the ref on unmount, so the callback lands on a ref that is gone.
    fireEvent.mouseUp(getSourceEl(container))
    unmount()
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    process.off('uncaughtException', uncaught)
    expect(uncaught).not.toHaveBeenCalled()
  })
})

describe('SegmentSource clipboard and drag', () => {
  test('copying without an internal clipboard fragment dispatches nothing', async () => {
    const {container} = renderSource(makeSegment())
    await flushTimers()

    fireEvent.copy(getSourceEl(container))

    expect(SegmentActions.copyFragmentToClipboard).not.toHaveBeenCalled()
  })

  test('dragFragment writes the serialised fragment onto the drag event', async () => {
    mockGetFragmentFromSelection.mockReturnValue([{getText: () => 'Hello'}])
    const {container} = renderSource(makeSegment())
    await flushTimers()
    const dataTransfer = {clearData: jest.fn(), setData: jest.fn()}

    fireEvent.dragStart(getSourceEl(container), {dataTransfer})

    expect(dataTransfer.clearData).toHaveBeenCalled()
    expect(dataTransfer.setData).toHaveBeenCalledWith(
      'text/plain',
      expect.stringContaining('orderedMap'),
    )
    expect(dataTransfer.setData).toHaveBeenCalledWith(
      'text/html',
      expect.stringContaining('orderedMap'),
    )
  })

  test('dragFragment does nothing without a selection fragment', async () => {
    mockGetFragmentFromSelection.mockReturnValue(null)
    const {container} = renderSource(makeSegment())
    await flushTimers()
    const dataTransfer = {clearData: jest.fn(), setData: jest.fn()}

    fireEvent.dragStart(getSourceEl(container), {dataTransfer})
    expect(dataTransfer.setData).not.toHaveBeenCalled()
  })
})

describe('SegmentSource AI assistant', () => {
  // Pure function, tested directly — no rendering needed.
  test('isValidPhraseToAiAssistant accepts up to three words by default', () => {
    expect(isValidPhraseToAiAssistant({phrase: ''})).toBe(false)
    expect(isValidPhraseToAiAssistant({phrase: 'one two three'})).toBe(true)
    expect(isValidPhraseToAiAssistant({phrase: 'one two three four'})).toBe(
      false,
    )
  })

  test.each([
    ['zh-CN', '一二三四五六', '一二三四五六七'],
    ['zh-TW', '一二三四五六', '一二三四五六七'],
    ['zh-HK', '一二三四五六', '一二三四五六七'],
    ['zh-MO', '一二三四五六', '一二三四五六七'],
    ['ja-JP', 'あいうえおかきくけこ', 'あいうえおかきくけこさ'],
  ])(
    'isValidPhraseToAiAssistant applies the %s character limit',
    (lang, valid, tooLong) => {
      expect(
        isValidPhraseToAiAssistant({phrase: valid, sourceLanguageCode: lang}),
      ).toBe(true)
      expect(
        isValidPhraseToAiAssistant({phrase: tooLong, sourceLanguageCode: lang}),
      ).toBe(false)
    },
  )

  test('selecting a tag asks the AI assistant about the selection', async () => {
    window.config.isOpenAiEnabled = true
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()

    await selectTagWithMouse(container)
    await flushDeferred(250)

    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledTimes(1)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'two words',
    })
  })

  test('does not ask the AI assistant when OpenAI is disabled', async () => {
    window.config.isOpenAiEnabled = false
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()

    await selectTagWithMouse(container)
    await flushDeferred(250)

    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('does not ask the AI assistant about a phrase over the word limit', async () => {
    window.config.isOpenAiEnabled = true
    mockGetSelectedTextWithoutEntities.mockReturnValue([
      {value: 'way too many words here'},
    ])
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()

    await selectTagWithMouse(container)
    await flushDeferred(250)

    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('a second selection cancels the request pending for the first', async () => {
    window.config.isOpenAiEnabled = true
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()

    await selectTagWithMouse(container)
    await selectTagWithMouse(container)
    await flushDeferred(250)

    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledTimes(1)
  })
})

describe('SegmentSource options toolbar', () => {
  test('adds the highlighted text to the termbase', async () => {
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}))
    await flushTimers()
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    await selectTagWithMouse(container)

    fireEvent.mouseDown(
      screen.getByTitle('Click to add the highlighted text to the termbase'),
    )
    expect(SegmentActions.openGlossaryFormPrefill).toHaveBeenCalledWith(
      expect.objectContaining({sid: '10'}),
    )
  })

  test('offers the AI assistant shortcut to users without the feature', async () => {
    window.config.isOpenAiEnabled = true
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 0}},
    })
    await flushTimers()
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    await selectTagWithMouse(container)

    fireEvent.mouseDown(
      screen.getByTitle(
        'See the meaning of the highlighted text in this context',
      ),
    )
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'two words',
    })
  })

  test('disables the AI assistant shortcut for an over-long selection', async () => {
    window.config.isOpenAiEnabled = true
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 0}},
    })
    await flushTimers()
    mockGetSelectedTextWithoutEntities.mockReturnValue([
      {value: 'far too many words to allow'},
    ])
    await selectTagWithMouse(container)

    const button = screen.getByTitle(
      "Your selection is over the AI assistant's limit of 3 words, 6 Chinese characters or 10 Japanese characters, please reduce it.",
    )
    fireEvent.mouseDown(button)
    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('hides the AI assistant shortcut when the user already has the feature', async () => {
    window.config.isOpenAiEnabled = true
    const {container} = renderSource(makeSegment({segment: TAGGED_SOURCE}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()
    mockGetSelectedTextWithoutEntities.mockReturnValue([{value: 'two words'}])
    await selectTagWithMouse(container)

    expect(getOptionsToolbar(container)).toBeInTheDocument()
    expect(
      screen.queryByTitle(
        'See the meaning of the highlighted text in this context',
      ),
    ).not.toBeInTheDocument()
  })
})

describe('getUpdatedSegmentInfo', () => {
  test('exposes the current segment and selection', () => {
    const contextSegment = makeSegment({
      warnings: {a: 1},
      tagMismatch: {b: 2},
      missingTagsInTarget: ['x'],
    })
    const tagRange = {start: 0, end: 1}
    const editorState = EditorState.createEmpty()

    const info = getUpdatedSegmentInfo({contextSegment, tagRange, editorState})

    expect(info).toEqual({
      sid: '10',
      warnings: {a: 1},
      tagMismatch: {b: 2},
      tagRange,
      segmentOpened: true,
      missingTagsInTarget: ['x'],
      currentSelection: editorState.getSelection(),
      openSplit: false,
    })
  })
})

// Not covered on purpose:
//
// - The DraftJS `Editor` keystroke/paste/drop handlers (`handleBeforeInput`,
//   `handlePastedText`, `handleDrop`, `handleReturn`, `handleKeyCommand`,
//   `handleDroppedFiles`, `handlePastedFiles`) are all wired to `preventEdit`,
//   which is unit-tested directly above — driving them through the editor needs
//   real contentEditable + `Selection` behaviour that jsdom does not implement.
// - Copying a non-empty fragment: `copyFragment` reads DraftJS's own internal
//   clipboard, which is only ever populated by a real cut/copy against a live
//   contentEditable. Only the empty-clipboard path is reachable here.
// - Triple-click select-all: `CommonUtils.DetectTripleClick` gates on
//   `getBoundingClientRect()` geometry, which jsdom reports as all-zero.
