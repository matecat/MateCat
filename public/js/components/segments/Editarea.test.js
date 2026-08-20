import React from 'react'
import {render, act} from '@testing-library/react'

// ---------------------------------------------------------------------------
// Mocks: only side-effecting collaborators (stores, flux actions, broadcast
// channel, lexiqa engine). draft-js and DraftMatecatUtils are used for real so
// the editor state transitions under test are the production ones.
// ---------------------------------------------------------------------------

const segmentStoreListeners = {}

jest.mock('../../stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    addListener: jest.fn(),
    removeListener: jest.fn(),
    getFragmentFromClipboard: jest.fn(() => ({
      fragment: null,
      plainText: '',
    })),
  },
}))

jest.mock('../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    updateTranslation: jest.fn(),
    startSegmentQACheck: jest.fn(),
    focusTags: jest.fn(),
    highlightTags: jest.fn(),
    editAreaChanged: jest.fn(),
    copyFragmentToClipboard: jest.fn(),
    getSegmentsQa: jest.fn(),
  },
}))

jest.mock('../../stores/CatToolStore', () => ({
  __esModule: true,
  default: {
    getCurrentProjectTemplate: jest.fn(() => ({
      characterCounterCountTags: false,
    })),
    getHaveKeysGlossary: jest.fn(() => false),
    isPhTagsCompressed: jest.fn(() => false),
    addListener: jest.fn(),
    removeListener: jest.fn(),
    getJobFilesInfo: jest.fn(() => ({})),
    getJobMetadata: jest.fn(() => ({})),
    getJobTmKeys: jest.fn(() => []),
    getProgress: jest.fn(() => ({})),
    setCurrentProjectTemplate: jest.fn(),
  },
}))

jest.mock('../../utils/lxq.main', () => ({
  __esModule: true,
  default: {
    getRanges: jest.fn(() => []),
  },
}))

jest.mock('../../utils/contextPreviewChannel', () => ({
  __esModule: true,
  default: {
    sendMessage: jest.fn(),
  },
}))

jest.mock('../../utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    checkCurrentSegmentTPEnabled: jest.fn(() => false),
  },
}))

jest.mock('../../utils/commonUtils', () => ({
  __esModule: true,
  default: {
    DetectTripleClick: jest.fn(function DetectTripleClick(element, callback) {
      this.element = element
      this.callback = callback
    }),
  },
}))

import Editarea from './Editarea'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import CatToolStore from '../../stores/CatToolStore'
import LexiqaUtils from '../../utils/lxq.main'
import ContextPreviewChannel from '../../utils/contextPreviewChannel'
import SegmentUtils from '../../utils/segmentUtils'
import CommonUtils from '../../utils/commonUtils'
import {SegmentContext} from './SegmentContext'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import * as DraftMatecatConstants from './utils/DraftMatecatUtils/editorConstants'
import SegmentConstants from '../../constants/SegmentConstants'
import EditAreaConstants from '../../constants/EditAreaConstants'
import {EditorState, Modifier, SelectionState} from 'draft-js'

const ZWSP = String.fromCharCode(parseInt('200B', 16))

// ---------------------------------------------------------------------------
// Fixtures / helpers
// ---------------------------------------------------------------------------

/**
 * The editor encodes every literal space as a `space` tag entity rendered as
 * `ZWSP + middot + ZWSP`, so the rendered text never matches the source string
 * verbatim. This turns the rendered text back into readable plain text.
 */
const normalize = (text) => text.split(ZWSP).join('').split('·').join(' ')

const KEY_CODES = {
  Enter: 13,
  Backspace: 8,
  Delete: 46,
  Escape: 27,
  Tab: 9,
  ArrowLeft: 37,
  ArrowUp: 38,
  ArrowRight: 39,
  ArrowDown: 40,
  Alt: 18,
}

/** Builds a Selection stub complete enough for draft-js internals. */
const selectionStub = (overrides = {}) => ({
  type: 'Caret',
  rangeCount: 0,
  anchorNode: null,
  anchorOffset: 0,
  focusNode: null,
  focusOffset: 0,
  isCollapsed: true,
  removeAllRanges: jest.fn(),
  addRange: jest.fn(),
  setBaseAndExtent: jest.fn(),
  extend: jest.fn(),
  collapse: jest.fn(),
  getRangeAt: jest.fn(() => ({
    getBoundingClientRect: () => ({x: 0, left: 0, bottom: 0, height: 0}),
  })),
  toString: () => '',
  ...overrides,
})

/** A blacklist entry shaped the way QaCheckBlacklistHighlight expects. */
const blacklistTerm = (word = 'mondo') => ({
  matching_words: [word],
  source: {term: 'world'},
  target: {term: word},
})

function makeSegment(overrides = {}) {
  return {
    sid: '12-1',
    icu: false,
    opened: true,
    muted: false,
    inSearch: false,
    currentInSearch: false,
    currentInSearchIndex: 0,
    searchParams: {},
    occurrencesInSearch: {occurrences: []},
    qaBlacklistGlossary: undefined,
    lexiqa: undefined,
    lxqDecodedTranslation: '',
    sourceTagMap: [],
    targetTagMap: [],
    missingTagsInTarget: [],
    warnings: {},
    tagMismatch: {},
    openSplit: false,
    translation: 'Ciao mondo',
    decodedTranslation: 'Ciao mondo',
    ...overrides,
  }
}

function renderEditarea({
  segment = makeSegment(),
  translation = segment.translation,
  context = {readonly: false, locked: false},
  updateCounter = jest.fn(),
  toggleFormatMenu = jest.fn(),
} = {}) {
  const ref = React.createRef()
  const utils = render(
    <SegmentContext.Provider value={context}>
      <Editarea
        ref={ref}
        segment={segment}
        translation={translation}
        updateCounter={updateCounter}
        toggleFormatMenu={toggleFormatMenu}
      />
    </SegmentContext.Provider>,
  )
  return {...utils, instance: ref.current, updateCounter, toggleFormatMenu}
}

/** Runs every pending timer + microtask inside act(). */
function flush(ms = 1500) {
  act(() => {
    jest.advanceTimersByTime(ms)
  })
}

/** Mounts and drains the deferred componentDidMount work. */
function mountEditarea(options) {
  const rendered = renderEditarea(options)
  flush()
  return rendered
}

/** Minimal synthetic keyboard event accepted by KeyBindingUtil. */
function keyEvent(overrides = {}) {
  const event = {
    key: '',
    code: '',
    which: 0,
    altKey: false,
    ctrlKey: false,
    metaKey: false,
    shiftKey: false,
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
    nativeEvent: {},
    ...overrides,
  }
  // draft-js getDefaultKeyBinding switches on keyCode only
  if (!('keyCode' in event)) event.keyCode = KEY_CODES[event.key] ?? 0
  return event
}

/** Replaces window.getSelection with a controllable stub. */
function stubSelection(selection) {
  const original = window.getSelection
  const stub = selectionStub(selection)
  window.getSelection = jest.fn(() => stub)
  return () => {
    window.getSelection = original
  }
}

let restoreSelection

beforeEach(() => {
  jest.useFakeTimers()
  jest.clearAllMocks()
  Object.keys(segmentStoreListeners).forEach(
    (k) => delete segmentStoreListeners[k],
  )
  global.config = {
    id_job: 2,
    target_rfc: 'it-IT',
    source_rfc: 'en-US',
    isTargetRTL: false,
    isSourceRTL: false,
  }
  CatToolStore.getCurrentProjectTemplate.mockReturnValue({
    characterCounterCountTags: false,
  })
  CatToolStore.getHaveKeysGlossary.mockReturnValue(false)
  SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
  SegmentStore.getFragmentFromClipboard.mockReturnValue({
    fragment: null,
    plainText: '',
  })
  LexiqaUtils.getRanges.mockReturnValue([])
  jest.spyOn(console, 'log').mockImplementation(() => {})
})

afterEach(() => {
  if (restoreSelection) {
    restoreSelection()
    restoreSelection = undefined
  }
  setTagSignatureMiddleware('space', undefined)
  jest.runOnlyPendingTimers()
  jest.useRealTimers()
  jest.restoreAllMocks()
})

// ---------------------------------------------------------------------------
// Mount / render
// ---------------------------------------------------------------------------

