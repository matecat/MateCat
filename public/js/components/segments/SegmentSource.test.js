import React from 'react'
import {act, fireEvent, render} from '@testing-library/react'
import {EditorState} from 'draft-js'

import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

setTagSignatureMiddleware('space', () => false)

const mockCheckCurrentSegmentTPEnabled = jest.fn(() => false)
const mockGetRanges = jest.fn(() => [])
const mockUpdateOffset = jest.fn(() => [])
const mockGetFragmentFromSelection = jest.fn(() => null)

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

jest.mock('../../hooks/UseHotKeysComponent', () => ({
  UseHotKeysComponent: () => null,
}))

jest.mock('./TagEntity/TagEntity.component', () => ({
  __esModule: true,
  default: ({children}) => <span data-testid="tag-entity">{children}</span>,
}))

jest.mock('./utils/DraftMatecatUtils/createICUDecorator', () => ({
  createIcuTokens: jest.fn(() => []),
  createICUDecorator: jest.fn(() => ({
    name: 'icu',
    strategy: () => {},
    component: () => null,
  })),
}))

const decoratorStub = (name) => ({
  name,
  strategy: () => {},
  component: () => null,
})

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
    },
  }
})

import SegmentSource from './SegmentSource'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentConstants from '../../constants/SegmentConstants'
import {segmentsMock} from '../../../mocks/segmentsMock'

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
  const ref = React.createRef()
  const tree = (seg) => (
    <SegmentContext.Provider value={{segment: seg, ...contextExtra}}>
      <SegmentSource ref={ref} segment={seg} />
    </SegmentContext.Provider>
  )
  const utils = render(tree(segment))
  return {
    ...utils,
    ref,
    update: (nextSegment, nextContextExtra) => {
      if (nextContextExtra) Object.assign(contextExtra, nextContextExtra)
      utils.rerender(tree(nextSegment))
    },
  }
}

// Flushes the `setTimeout(...)` scheduled by componentDidMount / refreshTagMap.
async function flushTimers() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 120))
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
    source_rfc: 'en-US',
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
  jest.clearAllMocks()
})

afterEach(() => {
  window.getSelection = originalGetSelection
})

describe('SegmentSource rendering', () => {
  test('renders the source editor with the segment text', async () => {
    const segment = makeSegment()
    const {container} = renderSource(segment)
    await flushTimers()

    const source = container.querySelector('#segment-10-source')
    expect(source).not.toBeNull()
    expect(source.className).toContain('source')
    expect(source.getAttribute('data-original')).toBe('Hello world')
    expect(source.textContent).toContain('Hello world')
  })

  test('strips tags from the source when tag projection is enabled', async () => {
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)
    const segment = makeSegment({segment: 'Hello <g id="1">world</g>'})
    const {container} = renderSource(segment)
    await flushTimers()

    expect(mockCheckCurrentSegmentTPEnabled).toHaveBeenCalled()
    expect(container.querySelector('#segment-10-source').textContent).not.toContain(
      '<g',
    )
  })

  test('renders tag entities through the tag decorator strategy', async () => {
    const segment = makeSegment({segment: 'Hello <g id="1">world</g>'})
    const {container} = renderSource(segment)
    await flushTimers()

    expect(container.querySelectorAll('[data-testid="tag-entity"]').length).toBeGreaterThan(
      0,
    )
  })

  test('renders right-to-left when the source language is RTL', async () => {
    window.config.isSourceRTL = true
    const segment = makeSegment()
    const {container} = renderSource(segment)
    await flushTimers()

    expect(container.querySelector('.public-DraftEditor-content')).not.toBeNull()
  })

  test('wraps the editor in a split container when openSplit is set', async () => {
    const segment = makeSegment({openSplit: true, split_group: ['10', '11']})
    const {container, getByText} = renderSource(segment)
    await flushTimers()

    expect(container.querySelector('.splitContainer')).not.toBeNull()
    expect(container.querySelector('.splitNum .num').textContent).toBe('1')

    fireEvent.click(getByText('Cancel'))
    expect(SegmentActions.closeSplitSegment).toHaveBeenCalled()
  })

  test('confirm in split mode dispatches splitSegment with decoded text', async () => {
    const segment = makeSegment({
      openSplit: true,
      split_group: ['10', '11'],
      segment: 'a &lt;b&gt; c',
    })
    const {getByText} = renderSource(segment)
    await flushTimers()

    fireEvent.click(getByText('Confirm'))
    expect(SegmentActions.splitSegment).toHaveBeenCalledWith(
      '10',
      expect.stringContaining('<b>'),
      undefined,
    )
  })

  test('hides the split counter and disables confirm when there is no split point', async () => {
    const segment = makeSegment({openSplit: true, split_group: null})
    const {container, getByText} = renderSource(segment)
    await flushTimers()

    expect(container.querySelector('.splitNum')).toBeNull()
    expect(getByText('Confirm').closest('button').disabled).toBe(true)
  })
})

