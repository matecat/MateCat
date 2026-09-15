import React from 'react'
import {render, act, fireEvent} from '@testing-library/react'

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
import {SegmentContext} from './SegmentContext'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'
import SegmentConstants from '../../constants/SegmentConstants'
import EditAreaConstants from '../../constants/EditAreaConstants'

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

/** Builds a Selection stub complete enough for draft-js internals. */
/** A blacklist entry shaped the way QaCheckBlacklistHighlight expects. */
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
  const tree = (seg, trans) => (
    <SegmentContext.Provider value={context}>
      <Editarea
        ref={ref}
        segment={seg}
        translation={trans}
        updateCounter={updateCounter}
        toggleFormatMenu={toggleFormatMenu}
      />
    </SegmentContext.Provider>
  )
  const utils = render(tree(segment, translation))
  return {
    ...utils,
    instance: ref.current,
    updateCounter,
    toggleFormatMenu,
    /** Re-renders with a different segment, then drains deferred work. */
    update: (nextSegment, nextTranslation = nextSegment.translation) => {
      act(() => {
        utils.rerender(tree(nextSegment, nextTranslation))
      })
      flush()
    },
  }
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
/** Replaces window.getSelection with a controllable stub. */
let restoreSelection

beforeEach(() => {
  jest.useFakeTimers()
  jest.clearAllMocks()
  Object.keys(segmentStoreListeners).forEach(
    (k) => delete segmentStoreListeners[k],
  )
  global.config = {
    id_job: 2,
    target_code: 'it-IT',
    source_code: 'en-US',
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

  test('removes its five store listeners on unmount', () => {
    const {unmount} = mountEditarea()

    unmount()

    const own = SegmentStore.removeListener.mock.calls.filter(([event]) =>
      EDITAREA_EVENTS.includes(event),
    )
    expect(own.map(([event]) => event)).toEqual(EDITAREA_EVENTS)
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

// ---------------------------------------------------------------------------
// Translation replacement
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Decorators
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// myKeyBindingFn
// ---------------------------------------------------------------------------

// A source tag map with one entry, so the tag menu has something to suggest —
// `toggle-tag-menu` is a no-op when the segment has no source tags.
const SOURCE_TAG_MAP = [
  {
    type: 'g',
    data: {id: '1', name: 'g', encodedText: '&lt;g id="1"&gt;'},
    offset: 0,
    length: 1,
  },
]

function mountWithSourceTags(segmentOverrides = {}) {
  return mountEditarea({
    segment: makeSegment({sourceTagMap: SOURCE_TAG_MAP, ...segmentOverrides}),
  })
}

const editorNode = (container) =>
  container.querySelector('.public-DraftEditor-content')
const tagBox = (container) => container.querySelector('.tag-box')

/**
 * Presses a key on the editor the way a user does, so the event travels through
 * DraftJS's own keyBindingFn/handleKeyCommand wiring rather than being invoked
 * directly on the component.
 */
function pressKey(container, key) {
  // fireEvent already wraps in act, and flush() wraps the timer advance.
  fireEvent.keyDown(editorNode(container), key)
  flush()
}

/**
 * jsdom implements Range but not `Range.prototype.getBoundingClientRect`, which
 * Editarea calls to position the tag menu. Without it every tag-menu path
 * throws before the menu can open — which is why reaching those behaviours used
 * to require calling methods on the instance. Patching the gap is test-side
 * setup; the component is untouched.
 */
function useRangeRectPolyfill() {
  let original
  beforeAll(() => {
    original = Range.prototype.getBoundingClientRect
    Range.prototype.getBoundingClientRect = () => ({
      x: 0,
      y: 0,
      left: 0,
      right: 0,
      top: 0,
      bottom: 0,
      width: 0,
      height: 0,
    })
  })
  afterAll(() => {
    Range.prototype.getBoundingClientRect = original
  })
}

describe('tag menu keyboard shortcuts', () => {
  useRangeRectPolyfill()

  test('alt + t opens the tag menu', () => {
    const {container} = mountWithSourceTags()
    expect(tagBox(container)).not.toBeVisible()

    pressKey(container, {keyCode: 84, key: 't', altKey: true})

    expect(tagBox(container)).toBeVisible()
  })

  test('the mac option-t glyph also opens the tag menu', () => {
    const {container} = mountWithSourceTags()

    pressKey(container, {key: '™', altKey: true})

    expect(tagBox(container)).toBeVisible()
  })

  test('alt + shift + t does not open the tag menu', () => {
    const {container} = mountWithSourceTags()

    pressKey(container, {keyCode: 84, key: 'T', altKey: true, shiftKey: true})

    expect(tagBox(container)).not.toBeVisible()
  })

  test('typing "<" opens the tag menu', () => {
    const {container} = mountWithSourceTags()

    pressKey(container, {key: '<'})

    expect(tagBox(container)).toBeVisible()
  })

  test('escape closes an open tag menu', () => {
    const {container} = mountWithSourceTags()
    pressKey(container, {keyCode: 84, key: 't', altKey: true})
    expect(tagBox(container)).toBeVisible()

    pressKey(container, {key: 'Escape', keyCode: 27})

    expect(tagBox(container)).not.toBeVisible()
  })

  test('the tag menu stays closed when the segment has no source tags', () => {
    const {container} = mountEditarea({
      segment: makeSegment({sourceTagMap: []}),
    })

    pressKey(container, {keyCode: 84, key: 't', altKey: true})

    expect(tagBox(container)).not.toBeVisible()
  })
})

describe('tag insertion keyboard shortcuts', () => {
  const tagCount = (container) =>
    container.querySelectorAll('.public-DraftEditor-content .tag-container')
      .length

  test('tab inserts a tab tag and shift + tab does not', () => {
    const {container} = mountEditarea({translation: 'ciao'})
    expect(tagCount(container)).toBe(0)

    pressKey(container, {key: 'Tab'})
    expect(tagCount(container)).toBe(1)

    pressKey(container, {key: 'Tab', shiftKey: true})
    expect(tagCount(container)).toBe(1)
  })

  test('space inserts a space tag when the space signature is enabled', () => {
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {code: 'Space', key: ' '})

    expect(tagCount(container)).toBe(1)
  })

  test('space does not insert a space tag when the signature is disabled', () => {
    setTagSignatureMiddleware('space', () => false)
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {code: 'Space', key: ' '})

    expect(tagCount(container)).toBe(0)
  })

  test('ctrl + shift + space inserts a nbsp tag', () => {
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {key: ' ', ctrlKey: true, shiftKey: true})

    expect(tagCount(container)).toBe(1)
  })

  test('alt + space on a chromebook inserts a nbsp tag', () => {
    const userAgent = jest
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 (X11; CrOS x86_64)')
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {key: ' ', altKey: true})

    expect(tagCount(container)).toBe(1)
    userAgent.mockRestore()
  })

  test('alt + space off a chromebook does not insert a nbsp tag', () => {
    const userAgent = jest
      .spyOn(window.navigator, 'userAgent', 'get')
      .mockReturnValue('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {key: ' ', altKey: true})

    expect(tagCount(container)).toBe(0)
    userAgent.mockRestore()
  })

  test('ctrl + alt + space inserts a word joiner tag', () => {
    const {container} = mountEditarea({translation: 'ciao'})

    pressKey(container, {key: ' ', ctrlKey: true, altKey: true})

    expect(tagCount(container)).toBe(1)
  })
})

// Caret navigation around tag entities is deliberately not covered here.
//
// The behaviour only exists relative to a caret sitting next to an entity, and
// jsdom cannot produce one: DraftJS derives its selection in `editOnSelect` by
// reading `window.getSelection()` and mapping the DOM nodes back through their
// `data-offset-key` attributes, and a synthetic Range + `select` event does not
// survive that mapping — the editor's selection is left untouched (verified).
//
// The tests that used to live here worked around it by calling
// `instance.setState({editorState: EditorState.forceSelection(...)})` to place
// the caret, which is the component carrying a `setState` escape hatch purely
// so its own tests can drive it. What they bought was thin: of their three
// assertions, two accepted either outcome (`expect(['right-nav', undefined])
// .toContain(command)`, and a `typeof command === 'string' || command ===
// undefined` tautology) and only the backspace-on-an-entity one could fail at
// all — `myKeyBindingFn` never returns 'right-nav'/'left-nav' in the first
// place; those are handleKeyCommand cases.
//
// Covering this properly needs a real browser (Playwright), not a stronger
// mock.

// ---------------------------------------------------------------------------
// handleKeyCommand
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Tag menu
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// onChange
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// DOM event handlers
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Clipboard
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Drag and drop
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Entity interaction
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// componentDidUpdate
// ---------------------------------------------------------------------------

describe('componentDidUpdate', () => {
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

// The ref Editarea exposes is production API, not test scaffolding: three
// components reach through it, and each of their own test suites passes a
// hand-made `editArea` stub instead of a real instance. Nothing else checks
// that the real handle still satisfies them, so this does — and it is the
// contract that has to survive any narrowing of the exposed surface.
//
//   SegmentTarget.js          editArea.addMissingSourceTagsToTarget
//   SegmentTargetToolbar.js   editArea.formatSelection('uppercase' | ...)
//   AiAlternatives.js         editArea?.state?.editorState
//   AiAlternatives.js         editArea?.editAreaRef.contains(...)
//
// Note the last two: neither `editAreaRef` nor `formatSelection` is reached
// through optional chaining at the call site, so they must always be present
// on a mounted instance, not merely usually.
describe('Editarea production ref surface', () => {
  test('exposes addMissingSourceTagsToTarget for SegmentTarget', () => {
    const {instance} = mountEditarea({translation: 'Ciao'})

    expect(typeof instance.addMissingSourceTagsToTarget).toBe('function')
  })

  test('exposes formatSelection for SegmentTargetToolbar', () => {
    const {instance} = mountEditarea({translation: 'Ciao'})

    expect(typeof instance.formatSelection).toBe('function')
  })

  test('exposes state.editorState for AiAlternatives', () => {
    const {instance} = mountEditarea({translation: 'Ciao'})

    expect(instance.state).toBeDefined()
    expect(typeof instance.state.editorState.getSelection).toBe('function')
    expect(typeof instance.state.editorState.getCurrentContent).toBe('function')
  })

  test('exposes editAreaRef as a live DOM node for AiAlternatives', () => {
    const {instance} = mountEditarea({translation: 'Ciao'})

    expect(instance.editAreaRef).toBeInstanceOf(HTMLElement)
    // AiAlternatives uses this to decide whether focus sits inside the editor,
    // so the call has to work on a real node rather than return a useful value
    // here — the editor holds focus once mounted.
    expect(instance.editAreaRef.contains(document.body)).toBe(false)
    expect(typeof instance.editAreaRef.contains(document.activeElement)).toBe(
      'boolean',
    )
  })
})

// ---------------------------------------------------------------------------
// Stale-closure guards
// ---------------------------------------------------------------------------
//
// The handlers below are registered with the store ONCE, in a mount-only
// effect, so whatever function object is read at mount is kept for the life of
// the component. They must still see the CURRENT props when they fire later.
//
// Each guard re-renders with a different segment first, then fires the handler
// that was registered at mount. If a handler closed over the props it was
// created with, it compares against the old sid and silently does nothing —
// which is the defect that shipped in 71cd271, where a once-built callback kept
// returning first-render data.
//
// These are the safety net for converting the remaining useRef-wrapped methods
// into plain per-render closures: a conversion that freezes one of these paths
// fails here instead of in production.
describe('frozen call sites see the current props', () => {
  const OTHER = '12-2'

  /** The handler the component registered for `event` at mount. */
  const listenerFor = (event) => {
    const call = SegmentStore.addListener.mock.calls.find(
      ([registered]) => registered === event,
    )
    if (!call) throw new Error(`no listener registered for ${event}`)
    return call[1]
  }

  function renderThenSwapSegment(overrides = {}) {
    const first = makeSegment({sid: '12-1', translation: 'uno'})
    const rendered = renderEditarea({segment: first, translation: 'uno'})
    flush()
    const second = makeSegment({sid: OTHER, translation: 'due', ...overrides})
    rendered.update(second, 'due')
    return rendered
  }

  test('REPLACE_TRANSLATION applies to the segment rendered now', () => {
    const {container} = renderThenSwapSegment()

    act(() => {
      listenerFor(SegmentConstants.REPLACE_TRANSLATION)(OTHER, 'tre')
    })
    flush()

    expect(container.textContent).toContain('tre')
  })

  test('REPLACE_TRANSLATION ignores the segment that was mounted first', () => {
    const {container} = renderThenSwapSegment()

    act(() => {
      listenerFor(SegmentConstants.REPLACE_TRANSLATION)('12-1', 'stale')
    })
    flush()

    expect(container.textContent).not.toContain('stale')
  })

  test('REFRESH_TAG_MAP re-encodes against the segment rendered now', () => {
    const {container} = renderThenSwapSegment({
      translation: 'due <g id="1">tag</g>',
      sourceTagMap: [
        {
          type: 'g',
          data: {id: '1', name: 'g', encodedText: '&lt;g id="1"&gt;'},
          offset: 0,
          length: 1,
        },
      ],
    })

    act(() => {
      listenerFor(SegmentConstants.REFRESH_TAG_MAP)()
    })
    flush()

    expect(container.textContent).toContain('tag')
  })

  test('CHANGE_CHARACTERS_COUNTER_RULES recounts the segment rendered now', () => {
    const {updateCounter} = renderThenSwapSegment()
    updateCounter.mockClear()

    act(() => {
      listenerFor(SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES)()
    })
    flush()

    expect(updateCounter).toHaveBeenCalled()
  })
})