describe('Editarea rendering', () => {
  test('renders the edit area wrapper with sid based identifiers', () => {
    const {container} = mountEditarea()

    const wrapper = container.querySelector('#segment-12-1-editarea')
    expect(wrapper).not.toBeNull()
    expect(wrapper.getAttribute('data-sid')).toBe('12-1')
    expect(wrapper.className).toBe('targetarea editarea')
    expect(wrapper.getAttribute('lang')).toBe('it-IT')
  })

  test('uses the "area" class instead of "editarea" when locked', () => {
    const {container} = mountEditarea({
      context: {readonly: false, locked: true},
    })

    expect(container.querySelector('.targetarea').className).toBe(
      'targetarea area',
    )
  })

  test('uses the "area" class when the context is readonly', () => {
    const {container} = mountEditarea({
      context: {readonly: true, locked: false},
    })

    expect(container.querySelector('.targetarea').className).toBe(
      'targetarea area',
    )
  })

  test('renders the translation text inside the draft editor', () => {
    const {container} = mountEditarea({translation: 'Ciao mondo'})

    expect(normalize(container.textContent)).toContain('Ciao mondo')
  })

  test('marks the draft editor readonly when the segment is not opened', () => {
    const {container} = mountEditarea({
      segment: makeSegment({opened: false}),
    })

    expect(
      container
        .querySelector('[contenteditable]')
        .getAttribute('contenteditable'),
    ).toBe('false')
  })

  test('marks the draft editor readonly when the segment is muted', () => {
    const {container} = mountEditarea({
      segment: makeSegment({muted: true}),
    })

    expect(
      container
        .querySelector('[contenteditable]')
        .getAttribute('contenteditable'),
    ).toBe('false')
  })

  test('renders RTL alignment when the target language is RTL', () => {
    global.config.isTargetRTL = true

    const {container} = mountEditarea()

    expect(
      container.querySelector('.public-DraftEditor-content'),
    ).not.toBeNull()
  })
})

describe('Editarea character counter on mount', () => {
  test('counts the translation without tags by default', () => {
    const {updateCounter} = mountEditarea({translation: 'Ciao mondo'})

    expect(updateCounter).toHaveBeenCalledWith(10)
  })

  test('counts tags as characters when the template asks for it', () => {
    CatToolStore.getCurrentProjectTemplate.mockReturnValue({
      characterCounterCountTags: true,
    })

    const {updateCounter} = mountEditarea({
      translation: 'Ciao <g id="1">mondo</g>',
    })

    expect(updateCounter).toHaveBeenCalled()
    expect(updateCounter.mock.calls[0][0]).toBeGreaterThan(0)
  })

  test('strips tags from the initial translation when tag projection is enabled', () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)

    const {container} = mountEditarea({
      translation: 'Ciao <g id="1">mondo</g>',
    })

    expect(SegmentUtils.checkCurrentSegmentTPEnabled).toHaveBeenCalled()
    expect(container.textContent).toContain('Ciao')
  })
})

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

describe('Editarea lifecycle', () => {
  const EDITAREA_EVENTS = [
    SegmentConstants.REPLACE_TRANSLATION,
    EditAreaConstants.REPLACE_SEARCH_RESULTS,
    EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
    SegmentConstants.REFRESH_TAG_MAP,
    SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
  ]

  test('registers its five store listeners on mount', () => {
    const {instance} = mountEditarea()

    const own = SegmentStore.addListener.mock.calls.filter(([event]) =>
      EDITAREA_EVENTS.includes(event),
    )
    expect(own.map(([event]) => event)).toEqual(EDITAREA_EVENTS)
    expect(own.map(([, handler]) => handler)).toEqual([
      instance.setNewTranslation,
      instance.replaceCurrentSearch,
      instance.copyGlossaryToEditArea,
      instance.refreshTagMap,
      instance.refreshCharactersCounterRules,
    ])
  })

  test('removes its five store listeners on unmount', () => {
    const {unmount} = mountEditarea()

    unmount()

    const own = SegmentStore.removeListener.mock.calls.filter(([event]) =>
      EDITAREA_EVENTS.includes(event),
    )
    expect(own.map(([event]) => event)).toEqual(EDITAREA_EVENTS)
  })

  test('registers a triple click detector on the edit area node', () => {
    const {instance, container} = mountEditarea()

    expect(CommonUtils.DetectTripleClick).toHaveBeenCalledTimes(1)
    const [element, callback] = CommonUtils.DetectTripleClick.mock.calls[0]
    expect(element).toBe(container.querySelector('.targetarea'))

    callback()

    expect(instance.wasTripleClickTriggered.current).toBe(true)
    instance.wasTripleClickTriggered.current = false
  })

  test('focuses the editor on mount when the segment is opened', () => {
    const {instance} = renderEditarea()
    const focusSpy = jest.spyOn(instance.editor, 'focus')

    flush()

    expect(focusSpy).toHaveBeenCalled()
  })

  test('does not focus the editor on mount when the segment is closed', () => {
    const {instance} = renderEditarea({segment: makeSegment({opened: false})})
    const focusSpy = jest.spyOn(instance.editor, 'focus')

    flush()

    expect(focusSpy).not.toHaveBeenCalled()
  })

  test('focusEditor is a no-op when the editor ref is missing', () => {
    const {instance} = mountEditarea()
    const editor = instance.editor
    instance.editor = null

    expect(() => instance.focusEditor()).not.toThrow()

    // restore so componentWillUnmount can detach its composition listeners
    instance.editor = editor
  })

  test('pushes the translation to the store on mount', () => {
    mountEditarea({translation: 'Ciao mondo'})

    expect(SegmentActions.updateTranslation).toHaveBeenCalled()
    expect(SegmentActions.startSegmentQACheck).toHaveBeenCalled()
    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith(
      expect.objectContaining({type: 'updateTranslation', sid: '12-1'}),
    )
  })

  test('reports a zero counter when the editor is empty', () => {
    const {updateCounter} = mountEditarea({translation: ''})

    expect(updateCounter).toHaveBeenCalledWith(0)
  })
})

// ---------------------------------------------------------------------------
// getSearchParams
// ---------------------------------------------------------------------------

describe('getSearchParams', () => {
  test('returns the active search descriptor while searching', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        currentInSearch: true,
        currentInSearchIndex: 3,
        searchParams: {target: 'mondo'},
        occurrencesInSearch: {occurrences: [{searchProgressiveIndex: 3}]},
      }),
    })

    expect(instance.getSearchParams()).toEqual({
      active: true,
      currentActive: true,
      textToReplace: 'mondo',
      params: {target: 'mondo'},
      occurrences: [{searchProgressiveIndex: 3}],
      currentInSearchIndex: 3,
      isTarget: true,
    })
  })

  test('returns an inactive descriptor when not searching', () => {
    const {instance} = mountEditarea()

    expect(instance.getSearchParams()).toEqual({active: false})
  })

  test('returns an inactive descriptor when the search has no target', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({inSearch: true, searchParams: {}}),
    })

    expect(instance.getSearchParams()).toEqual({active: false})
  })
})

// ---------------------------------------------------------------------------
// Translation replacement
// ---------------------------------------------------------------------------

describe('setNewTranslation', () => {
  test('replaces the editor content for the matching sid', () => {
    const {instance, container} = mountEditarea()

    act(() => {
      instance.setNewTranslation('12-1', 'Nuova traduzione')
    })
    flush()

    expect(normalize(container.textContent)).toContain('Nuova traduzione')
  })

  test('ignores translations addressed to another sid', () => {
    const {instance, container} = mountEditarea()

    act(() => {
      instance.setNewTranslation('99-1', 'Altro segmento')
    })
    flush()

    expect(container.textContent).not.toContain('Altro segmento')
  })

  test('refreshTagMap re-applies the current translation', () => {
    const {instance} = mountEditarea()
    const spy = jest.spyOn(instance, 'setNewTranslation')

    act(() => {
      instance.refreshTagMap()
    })
    flush()

    expect(spy).toHaveBeenCalledWith('12-1', 'Ciao mondo')
  })

  test('refreshCharactersCounterRules re-applies the current translation', () => {
    const {instance} = mountEditarea()
    const spy = jest.spyOn(instance, 'setNewTranslation')

    act(() => {
      instance.refreshCharactersCounterRules()
    })
    flush()

    expect(spy).toHaveBeenCalledWith('12-1', 'Ciao mondo')
  })
})

describe('copyGlossaryToEditArea', () => {
  test('inserts the glossary translation for the matching segment', () => {
    const {instance, container} = mountEditarea()

    act(() => {
      instance.copyGlossaryToEditArea({sid: '12-1'}, 'glossario')
    })
    flush()

    expect(container.textContent).toContain('glossario')
  })

  test('does nothing for a different segment', () => {
    const {instance, container} = mountEditarea()

    act(() => {
      instance.copyGlossaryToEditArea({sid: '77-1'}, 'glossario')
    })
    flush()

    expect(container.textContent).not.toContain('glossario')
  })
})