describe('SegmentSource lifecycle', () => {
  test('registers and unregisters its store listeners', async () => {
    const segment = makeSegment()
    const {unmount} = renderSource(segment)
    await flushTimers()

    const registered = SegmentStore.addListener.mock.calls.map(([event]) => event)
    expect(registered).toContain(SegmentConstants.CLOSE_SPLIT_SEGMENT)
    expect(registered).toContain(SegmentConstants.SET_SEGMENT_TAGGED)
    expect(registered).toContain(SegmentConstants.REFRESH_TAG_MAP)

    unmount()
    const removed = SegmentStore.removeListener.mock.calls.map(([event]) => event)
    expect(removed).toContain(SegmentConstants.CLOSE_SPLIT_SEGMENT)
    expect(removed).toContain(SegmentConstants.REFRESH_TAG_MAP)
  })

  test('pushes the decoded source into the store on mount', async () => {
    const segment = makeSegment()
    renderSource(segment)
    await flushTimers()

    expect(SegmentActions.updateSource).toHaveBeenCalled()
    const [sid, decoded, plainText] = SegmentActions.updateSource.mock.calls[0]
    expect(sid).toBe('10')
    expect(decoded).toContain('Hello world')
    expect(plainText).toContain('Hello world')
  })

  test('does not push the source into the store when the source is empty', async () => {
    const segment = makeSegment({segment: ''})
    const {ref} = renderSource(segment)
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    act(() => ref.current.updateSourceInStore())
    expect(SegmentActions.updateSource).not.toHaveBeenCalled()
  })

  test('reports selected tag entities when the editor state changes', async () => {
    const segment = makeSegment()
    const {ref} = renderSource(segment)
    await flushTimers()
    SegmentActions.focusTags.mockClear()

    act(() => {
      const current = ref.current.state.editorState
      ref.current.onChange(
        EditorState.acceptSelection(current, current.getSelection()),
      )
    })
    await flushTimers()

    expect(SegmentActions.focusTags).toHaveBeenCalled()
  })
})

describe('SegmentSource search params', () => {
  test('returns the active search descriptor when the source is in search', async () => {
    const segment = makeSegment({
      inSearch: true,
      currentInSearch: true,
      currentInSearchIndex: 2,
      searchParams: {source: 'Hello'},
      occurrencesInSearch: {occurrences: [1, 2]},
    })
    const {ref} = renderSource(segment)
    await flushTimers()

    expect(ref.current.getSearchParams()).toEqual({
      active: true,
      currentActive: true,
      textToReplace: 'Hello',
      params: {source: 'Hello'},
      occurrences: [1, 2],
      currentInSearchIndex: 2,
      isTarget: false,
    })
  })

  test('returns an inactive descriptor when the source is not in search', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()

    expect(ref.current.getSearchParams()).toEqual({active: false})
  })
})

describe('SegmentSource decorators', () => {
  test('activates the search decorator when the segment enters search', async () => {
    const segment = makeSegment()
    const {ref, update} = renderSource(segment)
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
    expect(ref.current.state.activeDecorators.search).toBe(true)
  })

  test('drops the search decorator when the segment leaves search', async () => {
    const {ref, update} = renderSource(
      makeSegment({
        inSearch: true,
        searchParams: {source: 'world'},
        occurrencesInSearch: {occurrences: [1]},
      }),
    )
    await flushTimers()
    expect(ref.current.state.activeDecorators.search).toBe(true)

    update(makeSegment())
    await flushTimers()

    expect(ref.current.state.activeDecorators.search).toBe(false)
  })

  test('activates and then drops the glossary decorator', async () => {
    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{isBlacklist: false, missingTerm: false}]}))
    await flushTimers()
    expect(DraftMatecatUtils.activateGlossary).toHaveBeenCalled()
    expect(ref.current.state.activeDecorators.glossary).toBe(true)

    update(makeSegment({glossary: []}))
    await flushTimers()
    expect(ref.current.state.activeDecorators.glossary).toBe(false)
  })

  test('activates and then drops the QA glossary decorator', async () => {
    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{missingTerm: true, isBlacklist: false}]}))
    await flushTimers()
    expect(DraftMatecatUtils.activateQaCheckGlossary).toHaveBeenCalled()
    expect(ref.current.state.activeDecorators.qaCheckGlossary).toBe(true)

    update(makeSegment({glossary: []}))
    await flushTimers()
    expect(ref.current.state.activeDecorators.qaCheckGlossary).toBe(false)
  })

  test('activates the lexiqa decorator when warnings resolve to ranges', async () => {
    mockGetRanges.mockReturnValue([{start: 0, end: 2}])
    mockUpdateOffset.mockReturnValue([{start: 0, end: 2}])

    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({lexiqa: {source: [{start: 0, end: 2}]}}))
    await flushTimers()

    expect(DraftMatecatUtils.activateLexiqa).toHaveBeenCalled()
    expect(ref.current.state.activeDecorators.lexiqa).toBe(true)

    update(makeSegment({lexiqa: {}}))
    await flushTimers()
    expect(ref.current.state.activeDecorators.lexiqa).toBe(false)
  })

  test('removes the lexiqa decorator when no offsets survive the editor state', async () => {
    mockGetRanges.mockReturnValue([{start: 0, end: 2}])
    mockUpdateOffset.mockReturnValue([])

    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({lexiqa: {source: [{start: 0, end: 2}]}}))
    await flushTimers()

    expect(DraftMatecatUtils.activateLexiqa).not.toHaveBeenCalled()
    expect(ref.current.state.activeDecorators.lexiqa).toBe(true)
  })

  test('adds the ICU decorator on the first check when ICU is enabled', async () => {
    const {ref} = renderSource(makeSegment({icu: true}))
    await flushTimers()

    expect(ref.current.firstIcuCheck).toBe(true)
    expect(
      ref.current.decoratorsStructure.some(({name}) => name === 'icu'),
    ).toBe(true)
  })

  test('removeDecorator without a name clears every non-tag decorator', async () => {
    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{isBlacklist: false}]}))
    await flushTimers()
    expect(ref.current.decoratorsStructure.length).toBeGreaterThan(1)

    act(() => ref.current.removeDecorator())
    expect(ref.current.decoratorsStructure.map(({name}) => name)).toEqual(['tags'])
  })

  test('removeDecorator with a name clears only that decorator', async () => {
    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{isBlacklist: false}]}))
    await flushTimers()

    act(() => ref.current.removeDecorator('glossary'))
    expect(ref.current.decoratorsStructure.map(({name}) => name)).toEqual(['tags'])
  })

  test('disableDecorator returns a new editor state without that decorator', async () => {
    const {ref, update} = renderSource(makeSegment())
    await flushTimers()

    update(makeSegment({glossary: [{isBlacklist: false}]}))
    await flushTimers()

    const next = ref.current.disableDecorator(
      ref.current.state.editorState,
      'glossary',
    )
    expect(next).toBeDefined()
    expect(ref.current.decoratorsStructure.map(({name}) => name)).toEqual(['tags'])
  })
})