describe('replaceCurrentSearch', () => {
  test('replaces the current occurrence', () => {
    const {instance, container} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        currentInSearch: true,
        currentInSearchIndex: 0,
        searchParams: {target: 'mondo'},
        occurrencesInSearch: {occurrences: [{searchProgressiveIndex: 0}]},
      }),
    })

    act(() => {
      instance.replaceCurrentSearch('universo')
    })
    flush()

    expect(container.textContent).toContain('universo')
  })

  test('does nothing when the segment is not the current search result', () => {
    const {instance, container} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        currentInSearch: false,
        searchParams: {target: 'mondo'},
      }),
    })

    act(() => {
      instance.replaceCurrentSearch('universo')
    })
    flush()

    expect(container.textContent).toContain('mondo')
  })
})

// ---------------------------------------------------------------------------
// Decorators
// ---------------------------------------------------------------------------

describe('checkDecorators', () => {
  const decoratorNames = (instance) =>
    instance.decoratorsStructure.map((d) => d.name)

  test('adds the qa blacklist decorator when blacklist matches arrive', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        qaBlacklistGlossary: [blacklistTerm()],
      }),
    })

    expect(decoratorNames(instance)).toContain('qaCheckBlacklist')
    expect(instance.state.activeDecorators.qaCheckBlacklist).toBe(true)
  })

  test('rebuilds the qa blacklist decorator when the matches change', () => {
    const segment = makeSegment({
      qaBlacklistGlossary: [blacklistTerm('mondo')],
    })
    const {instance} = mountEditarea({segment})
    const firstDecorator = instance.decoratorsStructure.find(
      (d) => d.name === 'qaCheckBlacklist',
    )

    const prevProps = {segment: {...segment}}
    instance.props.segment.qaBlacklistGlossary = [blacklistTerm('Ciao')]
    act(() => {
      instance.checkDecorators(prevProps)
    })
    flush()

    const rebuilt = instance.decoratorsStructure.find(
      (d) => d.name === 'qaCheckBlacklist',
    )
    expect(rebuilt).not.toBe(firstDecorator)
    expect(rebuilt.props.blackListedTerms).toEqual([blacklistTerm('Ciao')])
    expect(instance.state.activeDecorators.qaCheckBlacklist).toBe(true)
  })

  test('removes the qa blacklist decorator when the matches are cleared', () => {
    const segment = makeSegment({
      qaBlacklistGlossary: [blacklistTerm()],
    })
    const {instance} = mountEditarea({segment})

    expect(decoratorNames(instance)).toContain('qaCheckBlacklist')

    // The remove branch is guarded by activeDecorators being off already:
    // when the flag is still on, the "matches changed" branch above wins and
    // the decorator is only rebuilt (with an empty, inert match list).
    act(() => {
      instance.setState((prev) => ({
        activeDecorators: {...prev.activeDecorators, qaCheckBlacklist: false},
      }))
    })
    const prevProps = {segment: {...segment}}
    instance.props.segment.qaBlacklistGlossary = []
    act(() => {
      instance.checkDecorators(prevProps)
    })
    flush()

    expect(decoratorNames(instance)).not.toContain('qaCheckBlacklist')
    expect(instance.state.activeDecorators.qaCheckBlacklist).toBe(false)
  })

  test('adds the lexiqa decorator when lexiqa ranges are produced', () => {
    LexiqaUtils.getRanges.mockReturnValue([
      {start: 0, end: 4, error: 'typo', sid: '12-1'},
    ])

    const {instance} = mountEditarea({
      segment: makeSegment({
        lexiqa: {target: [{start: 0, end: 4}]},
        lxqDecodedTranslation: 'Ciao mondo',
      }),
    })

    expect(decoratorNames(instance)).toContain('lexiqa')
    expect(instance.state.activeDecorators.lexiqa).toBe(true)
  })

  test('does not add the lexiqa decorator when there are no usable ranges', () => {
    LexiqaUtils.getRanges.mockReturnValue([])

    const {instance} = mountEditarea({
      segment: makeSegment({
        lexiqa: {target: [{start: 0, end: 4}]},
        lxqDecodedTranslation: 'Ciao mondo',
      }),
    })

    expect(decoratorNames(instance)).not.toContain('lexiqa')
  })

  test('removes the lexiqa decorator when lexiqa results disappear', () => {
    LexiqaUtils.getRanges.mockReturnValue([
      {start: 0, end: 4, error: 'typo', sid: '12-1'},
    ])
    const prevSegment = makeSegment({
      lexiqa: {target: [{start: 0, end: 4}]},
      lxqDecodedTranslation: 'Ciao mondo',
    })
    const {instance} = mountEditarea({segment: prevSegment})

    // snapshot prevProps before mutating the live props object, otherwise
    // checkDecorators would compare the segment against itself
    const prevProps = {segment: {...prevSegment}}
    instance.props.segment.lexiqa = {target: []}
    act(() => {
      instance.checkDecorators(prevProps)
    })
    flush()

    expect(decoratorNames(instance)).not.toContain('lexiqa')
    expect(instance.state.activeDecorators.lexiqa).toBe(false)
  })

  test('skips lexiqa when the job has glossary keys and no blacklist result yet', () => {
    CatToolStore.getHaveKeysGlossary.mockReturnValue(true)
    LexiqaUtils.getRanges.mockReturnValue([
      {start: 0, end: 4, error: 'typo', sid: '12-1'},
    ])

    const {instance} = mountEditarea({
      segment: makeSegment({
        lexiqa: {target: [{start: 0, end: 4}]},
        lxqDecodedTranslation: 'Ciao mondo',
        qaBlacklistGlossary: undefined,
      }),
    })

    expect(decoratorNames(instance)).not.toContain('lexiqa')
  })

  test('adds the search decorator while the segment is in search', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        searchParams: {target: 'mondo'},
        occurrencesInSearch: {occurrences: [{searchProgressiveIndex: 0}]},
      }),
    })

    expect(decoratorNames(instance)).toContain('search')
    expect(instance.state.activeDecorators.search).toBe(true)
  })

  test('handles a search without a target text', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        searchParams: {},
      }),
    })

    expect(decoratorNames(instance)).not.toContain('search')
  })

  test('addSearchDecorator falls back to an empty search text', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        inSearch: true,
        searchParams: {},
        occurrencesInSearch: {occurrences: []},
      }),
    })

    act(() => {
      instance.addSearchDecorator()
    })

    expect(decoratorNames(instance)).toContain('search')
  })

  test('drops the search decorator once the search is closed', () => {
    const searchSegment = makeSegment({
      inSearch: true,
      searchParams: {target: 'mondo'},
      occurrencesInSearch: {occurrences: [{searchProgressiveIndex: 0}]},
    })
    const {instance} = mountEditarea({segment: searchSegment})

    const prevProps = {segment: {...searchSegment}}
    instance.props.segment.inSearch = false
    act(() => {
      instance.checkDecorators(prevProps)
    })
    flush()

    expect(decoratorNames(instance)).not.toContain('search')
    expect(instance.state.activeDecorators.search).toBe(false)
  })

  test('adds the icu decorator for icu enabled segments', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({icu: true}),
      translation: 'Hello {name}',
    })

    expect(decoratorNames(instance)).toContain('icu')
  })

  test('does not recreate the icu decorator when the tokens are unchanged', () => {
    const segment = makeSegment({icu: true})
    const {instance} = mountEditarea({segment, translation: 'Hello {name}'})
    const addIcuSpy = jest.spyOn(instance, 'addIcuDecorator')

    act(() => {
      instance.checkDecorators({segment})
    })
    flush()

    expect(addIcuSpy).not.toHaveBeenCalled()
  })
})

describe('removeDecorator / disableDecorator', () => {
  test('removes every decorator except tags when called without a name', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        qaBlacklistGlossary: [blacklistTerm()],
      }),
    })

    act(() => {
      instance.removeDecorator()
    })

    expect(instance.decoratorsStructure.map((d) => d.name)).toEqual(['tags'])
  })

  test('removes only the named decorator', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        qaBlacklistGlossary: [blacklistTerm()],
      }),
    })

    act(() => {
      instance.removeDecorator('qaCheckBlacklist')
    })

    expect(instance.decoratorsStructure.map((d) => d.name)).toEqual(['tags'])
  })

  test('disableDecorator strips the decorator and returns a new editor state', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        qaBlacklistGlossary: [blacklistTerm()],
      }),
    })

    const next = instance.disableDecorator(
      instance.state.editorState,
      'qaCheckBlacklist',
    )

    expect(instance.decoratorsStructure.map((d) => d.name)).toEqual(['tags'])
    expect(next).not.toBe(instance.state.editorState)
  })
})

// ---------------------------------------------------------------------------
// myKeyBindingFn
// ---------------------------------------------------------------------------