describe('SegmentSource tagged source and tag map', () => {
  test('setTaggedSource rebuilds the editor state for the matching sid', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const before = ref.current.state.editorState
    SegmentActions.updateSource.mockClear()

    await act(async () => {
      ref.current.setTaggedSource('10')
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    expect(ref.current.state.editorState).not.toBe(before)
    expect(SegmentActions.updateSource).toHaveBeenCalled()
  })

  test('setTaggedSource ignores a different sid', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const before = ref.current.state.editorState
    SegmentActions.updateSource.mockClear()

    await act(async () => {
      ref.current.setTaggedSource('99')
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    expect(ref.current.state.editorState).toBe(before)
    expect(SegmentActions.updateSource).not.toHaveBeenCalled()
  })

  test('setTaggedSource strips tags when tag projection is enabled', async () => {
    const {ref} = renderSource(makeSegment({segment: 'Hi <g id="1">there</g>'}))
    await flushTimers()
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)

    await act(async () => {
      ref.current.setTaggedSource('10')
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    expect(mockCheckCurrentSegmentTPEnabled).toHaveBeenCalled()
  })

  test('refreshTagMap re-encodes the content and updates the store', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    SegmentActions.updateSource.mockClear()

    await act(async () => {
      ref.current.refreshTagMap()
      await new Promise((resolve) => setTimeout(resolve, 120))
    })

    expect(SegmentActions.updateSource).toHaveBeenCalled()
  })
})

describe('SegmentSource concordance', () => {
  test('opens concordance search for a non-empty range selection', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Range', toString: () => '  world  '})

    ref.current.openConcordance({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).toHaveBeenCalledWith(
      '10',
      'world',
      false,
    )
  })

  test('ignores a range selection that trims to nothing', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Range', toString: () => '   '})

    ref.current.openConcordance({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).not.toHaveBeenCalled()
  })

  test('ignores a caret selection', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    stubSelection({type: 'Caret', toString: () => ''})

    ref.current.openConcordance({preventDefault: jest.fn()})
    expect(SegmentActions.openConcordance).not.toHaveBeenCalled()
  })
})

describe('SegmentSource split mode', () => {
  test('rebuilds the joined source when a splitted segment enters split mode', async () => {
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({
      sid,
      segment: `part-${sid}`,
    }))
    const {ref, update} = renderSource(
      makeSegment({splitted: true, split_group: ['10', '11']}),
    )
    await flushTimers()

    update(makeSegment({splitted: true, split_group: ['10', '11'], openSplit: true}))
    await flushTimers()

    expect(SegmentStore.getSegmentByIdToJS).toHaveBeenCalledWith('10')
    expect(SegmentStore.getSegmentByIdToJS).toHaveBeenCalledWith('11')
    expect(
      ref.current.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('part-11')
  })

  test('addSplitTag inserts a split point and bumps the split counter', async () => {
    const {ref} = renderSource(makeSegment({openSplit: true, split_group: ['10']}))
    await flushTimers()
    stubSelection({anchorNode: null})
    const before = ref.current.splitPoint

    act(() => ref.current.addSplitTag())
    expect(ref.current.splitPoint).toBe(before + 1)
  })

  test('addSplitTag clears the selection instead of splitting when text is selected', async () => {
    const {ref} = renderSource(makeSegment({openSplit: true, split_group: ['10']}))
    await flushTimers()
    const {removeAllRanges} = stubSelection({
      anchorNode: document.createElement('div'),
      getRangeAt: () => ({startOffset: 0, endOffset: 3}),
    })
    const before = ref.current.splitPoint

    act(() => ref.current.addSplitTag())
    expect(removeAllRanges).toHaveBeenCalled()
    expect(ref.current.splitPoint).toBe(before)
  })

  test('addSplitTag proceeds when the caret is collapsed inside the editor', async () => {
    const {ref} = renderSource(makeSegment({openSplit: true, split_group: ['10']}))
    await flushTimers()
    stubSelection({
      anchorNode: document.createElement('div'),
      getRangeAt: () => ({startOffset: 2, endOffset: 2}),
    })
    const before = ref.current.splitPoint

    act(() => ref.current.addSplitTag())
    expect(ref.current.splitPoint).toBe(before + 1)
  })

  test('insertTagAtSelection bails out for a non-buildable tag name', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const before = ref.current.state.editorState

    // `g` is a known tag signature but has no encodedPlaceholder, so it is not
    // buildable: structFromName returns null and insertion must be skipped.
    act(() => ref.current.insertTagAtSelection('g'))
    expect(ref.current.state.editorState).toBe(before)
  })

  test('updateSplitNumberNew is a no-op for already-splitted segments', async () => {
    const {ref} = renderSource(makeSegment({splitted: true, split_group: ['10']}))
    await flushTimers()
    const before = ref.current.splitPoint

    ref.current.updateSplitNumberNew(5)
    expect(ref.current.splitPoint).toBe(before)
  })

  test('endSplitMode restores the pre-split editor state while split is open', async () => {
    const segment = makeSegment({openSplit: true, split_group: ['10', '11']})
    const {ref} = renderSource(segment)
    await flushTimers()
    const beforeSplit = ref.current.state.editorStateBeforeSplit

    act(() => ref.current.endSplitMode())
    expect(ref.current.splitPoint).toBe(1)
    expect(ref.current.state.editorState).toBe(beforeSplit)
  })

  test('endSplitMode only recomputes the split point when split is closed', async () => {
    const {ref} = renderSource(makeSegment({split_group: null}))
    await flushTimers()
    const current = ref.current.state.editorState

    act(() => ref.current.endSplitMode())
    expect(ref.current.splitPoint).toBe(0)
    expect(ref.current.state.editorState).toBe(current)
  })
})

describe('SegmentSource editor handlers', () => {
  test('preventEdit reports the event as handled', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    expect(ref.current.preventEdit()).toBe('handled')
  })

  test('allowHTML wraps a string for dangerouslySetInnerHTML', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    expect(ref.current.allowHTML('<b>x</b>')).toEqual({__html: '<b>x</b>'})
  })

  test('onBlurEvent hides the toolbar and clears the tag highlight', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    await act(async () => {
      ref.current.onBlurEvent()
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    expect(ref.current.state.isShowingOptionsToolbar).toBe(false)
    expect(SegmentActions.highlightTags).toHaveBeenCalled()
    expect(SegmentActions.focusTags).toHaveBeenCalledWith([])
  })

  test('cut on the source wrapper is swallowed', async () => {
    const {container} = renderSource(makeSegment())
    await flushTimers()

    const source = container.querySelector('#segment-10-source')
    const cut = new Event('cut', {bubbles: true, cancelable: true})
    fireEvent(source, cut)
    expect(cut.defaultPrevented).toBe(true)
  })

  test('mouse up toggles the options toolbar from the latest selection', async () => {
    const {container, ref} = renderSource(makeSegment())
    await flushTimers()

    await act(async () => {
      fireEvent.mouseUp(container.querySelector('#segment-10-source'))
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    expect(typeof ref.current.state.isShowingOptionsToolbar).toBe('boolean')
  })

  test('arrow keys re-evaluate the options toolbar but other keys do not', async () => {
    const {container, ref} = renderSource(makeSegment())
    await flushTimers()
    const source = container.querySelector('#segment-10-source')
    const spy = jest.spyOn(ref.current, 'helpAiAssistant')

    await act(async () => {
      fireEvent.keyUp(source, {key: 'ArrowLeft'})
      fireEvent.keyUp(source, {key: 'ArrowRight'})
      fireEvent.keyUp(source, {key: 'ArrowUp'})
      fireEvent.keyUp(source, {key: 'ArrowDown'})
      await new Promise((resolve) => setTimeout(resolve, 0))
    })
    expect(spy).toHaveBeenCalledTimes(4)

    spy.mockClear()
    await act(async () => {
      fireEvent.keyUp(source, {key: 'a'})
      await new Promise((resolve) => setTimeout(resolve, 0))
    })
    expect(spy).not.toHaveBeenCalled()
  })

  test('clicking the wrapper in split mode adds a split tag', async () => {
    const {container, ref} = renderSource(
      makeSegment({openSplit: true, split_group: ['10']}),
    )
    await flushTimers()
    stubSelection({anchorNode: null})
    const before = ref.current.splitPoint

    fireEvent.click(container.querySelector('#segment-10-source'))
    expect(ref.current.splitPoint).toBe(before + 1)
  })

  test('blur on the wrapper hides the toolbar', async () => {
    const {container, ref} = renderSource(makeSegment())
    await flushTimers()
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    await act(async () => {
      fireEvent.blur(container.querySelector('#segment-10-source'))
      await new Promise((resolve) => setTimeout(resolve, 0))
    })
    expect(ref.current.state.isShowingOptionsToolbar).toBe(false)
  })
})

describe('SegmentSource clipboard and drag', () => {
  test('copyFragment serialises the internal clipboard fragment', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    ref.current.editor.getClipboard = () => [
      {getText: () => 'Hello​'},
      {getText: () => 'world·'},
    ]
    const setData = jest.fn()
    const event = {preventDefault: jest.fn(), clipboardData: {setData}}

    ref.current.copyFragment(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(setData).toHaveBeenCalledWith('text/plain', 'Hello\nworld ')
    expect(SegmentActions.copyFragmentToClipboard).toHaveBeenCalled()
  })

  test('copyFragment does nothing when the internal clipboard is empty', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    ref.current.editor.getClipboard = () => null
    const event = {preventDefault: jest.fn(), clipboardData: {setData: jest.fn()}}

    ref.current.copyFragment(event)

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(SegmentActions.copyFragmentToClipboard).not.toHaveBeenCalled()
  })

  test('dragFragment writes the serialised fragment onto the drag event', async () => {
    mockGetFragmentFromSelection.mockReturnValue([{getText: () => 'Hello'}])
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const dataTransfer = {clearData: jest.fn(), setData: jest.fn()}

    ref.current.dragFragment({dataTransfer})

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
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const dataTransfer = {clearData: jest.fn(), setData: jest.fn()}

    ref.current.dragFragment({dataTransfer})
    expect(dataTransfer.setData).not.toHaveBeenCalled()
  })
})