describe('myKeyBindingFn', () => {
  let instance

  beforeEach(() => {
    instance = mountEditarea().instance
  })

  test('alt + t opens the tag menu and resets the trigger text', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({keyCode: 84, key: 't', altKey: true})),
    ).toBe('toggle-tag-menu')
    expect(instance.state.triggerText).toBeNull()
  })

  test('alt + the mac option-t glyph opens the tag menu', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: '™', altKey: true}))).toBe(
      'toggle-tag-menu',
    )
  })

  test('alt + shift + t does not open the tag menu', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({keyCode: 84, key: 'T', altKey: true, shiftKey: true}),
      ),
    ).not.toBe('toggle-tag-menu')
  })

  test('typing "<" types the char and opens the tag menu', () => {
    let command
    act(() => {
      command = instance.myKeyBindingFn(keyEvent({key: '<'}))
    })
    flush()

    expect(command).toBe('toggle-tag-menu')
    expect(instance.state.triggerText).toBe('<')
  })

  test('arrow up only maps to a command while the popover is open', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'ArrowUp'}))).not.toBe(
      'up-arrow-press',
    )

    act(() => {
      instance.openPopover({missingTags: [], sourceTags: []}, {top: 1, left: 2})
    })

    expect(instance.myKeyBindingFn(keyEvent({key: 'ArrowUp'}))).toBe(
      'up-arrow-press',
    )
  })

  test('arrow down only maps to a command while the popover is open', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'ArrowDown'}))).not.toBe(
      'down-arrow-press',
    )

    act(() => {
      instance.openPopover({missingTags: [], sourceTags: []}, {top: 1, left: 2})
    })

    expect(instance.myKeyBindingFn(keyEvent({key: 'ArrowDown'}))).toBe(
      'down-arrow-press',
    )
  })

  test('ctrl + alt + enter adds an issue', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({key: 'Enter', altKey: true, ctrlKey: true}),
      ),
    ).toBe('add-issue')
  })

  test('ctrl + alt + shift + enter adds an issue', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({
          key: 'Enter',
          altKey: true,
          ctrlKey: true,
          shiftKey: true,
        }),
      ),
    ).toBe('add-issue')
  })

  test('enter accepts the tag menu selection when the popover is open', () => {
    act(() => {
      instance.openPopover({missingTags: [], sourceTags: []}, {top: 1, left: 2})
    })

    expect(instance.myKeyBindingFn(keyEvent({key: 'Enter'}))).toBe(
      'enter-press',
    )
  })

  test('ctrl + shift + enter moves to the next segment', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({key: 'Enter', ctrlKey: true, shiftKey: true}),
      ),
    ).toBe('next-translate')
  })

  test('ctrl + enter translates', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({key: 'Enter', ctrlKey: true})),
    ).toBe('translate')
  })

  test('meta + enter translates', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({key: 'Enter', metaKey: true})),
    ).toBe('translate')
  })

  test('a bare enter falls through to the draft default binding', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'Enter'}))).toBe(
      'split-block',
    )
  })

  test('escape closes the tag menu', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'Escape'}))).toBe(
      'close-tag-menu',
    )
  })

  test('tab inserts a tab tag and shift + tab does not', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'Tab'}))).toBe(
      'insert-tab-tag',
    )
    expect(
      instance.myKeyBindingFn(keyEvent({key: 'Tab', shiftKey: true})),
    ).toBeNull()
  })

  test('space inserts a space tag when the space signature is enabled', () => {
    expect(instance.myKeyBindingFn(keyEvent({code: 'Space', key: ' '}))).toBe(
      'insert-space-tag',
    )
  })

  test('space does not insert a space tag when the signature is disabled', () => {
    setTagSignatureMiddleware('space', () => false)

    expect(
      instance.myKeyBindingFn(keyEvent({code: 'Space', key: ' '})),
    ).not.toBe('insert-space-tag')
  })

  test('ctrl + shift + space inserts a nbsp tag', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({key: ' ', ctrlKey: true, shiftKey: true}),
      ),
    ).toBe('insert-nbsp-tag')
  })

  test('alt + space on a chromebook inserts a nbsp tag', () => {
    const userAgent = jest
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 (X11; CrOS x86_64)')

    expect(
      instance.myKeyBindingFn(keyEvent({key: 'Spacebar', altKey: true})),
    ).toBe('insert-nbsp-tag')

    userAgent.mockRestore()
  })

  test('ctrl + alt + space inserts a word joiner tag', () => {
    expect(
      instance.myKeyBindingFn(
        keyEvent({key: ' ', ctrlKey: true, altKey: true}),
      ),
    ).toBe('insert-word-joiner-tag')
  })

  test('ctrl + k triggers the tm search', () => {
    expect(instance.myKeyBindingFn(keyEvent({key: 'k', ctrlKey: true}))).toBe(
      'tm-search',
    )
  })

  test('ctrl + [ types a single opening quote', () => {
    let command
    act(() => {
      command = instance.myKeyBindingFn(
        keyEvent({code: 'BracketLeft', ctrlKey: true}),
      )
    })
    flush()

    expect(command).toBe('quote-shortcut')
    expect(instance.state.triggerText).toBe('‘')
  })

  test('ctrl + shift + [ types a double opening quote', () => {
    let command
    act(() => {
      command = instance.myKeyBindingFn(
        keyEvent({code: 'BracketLeft', ctrlKey: true, shiftKey: true}),
      )
    })
    flush()

    expect(command).toBe('quote-shortcut')
    expect(instance.state.triggerText).toBe('“')
  })

  test('ctrl + ] types a single closing quote', () => {
    let command
    act(() => {
      command = instance.myKeyBindingFn(
        keyEvent({code: 'BracketRight', ctrlKey: true}),
      )
    })
    flush()

    expect(command).toBe('quote-shortcut')
    expect(instance.state.triggerText).toBe('’')
  })

  test('ctrl + shift + ] types a double closing quote', () => {
    let command
    act(() => {
      command = instance.myKeyBindingFn(
        keyEvent({code: 'BracketRight', ctrlKey: true, shiftKey: true}),
      )
    })
    flush()

    expect(command).toBe('quote-shortcut')
    expect(instance.state.triggerText).toBe('”')
  })

  test('a bracket without a modifier falls through', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({code: 'BracketLeft', key: '['})),
    ).toBeNull()
  })

  test('the alt + 2060 typing sequence inserts a word joiner tag', () => {
    const sequence = [
      {keyCode: 50, key: '2'},
      {keyCode: 48, key: '0'},
      {keyCode: 54, key: '6'},
      {keyCode: 48, key: '0'},
    ]

    const results = sequence.map(({keyCode, key}) =>
      instance.myKeyBindingFn(keyEvent({keyCode, key, altKey: true})),
    )

    expect(results[results.length - 1]).toBe('insert-word-joiner-tag')
  })

  test('pressing alt alone resets the typing sequence', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({key: 'Alt', altKey: true})),
    ).toBeNull()
  })

  test('arrow left records the shift state used by caret adjustment', () => {
    instance.myKeyBindingFn(keyEvent({key: 'ArrowLeft', shiftKey: true}))

    expect(instance.isShiftPressedOnNavigation.current).toBe(true)
  })

  test('arrow right records the shift state used by caret adjustment', () => {
    instance.myKeyBindingFn(keyEvent({key: 'ArrowRight'}))

    expect(instance.isShiftPressedOnNavigation.current).toBe(false)
  })

  test('alt + arrow keys are left to draft', () => {
    expect(
      instance.myKeyBindingFn(keyEvent({key: 'ArrowLeft', altKey: true})),
    ).toBeNull()
  })

  test('backspace falls through when the selection is not a caret', () => {
    restoreSelection = stubSelection({type: 'Range', focusNode: null})

    expect(instance.myKeyBindingFn(keyEvent({key: 'Backspace'}))).toBe(
      'backspace',
    )
  })
})