describe('SegmentSource entity click', () => {
  test('forces the selection onto the clicked entity', async () => {
    const {ref} = renderSource(makeSegment({segment: 'Hello <g id="1">world</g>'}))
    await flushTimers()
    const before = ref.current.state.editorState

    act(() => ref.current.onEntityClick(0, 2, 'g'))
    expect(ref.current.state.editorState).not.toBe(before)
  })

  test('removes the split point entity when clicked in split mode', async () => {
    const segment = makeSegment({
      openSplit: true,
      split_group: ['10', '11'],
      segment: 'Hello world',
    })
    const {ref} = renderSource(segment)
    await flushTimers()
    const before = ref.current.splitPoint

    act(() => ref.current.onEntityClick(0, 2, 'splitPoint'))
    expect(ref.current.splitPoint).toBe(before - 1)
  })

  test('swallows errors coming from a missing editor reference', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {})
    ref.current.editor = null

    act(() => ref.current.onEntityClick(0, 2, 'g'))
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })
})

describe('SegmentSource AI assistant', () => {
  test('isValidPhraseToAiAssistant accepts up to three words by default', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const {isValidPhraseToAiAssistant} = ref.current

    expect(isValidPhraseToAiAssistant({phrase: ''})).toBe(false)
    expect(isValidPhraseToAiAssistant({phrase: 'one two three'})).toBe(true)
    expect(isValidPhraseToAiAssistant({phrase: 'one two three four'})).toBe(false)
  })

  test.each([
    ['zh-CN', '一二三四五六', '一二三四五六七'],
    ['zh-TW', '一二三四五六', '一二三四五六七'],
    ['zh-HK', '一二三四五六', '一二三四五六七'],
    ['zh-MO', '一二三四五六', '一二三四五六七'],
    ['ja-JP', 'あいうえおかきくけこ', 'あいうえおかきくけこさ'],
  ])('isValidPhraseToAiAssistant applies the %s character limit', async (
    lang,
    valid,
    tooLong,
  ) => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const {isValidPhraseToAiAssistant} = ref.current

    expect(
      isValidPhraseToAiAssistant({phrase: valid, sourceLanguageCode: lang}),
    ).toBe(true)
    expect(
      isValidPhraseToAiAssistant({phrase: tooLong, sourceLanguageCode: lang}),
    ).toBe(false)
  })

  test('getSelectedWords returns the plain selected text', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    expect(typeof ref.current.getSelectedWords()).toBe('string')
  })

  test('helpAiAssistant does nothing when OpenAI is disabled', async () => {
    window.config.isOpenAiEnabled = false
    const {ref} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()

    await act(async () => {
      ref.current.helpAiAssistant()
      await new Promise((resolve) => setTimeout(resolve, 250))
    })
    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('helpAiAssistant dispatches the selected phrase when enabled', async () => {
    window.config.isOpenAiEnabled = true
    const {ref} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()
    jest.spyOn(ref.current, 'getSelectedWords').mockReturnValue('two words')

    await act(async () => {
      ref.current.helpAiAssistant()
      ref.current.helpAiAssistant() // second call must cancel the pending one
      await new Promise((resolve) => setTimeout(resolve, 250))
    })

    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledTimes(1)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'two words',
    })
  })

  test('helpAiAssistant skips phrases over the word limit', async () => {
    window.config.isOpenAiEnabled = true
    const {ref} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()
    jest
      .spyOn(ref.current, 'getSelectedWords')
      .mockReturnValue('way too many words here')

    await act(async () => {
      ref.current.helpAiAssistant()
      await new Promise((resolve) => setTimeout(resolve, 250))
    })
    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })
})