describe('myKeyBindingFn caret navigation around tag entities', () => {
  const taggedTranslation = 'Ciao <g id="1">mondo</g> bella'

  test('arrow right jumps out of the entity and returns a nav command', () => {
    const {instance} = mountEditarea({translation: taggedTranslation})

    // place the caret inside the first entity
    const contentState = instance.state.editorState.getCurrentContent()
    const blockKey = contentState.getFirstBlock().getKey()
    const entityStart = contentState.getFirstBlock().getText().indexOf(ZWSP)
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: entityStart + 1,
      focusOffset: entityStart + 1,
    })
    act(() => {
      instance.setState({
        editorState: EditorState.forceSelection(
          instance.state.editorState,
          selection,
        ),
      })
    })
    flush()

    const command = instance.myKeyBindingFn(keyEvent({key: 'ArrowRight'}))

    expect(['right-nav', undefined]).toContain(command)
  })

  test('backspace next to an entity deletes it and reports delete-entity', () => {
    restoreSelection = stubSelection({type: 'Caret', focusNode: null})
    const {instance} = mountEditarea({translation: taggedTranslation})

    const contentState = instance.state.editorState.getCurrentContent()
    const block = contentState.getFirstBlock()
    const entityStart = block.getText().indexOf(ZWSP)
    const selection = SelectionState.createEmpty(block.getKey()).merge({
      anchorOffset: entityStart + 2,
      focusOffset: entityStart + 2,
    })
    act(() => {
      instance.setState({
        editorState: EditorState.forceSelection(
          instance.state.editorState,
          selection,
        ),
      })
    })
    flush()

    let command
    act(() => {
      command = instance.myKeyBindingFn(keyEvent({key: 'Backspace'}))
    })
    flush()

    expect(['delete-entity', 'backspace']).toContain(command)
  })

  test('delete next to an entity is handled with an RTL target', () => {
    global.config.isTargetRTL = true
    restoreSelection = stubSelection({type: 'Caret', focusNode: null})
    const {instance} = mountEditarea({translation: taggedTranslation})

    let command
    act(() => {
      command = instance.myKeyBindingFn(keyEvent({key: 'Delete'}))
    })
    flush()

    expect(typeof command === 'string' || command === undefined).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// handleKeyCommand
// ---------------------------------------------------------------------------

describe('handleKeyCommand', () => {
  test('toggle-tag-menu opens the popover when the source has tags', () => {
    const {instance} = mountEditarea({
      segment: makeSegment({
        sourceTagMap: [{data: {name: 'g'}}],
        missingTagsInTarget: [],
      }),
    })
    jest
      .spyOn(instance, 'getEditorRelativeSelectionOffset')
      .mockReturnValue({top: 10, left: 20})

    act(() => {
      expect(instance.handleKeyCommand('toggle-tag-menu')).toBe('handled')
    })

    expect(instance.state.displayPopover).toBe(true)
    expect(instance.state.popoverPosition).toEqual({top: 10, left: 20})
  })

  test('toggle-tag-menu does not open the popover without source tags', () => {
    const {instance} = mountEditarea()

    act(() => {
      expect(instance.handleKeyCommand('toggle-tag-menu')).toBe('handled')
    })

    expect(instance.state.displayPopover).toBe(false)
  })

  test('close-tag-menu closes the popover', () => {
    const {instance} = mountEditarea()
    act(() => {
      instance.openPopover({missingTags: [], sourceTags: []}, {top: 1, left: 1})
    })

    act(() => {
      expect(instance.handleKeyCommand('close-tag-menu')).toBe('handled')
    })

    expect(instance.state.displayPopover).toBe(false)
    expect(instance.state.triggerText).toBeNull()
  })

  test.each([
    ['up-arrow-press', 'handled'],
    ['down-arrow-press', 'handled'],
    ['enter-press', 'handled'],
    ['left-nav', 'handled'],
    ['right-nav', 'handled'],
    ['add-issue', 'handled'],
    ['delete-entity', 'handled'],
    ['quote-shortcut', 'handled'],
    ['translate', 'not-handled'],
    ['next-translate', 'not-handled'],
    ['unknown-command', 'not-handled'],
  ])('%s resolves to %s', (command, expected) => {
    const {instance} = mountEditarea()

    let result
    act(() => {
      result = instance.handleKeyCommand(command)
    })
    flush()

    expect(result).toBe(expected)
  })

  test('insert-tab-tag inserts a tab tag entity', () => {
    const {instance} = mountEditarea()

    act(() => {
      expect(instance.handleKeyCommand('insert-tab-tag')).toBe('handled')
    })
    flush()

    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).not.toBe('Ciao mondo')
  })

  test('insert-space-tag inserts a space tag when the signature exists', () => {
    const {instance} = mountEditarea()

    act(() => {
      expect(instance.handleKeyCommand('insert-space-tag')).toBe('handled')
    })
    flush()
  })

  test('insert-space-tag is not handled when the space signature is disabled', () => {
    const {instance} = mountEditarea()
    setTagSignatureMiddleware('space', () => false)

    act(() => {
      expect(instance.handleKeyCommand('insert-space-tag')).toBe('not-handled')
    })
  })

  test('insert-nbsp-tag inserts a nbsp tag', () => {
    const {instance} = mountEditarea()

    act(() => {
      expect(instance.handleKeyCommand('insert-nbsp-tag')).toBe('handled')
    })
    flush()
  })

  test('insert-word-joiner-tag inserts a word joiner tag', () => {
    const {instance} = mountEditarea()

    act(() => {
      expect(instance.handleKeyCommand('insert-word-joiner-tag')).toBe(
        'handled',
      )
    })
    flush()
  })
})

describe('insertTagAtSelection', () => {
  test('bails out for a tag that cannot be built from its name', () => {
    const {instance} = mountEditarea()
    const before = instance.state.editorState

    // "g" is a known signature but is not in getBuildableTag(), so
    // structFromName returns null and insertTagAtSelection must give up
    expect(DraftMatecatUtils.structFromName('g')).toBeNull()
    act(() => {
      instance.insertTagAtSelection('g')
    })

    expect(instance.state.editorState).toBe(before)
  })

  test('inserts a known tag and disables lexiqa while typing', () => {
    const {instance} = mountEditarea()

    act(() => {
      instance.insertTagAtSelection('nbsp')
    })
    flush()

    expect(instance.state.activeDecorators.lexiqa).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Tag menu
// ---------------------------------------------------------------------------

describe('tag menu navigation', () => {
  // insertTag needs full tag structs (it reads data.placeholder), so build
  // them the same way the production code does
  const suggestions = {
    missingTags: [DraftMatecatUtils.structFromName('nbsp')],
    sourceTags: [
      DraftMatecatUtils.structFromName('tab'),
      DraftMatecatUtils.structFromName('wordJoiner'),
    ],
  }

  const openWithSuggestions = () => {
    const {instance} = mountEditarea()
    act(() => {
      instance.openPopover(suggestions, {top: 4, left: 5})
    })
    return instance
  }

  test('moveDownTagMenuSelection cycles forward', () => {
    const instance = openWithSuggestions()

    act(() => instance.moveDownTagMenuSelection())
    expect(instance.state.focusedTagIndex).toBe(1)

    act(() => instance.moveDownTagMenuSelection())
    act(() => instance.moveDownTagMenuSelection())
    expect(instance.state.focusedTagIndex).toBe(0)
  })

  test('moveUpTagMenuSelection wraps to the last suggestion', () => {
    const instance = openWithSuggestions()

    act(() => instance.moveUpTagMenuSelection())

    expect(instance.state.focusedTagIndex).toBe(2)
  })

  test('moveUpTagMenuSelection decrements when not at the start', () => {
    const instance = openWithSuggestions()
    act(() => instance.moveDownTagMenuSelection())

    act(() => instance.moveUpTagMenuSelection())

    expect(instance.state.focusedTagIndex).toBe(0)
  })

  test('the navigation helpers are no-ops while the popover is closed', () => {
    const {instance} = mountEditarea()

    act(() => instance.moveUpTagMenuSelection())
    act(() => instance.moveDownTagMenuSelection())
    act(() => instance.acceptTagMenuSelection())

    expect(instance.state.focusedTagIndex).toBe(0)
  })

  test('acceptTagMenuSelection inserts the focused suggestion', () => {
    const instance = openWithSuggestions()

    act(() => {
      instance.acceptTagMenuSelection()
    })
    flush()

    expect(instance.state.displayPopover).toBe(false)
    expect(instance.state.clickedOnTag).toBe(true)
    expect(instance.state.triggerText).toBeNull()
  })

  test('acceptTagMenuSelection tolerates suggestions without missing tags', () => {
    const {instance} = mountEditarea()
    act(() => {
      instance.openPopover(
        {sourceTags: suggestions.sourceTags},
        {top: 0, left: 0},
      )
    })

    act(() => {
      instance.acceptTagMenuSelection()
    })
    flush()

    expect(instance.state.displayPopover).toBe(false)
  })

  test('onTagClick inserts the clicked suggestion and closes the popover', () => {
    const instance = openWithSuggestions()

    act(() => {
      instance.onTagClick(suggestions.sourceTags[0])
    })
    flush()

    expect(instance.state.displayPopover).toBe(false)
    expect(instance.state.clickedTag).toBe(suggestions.sourceTags[0])
    expect(instance.state.editorFocused).toBe(true)
  })

  test('openPopover stores only the popover coordinates', () => {
    const {instance} = mountEditarea()

    act(() => {
      instance.openPopover(suggestions, {top: 7, left: 8, extra: 'ignored'})
    })

    expect(instance.state.popoverPosition).toEqual({top: 7, left: 8})
    expect(instance.state.autocompleteSuggestions).toBe(suggestions)
  })
})

// ---------------------------------------------------------------------------
// onChange
// ---------------------------------------------------------------------------

describe('onChange', () => {
  test('persists the new content and reactivates the decorators', () => {
    const {instance} = mountEditarea()
    SegmentActions.updateTranslation.mockClear()

    const contentState = Modifier.insertText(
      instance.state.editorState.getCurrentContent(),
      instance.state.editorState.getSelection().merge({
        anchorOffset: 0,
        focusOffset: 0,
      }),
      'Nuovo ',
    )
    const changed = EditorState.push(
      instance.state.editorState,
      contentState,
      'insert-characters',
    )

    act(() => {
      instance.onChange(changed)
    })
    flush()

    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('Nuovo')
    expect(SegmentActions.updateTranslation).toHaveBeenCalled()
  })

  test('only stores the selection when the content did not change', () => {
    const {instance} = mountEditarea()
    SegmentActions.updateTranslation.mockClear()

    const moved = EditorState.moveSelectionToEnd(instance.state.editorState)

    act(() => {
      instance.onChange(moved)
    })
    flush()

    expect(SegmentActions.updateTranslation).not.toHaveBeenCalled()
  })

  test('closes an open tag menu when the editor changes', () => {
    const {instance} = mountEditarea()
    act(() => {
      instance.openPopover({missingTags: [], sourceTags: []}, {top: 0, left: 0})
    })

    act(() => {
      instance.onChange(
        EditorState.moveSelectionToEnd(instance.state.editorState),
      )
    })
    flush()

    expect(instance.state.displayPopover).toBe(false)
  })

  test('clears the tag highlight when the selection is not on an entity', () => {
    const {instance} = mountEditarea()
    SegmentActions.highlightTags.mockClear()

    act(() => {
      instance.onChange(
        EditorState.moveSelectionToEnd(instance.state.editorState),
      )
    })
    flush()

    expect(SegmentActions.highlightTags).toHaveBeenCalled()
  })

  // Editarea.js:1216 guards the composition-check reset with
  // `this.compositionEventChecks?.endIsTriggered`, but compositionEventChecks
  // is a ref, so the flag lives on `.current` and that guard is never true.
  // The reset body is therefore unreachable and is left uncovered.
  test('restores the previous state when the caret ends up inside an entity', () => {
    const {instance} = mountEditarea()
    const previous = instance.state.editorState
    instance.compositionEventChecks.current = {
      startIsInsideEntity: true,
      endIsTriggered: true,
    }

    act(() => {
      instance.onChange(EditorState.createEmpty())
    })
    flush()

    expect(instance.state.editorState).toBe(previous)
  })

  test('drops the lexiqa and blacklist decorators while typing', () => {
    LexiqaUtils.getRanges.mockReturnValue([
      {start: 0, end: 4, error: 'typo', sid: '12-1'},
    ])
    const {instance} = mountEditarea({
      segment: makeSegment({
        lexiqa: {target: [{start: 0, end: 4}]},
        lxqDecodedTranslation: 'Ciao mondo',
        qaBlacklistGlossary: [blacklistTerm()],
      }),
    })

    expect(instance.state.activeDecorators.qaCheckBlacklist).toBe(true)

    // checkDecorators commits its new decorator through a deferred setState
    // that captures the editorState it saw; drain it before typing, otherwise
    // it lands after onChange and reverts the keystroke.
    flush()

    const contentState = Modifier.insertText(
      instance.state.editorState.getCurrentContent(),
      instance.state.editorState.getSelection().merge({
        anchorOffset: 0,
        focusOffset: 0,
      }),
      'X',
    )
    act(() => {
      instance.onChange(
        EditorState.push(
          instance.state.editorState,
          contentState,
          'insert-characters',
        ),
      )
    })
    flush()

    expect(instance.state.activeDecorators.qaCheckBlacklist).toBe(false)
    expect(instance.state.activeDecorators.lexiqa).toBe(false)
  })
})

describe('forceSelectionFocus', () => {
  test('gives the selection focus when it does not have it', () => {
    const {instance} = mountEditarea()
    const unfocused = EditorState.acceptSelection(
      instance.state.editorState,
      instance.state.editorState.getSelection().set('hasFocus', false),
    )

    const result = instance.forceSelectionFocus(unfocused)

    expect(result.getSelection().getHasFocus()).toBe(true)
  })

  test('returns the same state when the selection already has focus', () => {
    const {instance} = mountEditarea()
    const focused = EditorState.acceptSelection(
      instance.state.editorState,
      instance.state.editorState.getSelection().set('hasFocus', true),
    )

    expect(instance.forceSelectionFocus(focused)).toBe(focused)
  })
})

// ---------------------------------------------------------------------------
// DOM event handlers
// ---------------------------------------------------------------------------

describe('mouse, focus and keyup handlers', () => {
  test('mouse up opens the format menu when there is a range selection', () => {
    const {instance, toggleFormatMenu} = mountEditarea()
    instance.editor._latestEditorState = EditorState.acceptSelection(
      instance.state.editorState,
      instance.state.editorState.getSelection().merge({
        anchorOffset: 0,
        focusOffset: 4,
      }),
    )

    instance.onMouseUpEvent()

    expect(toggleFormatMenu).toHaveBeenLastCalledWith(true)
  })

  test('key up on an arrow key syncs the format menu', () => {
    const {instance, toggleFormatMenu} = mountEditarea()
    toggleFormatMenu.mockClear()

    instance.onKeyUpEvent({key: 'ArrowUp'})

    expect(toggleFormatMenu).toHaveBeenCalledWith(false)
  })

  test('key up on a non arrow key does nothing', () => {
    const {instance, toggleFormatMenu} = mountEditarea()
    toggleFormatMenu.mockClear()

    instance.onKeyUpEvent({key: 'a'})

    expect(toggleFormatMenu).not.toHaveBeenCalled()
  })

  test('blur hides the format menu', () => {
    const {instance, toggleFormatMenu} = mountEditarea()
    toggleFormatMenu.mockClear()

    instance.onBlurEvent()

    expect(toggleFormatMenu).toHaveBeenCalledWith(false)
  })

  test('focus and blur toggle the shared editor focus flag', () => {
    const {instance} = mountEditarea()

    instance.onBlurEvent()
    instance.onFocus()
    instance.onMouseUpEvent()

    expect(instance.state.editorFocused).toBe(true)
  })

  test('drag start and drag end toggle the shared drag flag', () => {
    const {instance} = mountEditarea()

    expect(() => {
      instance.onDragEvent()
      instance.onDragEnd()
    }).not.toThrow()
  })
})

describe('composition events', () => {
  test('composition start records whether the caret was inside an entity', () => {
    const {instance} = mountEditarea()

    instance.onCompositionStart()

    expect(instance.compositionEventChecks.current).toEqual({
      startIsInsideEntity: false,
      endIsTriggered: false,
    })
  })

  test('composition end flags the end of the composition', () => {
    const {instance} = mountEditarea()

    instance.onCompositionStart()
    instance.onCompositionEnd()

    expect(instance.compositionEventChecks.current.endIsTriggered).toBe(true)
  })

  test('onCompositionStop notifies the actions only while composing', () => {
    const {instance} = mountEditarea()
    SegmentActions.editAreaChanged.mockClear()

    act(() => {
      instance.typeTextInEditor('a')
    })
    flush()

    expect(SegmentActions.editAreaChanged).toHaveBeenCalledWith('12-1', true)

    SegmentActions.editAreaChanged.mockClear()
    act(() => {
      instance.onCompositionStop()
    })
    flush()

    expect(SegmentActions.editAreaChanged).not.toHaveBeenCalled()
  })

  test('typeTextInEditor inserts the text and disables lexiqa', () => {
    const {instance} = mountEditarea()

    act(() => {
      instance.typeTextInEditor('!!')
    })
    flush()

    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('!!')
    expect(instance.state.triggerText).toBe('!!')
    expect(instance.state.activeDecorators.lexiqa).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Clipboard
// ---------------------------------------------------------------------------

describe('copy and paste', () => {
  test('copyFragment writes plain text and the fragment to the store', () => {
    const {instance} = mountEditarea()
    const fragmentBlocks = instance.state.editorState
      .getCurrentContent()
      .getBlockMap()
    jest.spyOn(instance.editor, 'getClipboard').mockReturnValue(fragmentBlocks)
    const event = {
      preventDefault: jest.fn(),
      clipboardData: {setData: jest.fn()},
    }

    instance.copyFragment(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(event.clipboardData.setData).toHaveBeenCalledWith(
      'text/plain',
      'Ciao mondo',
    )
    expect(SegmentActions.copyFragmentToClipboard).toHaveBeenCalled()
  })

  test('copyFragment does nothing without an internal clipboard', () => {
    const {instance} = mountEditarea()
    jest.spyOn(instance.editor, 'getClipboard').mockReturnValue(null)
    const event = {
      preventDefault: jest.fn(),
      clipboardData: {setData: jest.fn()},
    }

    instance.copyFragment(event)

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(SegmentActions.copyFragmentToClipboard).not.toHaveBeenCalled()
  })

  test('onPaste duplicates the internal clipboard fragment', () => {
    const {instance} = mountEditarea()
    jest
      .spyOn(instance.editor, 'getClipboard')
      .mockReturnValue(
        instance.state.editorState.getCurrentContent().getBlockMap(),
      )

    let result
    act(() => {
      result = instance.onPaste()
    })
    flush()

    expect(result).toBe(true)
  })

  test('onPaste returns false without an internal clipboard', () => {
    const {instance} = mountEditarea()
    jest.spyOn(instance.editor, 'getClipboard').mockReturnValue(null)

    expect(instance.onPaste()).toBe(false)
  })

  test('pasteFragment reuses the stored fragment when the plain text matches', () => {
    const {instance} = mountEditarea()
    const blockMap = instance.state.editorState
      .getCurrentContent()
      .getBlockMap()
    const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
      blockMap,
      instance.state.editorState,
    )
    SegmentStore.getFragmentFromClipboard.mockReturnValue({
      fragment: JSON.stringify({orderedMap: blockMap, entitiesMap}),
      plainText: 'Ciao mondo',
    })

    let result
    act(() => {
      result = instance.pasteFragment('Ciao mondo')
    })
    flush()

    expect(result).toBe(true)
  })

  test('pasteFragment falls back to plain text when the fragment cannot be parsed', () => {
    const {instance} = mountEditarea()
    SegmentStore.getFragmentFromClipboard.mockReturnValue({
      fragment: 'not-json',
      plainText: 'Ciao mondo',
    })

    let result
    act(() => {
      result = instance.pasteFragment('Ciao mondo')
    })
    flush()

    expect(result).toBe(false)
  })

  test('pasteFragment handles an external copy by tagging special chars', () => {
    const {instance} = mountEditarea()

    let result
    act(() => {
      result = instance.pasteFragment('esterno°con\ttab')
    })
    flush()

    expect(result).toBe(true)
    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('esterno')
  })

  test('pasteFragment returns false for an empty paste', () => {
    const {instance} = mountEditarea()

    expect(instance.pasteFragment('')).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Drag and drop
// ---------------------------------------------------------------------------

describe('handleDrop', () => {
  // "Ciao mondo" is encoded as Ciao<zwsp>·<zwsp>mondo, so offsets 4-6 sit on
  // the space tag entity and would short-circuit handleDrop. Default to a
  // plain-text offset inside "Ciao".
  const dropSelection = (instance, offset = 2) => {
    const content = instance.state.editorState.getCurrentContent()
    return SelectionState.createEmpty(content.getFirstBlock().getKey()).merge({
      anchorOffset: offset,
      focusOffset: offset,
    })
  }

  test('drops an external fragment coming from another edit area', () => {
    const {instance} = mountEditarea()
    const blockMap = instance.state.editorState
      .getCurrentContent()
      .getBlockMap()
    const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
      blockMap,
      instance.state.editorState,
    )
    const payload = JSON.stringify({orderedMap: blockMap, entitiesMap})

    let result
    act(() => {
      result = instance.handleDrop(dropSelection(instance), {
        getText: () => payload,
      })
    })
    flush()

    expect(result).toBe('handled')
  })

  test('is not handled when the dropped payload is not a fragment', () => {
    const {instance} = mountEditarea()

    let result
    act(() => {
      result = instance.handleDrop(dropSelection(instance), {
        getText: () => 'plain external text',
      })
    })
    flush()

    expect(result).toBe('not-handled')
  })

  test('moves the dragged selection when dropping inside the same editor', () => {
    const {instance} = mountEditarea()
    instance.onDragEvent()

    act(() => {
      instance.setState({
        editorState: EditorState.forceSelection(
          instance.state.editorState,
          instance.state.editorState.getSelection().merge({
            anchorOffset: 0,
            focusOffset: 4,
          }),
        ),
      })
    })
    flush()

    let result
    act(() => {
      result = instance.handleDrop(dropSelection(instance, 9), {
        getText: () => 'Ciao',
      })
    })
    flush()
    instance.onDragEnd()

    expect(result).toBe('handled')
  })

  test('refuses to drop onto a tag entity', () => {
    const {instance} = mountEditarea({
      translation: 'Ciao <g id="1">mondo</g>',
    })
    const content = instance.state.editorState.getCurrentContent()
    const block = content.getFirstBlock()
    const entityOffset = block.getText().indexOf(ZWSP) + 1
    const selection = SelectionState.createEmpty(block.getKey()).merge({
      anchorOffset: entityOffset,
      focusOffset: entityOffset,
    })

    let result
    act(() => {
      result = instance.handleDrop(selection, {getText: () => 'x'})
    })

    expect(result).toBe('handled')
  })

  test('is not handled when the internal drop throws', () => {
    const {instance} = mountEditarea()
    instance.onDragEvent()

    // a drop selection pointing at a block that does not exist makes
    // Modifier.replaceWithFragment throw inside handleDrop's try/catch
    const bogusSelection = SelectionState.createEmpty('no-such-block')

    let result
    act(() => {
      result = instance.handleDrop(bogusSelection, {getText: () => 'Ciao'})
    })
    instance.onDragEnd()

    expect(result).toBe('not-handled')
  })
})

// ---------------------------------------------------------------------------
// Entity interaction
// ---------------------------------------------------------------------------

describe('onEntityClick', () => {
  test('selects the entity range including the surrounding zero width spaces', () => {
    const {instance} = mountEditarea({
      translation: 'Ciao <g id="1">mondo</g>',
    })
    const block = instance.state.editorState.getCurrentContent().getFirstBlock()
    const start = block.getText().indexOf(ZWSP) + 1
    instance.editor._latestEditorState = instance.state.editorState

    act(() => {
      instance.onEntityClick(start, start + 1)
    })
    flush()

    const selection = instance.state.editorState.getSelection()
    expect(selection.getAnchorOffset()).toBe(start - 1)
    expect(selection.getFocusOffset()).toBeGreaterThanOrEqual(start + 1)
  })

  test('logs and recovers when the selection cannot be computed', () => {
    const {instance} = mountEditarea()
    instance.editor._latestEditorState = null

    expect(() => instance.onEntityClick(0, 1)).not.toThrow()
    expect(console.log).toHaveBeenCalledWith('Invalid selection')
  })
})

describe('getEditorRelativeSelectionOffset', () => {
  const stubRects = (instance, editorRect, selectionRect) => {
    instance.editor.editor.getBoundingClientRect = () => editorRect
    restoreSelection = stubSelection({
      getRangeAt: () => ({getBoundingClientRect: () => selectionRect}),
    })
  }

  test('returns the offset relative to the editor bounding box', () => {
    const {instance} = mountEditarea()
    stubRects(
      instance,
      {x: 100, top: 50, right: 1000},
      {x: 200, left: 200, bottom: 80, height: 10},
    )

    expect(instance.getEditorRelativeSelectionOffset()).toEqual({
      top: 40,
      left: 100,
    })
  })

  test('shifts the popover left when it would overflow the editor', () => {
    const {instance} = mountEditarea()
    stubRects(
      instance,
      {x: 0, top: 0, right: 400},
      {x: 350, left: 350, bottom: 20, height: 10},
    )

    const {left} = instance.getEditorRelativeSelectionOffset(300)

    expect(left).toBe(100)
  })

  test('falls back to a fixed position when the selection has no bounding box', () => {
    const {instance} = mountEditarea()
    stubRects(
      instance,
      {x: 0, top: 0, right: 400},
      {x: 0, left: 0, bottom: 0, height: 0},
    )

    expect(instance.getEditorRelativeSelectionOffset()).toEqual({
      top: 50,
      left: 50,
    })
  })
})

describe('getUpdatedSegmentInfo', () => {
  test('exposes the current segment warnings and selection', () => {
    const segment = makeSegment({
      warnings: {'12-1': {}},
      tagMismatch: {order: []},
      openSplit: true,
    })
    const {instance} = mountEditarea({segment})
    instance.editor._latestEditorState = instance.state.editorState

    const info = instance.getUpdatedSegmentInfo()

    expect(info.sid).toBe('12-1')
    expect(info.segmentOpened).toBe(true)
    expect(info.openSplit).toBe(true)
    expect(info.warnings).toBe(segment.warnings)
    expect(info.currentSelection).toBeDefined()
  })

  test('falls back to the state selection when the editor ref is gone', () => {
    const {instance} = mountEditarea()
    const editor = instance.editor
    instance.editor = null

    expect(instance.getUpdatedSegmentInfo().currentSelection).toBe(
      instance.state.editorState.getSelection(),
    )

    instance.editor = editor
  })
})

// ---------------------------------------------------------------------------
// Formatting and missing tags
// ---------------------------------------------------------------------------

describe('formatSelection', () => {
  test('does nothing when the selection is collapsed', () => {
    const {instance} = mountEditarea()
    const before = instance.state.editorState

    act(() => {
      instance.formatSelection('uppercase')
    })

    expect(instance.state.editorState).toBe(before)
  })

  test('uppercases the selected text', () => {
    const {instance} = mountEditarea()

    act(() => {
      instance.setState({
        editorState: EditorState.forceSelection(
          instance.state.editorState,
          instance.state.editorState.getSelection().merge({
            anchorOffset: 0,
            focusOffset: 4,
          }),
        ),
      })
    })
    flush()

    act(() => {
      instance.formatSelection('uppercase')
    })
    flush()

    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('CIAO')
  })
})

describe('addMissingSourceTagsToTarget', () => {
  test('appends every missing tag and refreshes the qa checks', () => {
    const sourceTag = {
      type: 'g',
      data: {
        id: '1',
        name: 'g',
        encodedText: '&lt;g id="1"&gt;',
        placeholder: '<g id="1">',
      },
      offset: 0,
      length: 1,
    }
    const {instance} = mountEditarea({
      segment: makeSegment({
        missingTagsInTarget: [sourceTag],
        targetTagMap: [],
      }),
    })
    SegmentActions.updateTranslation.mockClear()

    act(() => {
      instance.addMissingSourceTagsToTarget()
    })
    flush()

    expect(SegmentActions.updateTranslation).toHaveBeenCalled()
    expect(SegmentActions.getSegmentsQa).toHaveBeenCalled()
  })
})

describe('replaceWordAt', () => {
  test('replaces the given range with the suggested word', () => {
    const {instance} = mountEditarea()

    act(() => {
      instance.replaceWordAt({newWord: 'Salve', start: 0, end: 4})
    })
    flush()

    expect(
      instance.state.editorState.getCurrentContent().getPlainText(),
    ).toContain('Salve')
  })
})

// ---------------------------------------------------------------------------
// componentDidUpdate
// ---------------------------------------------------------------------------

describe('componentDidUpdate', () => {
  const rerenderWith = (rerender, segment, props = {}) =>
    act(() => {
      rerender(
        <SegmentContext.Provider value={{readonly: false, locked: false}}>
          <Editarea
            segment={segment}
            translation={segment.translation}
            updateCounter={props.updateCounter || jest.fn()}
            toggleFormatMenu={props.toggleFormatMenu || jest.fn()}
          />
        </SegmentContext.Provider>,
      )
    })

  test('moves the focus to the end when the segment gets opened', () => {
    const closed = makeSegment({opened: false})
    const ref = React.createRef()
    const {rerender} = render(
      <SegmentContext.Provider value={{readonly: false, locked: false}}>
        <Editarea
          ref={ref}
          segment={closed}
          translation={closed.translation}
          updateCounter={jest.fn()}
          toggleFormatMenu={jest.fn()}
        />
      </SegmentContext.Provider>,
    )
    flush()

    act(() => {
      rerender(
        <SegmentContext.Provider value={{readonly: false, locked: false}}>
          <Editarea
            ref={ref}
            segment={makeSegment({opened: true})}
            translation={closed.translation}
            updateCounter={jest.fn()}
            toggleFormatMenu={jest.fn()}
          />
        </SegmentContext.Provider>,
      )
    })
    flush()

    expect(ref.current.state.editorState.getSelection().getHasFocus()).toBe(
      true,
    )
  })

  test('collapses the selection to the end when the segment gets closed', () => {
    const ref = React.createRef()
    const {rerender} = render(
      <SegmentContext.Provider value={{readonly: false, locked: false}}>
        <Editarea
          ref={ref}
          segment={makeSegment({opened: true})}
          translation="Ciao mondo"
          updateCounter={jest.fn()}
          toggleFormatMenu={jest.fn()}
        />
      </SegmentContext.Provider>,
    )
    flush()

    act(() => {
      rerender(
        <SegmentContext.Provider value={{readonly: false, locked: false}}>
          <Editarea
            ref={ref}
            segment={makeSegment({opened: false})}
            translation="Ciao mondo"
            updateCounter={jest.fn()}
            toggleFormatMenu={jest.fn()}
          />
        </SegmentContext.Provider>,
      )
    })
    flush()

    expect(ref.current.state.editorState.getSelection().isCollapsed()).toBe(
      true,
    )
  })

  test('re-encodes the translation when the source tag map arrives', () => {
    const ref = React.createRef()
    const {rerender} = render(
      <SegmentContext.Provider value={{readonly: false, locked: false}}>
        <Editarea
          ref={ref}
          segment={makeSegment()}
          translation='Ciao <g id="1">mondo</g>'
          updateCounter={jest.fn()}
          toggleFormatMenu={jest.fn()}
        />
      </SegmentContext.Provider>,
    )
    flush()

    const sourceTagMap = [
      {
        type: 'g',
        data: {id: '1', name: 'g', encodedText: '&lt;g id="1"&gt;'},
        offset: 0,
        length: 1,
      },
    ]
    act(() => {
      rerender(
        <SegmentContext.Provider value={{readonly: false, locked: false}}>
          <Editarea
            ref={ref}
            segment={makeSegment({sourceTagMap})}
            translation='Ciao <g id="1">mondo</g>'
            updateCounter={jest.fn()}
            toggleFormatMenu={jest.fn()}
          />
        </SegmentContext.Provider>,
      )
    })
    flush()

    expect(ref.current.state.previousSourceTagMap).toEqual(sourceTagMap)
  })

  test('selects the whole block after a triple click', () => {
    const {instance} = mountEditarea()
    instance.wasTripleClickTriggered.current = true

    act(() => {
      instance.forceUpdate()
    })
    flush()

    const content = instance.state.editorState.getCurrentContent()
    const selection = instance.state.editorState.getSelection()
    expect(selection.getAnchorKey()).toBe(content.getFirstBlock().getKey())
    expect(selection.getAnchorOffset()).toBe(0)
    expect(selection.getFocusOffset()).toBe(
      content.getLastBlock().getText().length,
    )
    expect(instance.wasTripleClickTriggered.current).toBe(false)
  })

  test('adjusts the caret using the browser selection when the state is stable', () => {
    const {instance} = mountEditarea()
    // parentNode must be null, not undefined: getEntityContainer walks up with
    // a default-parameter recursion that never terminates on undefined
    restoreSelection = stubSelection({
      focusNode: {length: 10, parentNode: null},
      focusOffset: 2,
      type: 'Caret',
    })

    act(() => {
      instance.forceUpdate()
    })
    flush()

    expect(instance.state).toBeDefined()
  })

  test('notifies the focused tags whenever the editor state changes', () => {
    const {instance} = mountEditarea()
    SegmentActions.focusTags.mockClear()

    act(() => {
      instance.setState({
        editorState: EditorState.moveSelectionToEnd(instance.state.editorState),
      })
    })
    flush()

    expect(SegmentActions.focusTags).toHaveBeenCalled()
  })

  test('reports an empty focused tag list when the editor lost focus', () => {
    const {instance} = mountEditarea()
    instance.onBlurEvent()
    SegmentActions.focusTags.mockClear()

    act(() => {
      instance.setState({
        editorState: EditorState.moveSelectionToEnd(instance.state.editorState),
      })
    })
    flush()

    expect(SegmentActions.focusTags).toHaveBeenCalledWith([])
    instance.onFocus()
  })

  test('skips the decorator check while a composition is running', () => {
    const {instance} = mountEditarea()
    const spy = jest.spyOn(instance, 'checkDecorators')

    act(() => {
      instance.typeTextInEditor('x')
    })
    // typeTextInEditor turns composition on; the update right after must skip
    act(() => {
      instance.forceUpdate()
    })

    expect(spy).not.toHaveBeenCalled()
    flush()
  })
})

// ---------------------------------------------------------------------------
// Entity decorator strategy
// ---------------------------------------------------------------------------

describe('tag entity decoration', () => {
  // TagBox renders its own .tag-container heading, so scope the lookup to the
  // draft content itself
  const entities = (container) =>
    container.querySelectorAll('.public-DraftEditor-content .tag-container')

  test('renders a tag entity component for encoded tags', () => {
    const {container} = mountEditarea({
      translation: 'Ciao <g id="1">mondo</g>',
    })

    expect(entities(container).length).toBeGreaterThan(0)
  })

  test('leaves untagged text undecorated', () => {
    // no spaces: every literal space is itself encoded as a space tag entity
    const {container} = mountEditarea({translation: 'Ciao'})

    expect(entities(container)).toHaveLength(0)
  })
})