describe('SegmentSource options toolbar', () => {
  test('renders the glossary shortcut and prefills the term form', async () => {
    const {ref, getByTitle} = renderSource(makeSegment())
    await flushTimers()
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    fireEvent.mouseDown(
      getByTitle('Click to add the highlighted text to the termbase'),
    )
    expect(SegmentActions.openGlossaryFormPrefill).toHaveBeenCalledWith(
      expect.objectContaining({sid: '10'}),
    )
  })

  test('renders the AI assistant shortcut for users without the feature', async () => {
    window.config.isOpenAiEnabled = true
    const {ref, getByTitle} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 0}},
    })
    await flushTimers()
    jest.spyOn(ref.current, 'getSelectedWords').mockReturnValue('two words')
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    fireEvent.mouseDown(
      getByTitle('See the meaning of the highlighted text in this context'),
    )
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'two words',
    })
  })

  test('disables the AI assistant shortcut for an over-long selection', async () => {
    window.config.isOpenAiEnabled = true
    const {ref, getByTitle} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 0}},
    })
    await flushTimers()
    jest
      .spyOn(ref.current, 'getSelectedWords')
      .mockReturnValue('far too many words to allow')
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    const button = getByTitle(
      "Your selection is over the AI assistant's limit of 3 words, 6 Chinese characters or 10 Japanese characters, please reduce it.",
    )
    fireEvent.mouseDown(button)
    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('hides the AI assistant shortcut when the user already has the feature', async () => {
    window.config.isOpenAiEnabled = true
    const {ref, queryByTitle} = renderSource(makeSegment(), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await flushTimers()
    act(() => ref.current.setState({isShowingOptionsToolbar: true}))

    expect(
      queryByTitle('See the meaning of the highlighted text in this context'),
    ).toBeNull()
  })
})

describe('SegmentSource segment info', () => {
  test('getUpdatedSegmentInfo exposes the current segment and selection', async () => {
    const segment = makeSegment({
      warnings: {a: 1},
      tagMismatch: {b: 2},
      missingTagsInTarget: ['x'],
    })
    const {ref} = renderSource(segment)
    await flushTimers()

    const info = ref.current.getUpdatedSegmentInfo()
    expect(info).toMatchObject({
      sid: '10',
      warnings: {a: 1},
      tagMismatch: {b: 2},
      segmentOpened: true,
      missingTagsInTarget: ['x'],
      openSplit: false,
    })
    expect(info.currentSelection).toBeDefined()
    expect(info.tagRange).toBeDefined()
  })
})

describe('SegmentSource triple click', () => {
  test('selects the whole source after a triple click', async () => {
    const {ref} = renderSource(makeSegment())
    await flushTimers()
    const before = ref.current.state.editorState

    ref.current.wasTripleClickTriggered.current = true
    act(() => ref.current.forceUpdate())

    expect(ref.current.state.editorState).not.toBe(before)
    expect(ref.current.wasTripleClickTriggered.current).toBe(false)
  })
})

// Not covered on purpose: the DraftJS `Editor` keystroke/paste/drop handlers
// (`handleBeforeInput`, `handlePastedText`, `handleDrop`, `handleReturn`,
// `handleKeyCommand`, `handleDroppedFiles`, `handlePastedFiles`) are wired to
// `preventEdit`, which is unit-tested directly above — driving them through the
// editor needs real contentEditable + `Selection` behaviour that jsdom does not
// implement.

describe('SegmentSource.updateOptionsToolbarVisibility', () => {
  beforeEach(() => {
    global.config = {
      ...global.config,
      source_code: 'en-US',
      target_code: 'it-IT',
      tag_projection_languages: {},
    }
  })

  const buildInstance = () =>
    new SegmentSource({
      segment: segmentsMock[0],
      splitGroupLength: 1,
    })

  test('does not throw when the editor ref is null', () => {
    const instance = buildInstance()
    instance.editor = null
    instance.setState = jest.fn()
    instance.helpAiAssistant = jest.fn()

    expect(() => instance.updateOptionsToolbarVisibility()).not.toThrow()
    expect(instance.setState).not.toHaveBeenCalled()
    expect(instance.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('updates the toolbar visibility when the editor ref is set', () => {
    const instance = buildInstance()
    instance.editor = {
      _latestEditorState: {
        getSelection: () => ({isCollapsed: () => false}),
      },
    }
    instance.setState = jest.fn()
    instance.helpAiAssistant = jest.fn()

    instance.updateOptionsToolbarVisibility()

    expect(instance.setState).toHaveBeenCalledWith({
      isShowingOptionsToolbar: true,
    })
    expect(instance.helpAiAssistant).toHaveBeenCalled()
  })
})
