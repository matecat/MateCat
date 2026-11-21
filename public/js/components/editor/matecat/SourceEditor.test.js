/* eslint-disable testing-library/no-node-access, testing-library/no-container --
   The CodeMirror editor, its chips and its marks are plain DOM with no roles or labels: they are found by class. */
import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import {EditorView} from '@codemirror/view'

import SourceEditor, {
  highlightedChips,
  isValidPhraseToAiAssistant,
  joinSplitGroup,
  SPLIT_PLACEHOLDER,
} from './SourceEditor'
import {SegmentContext} from '../../segments/SegmentContext'
import {ApplicationWrapperContext} from '../../common/ApplicationWrapper/ApplicationWrapperContext'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import SearchUtils from '../../header/cattol/search/searchUtils'
import {readSegmentClipboard} from '../../segmentView/segmentModel'
import {sourceEditorBridge} from '../../segments/sourceEditorBridge'
import {setTagSignatureMiddleware} from '../../segments/utils/DraftMatecatUtils/tagModel'

setTagSignatureMiddleware('space', () => false)

// A store that really dispatches its events, so the tests drive the editor as the cattool does.
jest.mock('../../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    __esModule: true,
    default: {
      listeners,
      addListener: jest.fn((name, fn) => {
        ;(listeners[name] = listeners[name] || []).push(fn)
      }),
      removeListener: jest.fn((name, fn) => {
        listeners[name] = (listeners[name] || []).filter((f) => f !== fn)
      }),
      emit: (name, ...args) =>
        (listeners[name] || []).forEach((fn) => fn(...args)),
      getSegmentByIdToJS: jest.fn(),
    },
  }
})

jest.mock('../../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    closeSplitSegment: jest.fn(),
    focusTags: jest.fn(),
    helpAiAssistant: jest.fn(),
    highlightTags: jest.fn(),
    openConcordance: jest.fn(),
    openGlossaryFormPrefill: jest.fn(),
    splitSegment: jest.fn(),
    updateSource: jest.fn(),
  },
}))

const mockGetRanges = jest.fn(() => [])
jest.mock('../../../utils/lxq.main', () => ({
  __esModule: true,
  default: {
    getRanges: (...args) => mockGetRanges(...args),
    buildTooltipMessages: () => [{type: 'error', msg: 'Spelling'}],
  },
}))

let mockConcordance = null
jest.mock('../../../hooks/UseHotKeysComponent', () => ({
  UseHotKeysComponent: ({shortcut, callback}) => {
    mockConcordance = {shortcut, callback}
    return null
  },
}))

// jsdom has no layout: CodeMirror only needs these to exist.
beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

const makeSegment = (overrides = {}) => ({
  sid: '10',
  original_sid: '10',
  segment: 'Hello <g id="1">world</g>',
  opened: true,
  openSplit: false,
  splitted: false,
  inSearch: false,
  searchParams: {},
  glossary: [],
  lexiqa: {},
  lxqDecodedSource: '',
  icu: false,
  ...overrides,
})

const fakeBridge = () => ({
  publishSource: jest.fn(),
  tagIdentities: jest.fn(() => []),
})

const renderSource = (
  segment,
  {value = segment.segment, bridge, userInfo} = {},
) => {
  const tree = (seg, val) => (
    <SegmentContext.Provider value={{segment: seg, userInfo}}>
      <SourceEditor segment={seg} value={val} bridge={bridge} />
    </SegmentContext.Provider>
  )
  const utils = render(tree(segment, value))
  return {
    ...utils,
    update: (seg, val = seg.segment) => utils.rerender(tree(seg, val)),
  }
}

// Shared DOM lookups, in one place for eslint-testing-library.
const viewOf = (container) =>
  EditorView.findFromDOM(container.querySelector('.cm-editor'))
const sourceEl = (container) => container.querySelector('#segment-10-source')
const chipEls = (container) => [
  ...container.querySelectorAll('.cm-content .tag'),
]
const marks = (container, selector) =>
  [...container.querySelectorAll(selector)].map((el) => el.textContent)

const flush = async (ms = 0) => {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, ms))
  })
}

const select = (view, anchor, head) =>
  act(() => {
    view.dispatch({selection: {anchor, head}, userEvent: 'select'})
  })
const selectText = (view, text) => {
  const from = view.state.doc.toString().indexOf(text)
  return select(view, from, from + text.length)
}

beforeEach(() => {
  window.config = {
    ...window.config,
    source_code: 'en-US',
    isSourceRTL: false,
    isOpenAiEnabled: false,
    isCJK: false,
  }
  Object.keys(SegmentStore.listeners).forEach(
    (k) => delete SegmentStore.listeners[k],
  )
  jest.clearAllMocks()
  mockGetRanges.mockReturnValue([])
})

describe('SourceEditor rendering', () => {
  test('shows the source with its tags as chips, read-only, in the cattool source element', async () => {
    const {container} = renderSource(makeSegment())
    expect(sourceEl(container)).toHaveClass('source', 'item')
    expect(sourceEl(container)).toHaveAttribute(
      'data-original',
      'Hello <g id="1">world</g>',
    )
    const view = viewOf(container)
    expect(view.state.readOnly).toBe(true)
    expect(view.state.doc.toString()).toBe('Hello <g id="1">world</g>')
    expect(chipEls(container)).toHaveLength(2)
  })

  test('typing does not change the source', async () => {
    const {container} = renderSource(makeSegment())
    const view = viewOf(container)
    fireEvent.keyDown(view.contentDOM, {key: 'a'})
    expect(view.state.doc.toString()).toBe('Hello <g id="1">world</g>')
  })

  test('right-to-left source languages get an rtl editor', () => {
    window.config.isSourceRTL = true
    const {container} = renderSource(makeSegment())
    expect(viewOf(container).contentDOM).toHaveAttribute('dir', 'rtl')
  })
})

describe('SourceEditor store wiring', () => {
  test('publishes the source into the store once mounted', async () => {
    const bridge = fakeBridge()
    renderSource(makeSegment(), {bridge})
    expect(bridge.publishSource).not.toHaveBeenCalled()
    await flush()
    expect(bridge.publishSource).toHaveBeenCalledWith(
      '10',
      'Hello <g id="1">world</g>',
    )
  })

  test('a tag map refresh reloads the current value and publishes it again', async () => {
    const bridge = fakeBridge()
    const segment = makeSegment()
    const {container, update} = renderSource(segment, {bridge})
    await flush()
    update(segment, 'Hello world')
    bridge.publishSource.mockClear()
    act(() => SegmentStore.emit(SegmentConstants.REFRESH_TAG_MAP))
    expect(viewOf(container).state.doc.toString()).toBe('Hello world')
    expect(bridge.publishSource).toHaveBeenCalledWith('10', 'Hello world')
  })

  test('Guess tag reloads the source of its own segment only', async () => {
    const bridge = fakeBridge()
    renderSource(makeSegment(), {bridge})
    await flush()
    bridge.publishSource.mockClear()
    act(() => SegmentStore.emit(SegmentConstants.SET_SEGMENT_TAGGED, '99'))
    await flush()
    expect(bridge.publishSource).not.toHaveBeenCalled()
    act(() => SegmentStore.emit(SegmentConstants.SET_SEGMENT_TAGGED, '10'))
    await flush()
    expect(bridge.publishSource).toHaveBeenCalledTimes(1)
  })

  test('a new value is loaded and published (tag projection turned off)', async () => {
    const bridge = fakeBridge()
    const segment = makeSegment()
    const {container, update} = renderSource(segment, {
      bridge,
      value: 'Hello world',
    })
    await flush()
    update(segment, 'Hello <g id="1">world</g>')
    expect(viewOf(container).state.doc.toString()).toBe(
      'Hello <g id="1">world</g>',
    )
    expect(bridge.publishSource).toHaveBeenLastCalledWith(
      '10',
      'Hello <g id="1">world</g>',
    )
  })

  test('removes every store listener it added when unmounted', () => {
    const {unmount} = renderSource(makeSegment())
    unmount()
    Object.values(SegmentStore.listeners).forEach((fns) =>
      expect(fns).toHaveLength(0),
    )
    expect(SegmentStore.addListener).toHaveBeenCalledTimes(4)
  })
})

describe('SourceEditor layers', () => {
  const glossary = [{term_id: 5, matching_words: ['Hello']}]

  test('glossary and missing terms are highlighted', () => {
    const {container} = renderSource(
      makeSegment({
        segment: 'Hello big world',
        glossary: [
          ...glossary,
          {term_id: 6, matching_words: ['big'], missingTerm: true},
        ],
      }),
    )
    expect(marks(container, '.glossaryItem')).toEqual(['Hello'])
    expect(marks(container, '.qaCheckGlossaryItem')).toEqual(['big'])
  })

  test('search hides the other layers, and they come back when it ends', () => {
    Object.assign(SearchUtils, {
      searchMode: 'normal',
      searchParams: {source: 'world'},
      occurrencesList: ['10'],
      featuredSearchResult: 5,
      searchResultsDictionary: {10: {occurrences: []}},
    })
    const segment = makeSegment({segment: 'Hello world', glossary})
    const {container, update} = renderSource(segment)
    expect(marks(container, '.glossaryItem')).toEqual(['Hello'])
    update({...segment, inSearch: true, searchParams: {source: 'world'}})
    expect(marks(container, 'mark.searchMarker')).toEqual(['world'])
    expect(marks(container, '.glossaryItem')).toEqual([])
    update({...segment, inSearch: false, searchParams: {}})
    expect(marks(container, 'mark.searchMarker')).toEqual([])
    expect(marks(container, '.glossaryItem')).toEqual(['Hello'])
  })

  test('Lexiqa warnings on the source are highlighted', () => {
    mockGetRanges.mockReturnValue([
      {start: 6, end: 11, myClass: 'spelling s0', errorid: 'e', color: 'red'},
    ])
    const {container} = renderSource(
      makeSegment({
        segment: 'Hello wrold',
        lexiqa: {source: {spelling: [{}]}},
        lxqDecodedSource: 'Hello wrold',
      }),
    )
    expect(marks(container, '.lexiqahighlight')).toEqual(['wrold'])
  })

  test('ICU syntax is highlighted only in ICU segments', () => {
    const {container: icuSegment} = renderSource(
      makeSegment({segment: 'Hi {name}', icu: true}),
    )
    expect(marks(icuSegment, '.icuItem')).toEqual(['{', 'name', '}'])
    const {container: plainSegment} = renderSource(
      makeSegment({sid: '11', segment: 'Hi {name}'}),
    )
    expect(marks(plainSegment, '.icuItem')).toEqual([])
  })
})

describe('SourceEditor tags', () => {
  test('clicking a tag highlights it and its twin in the target, by the Draft id and placeholder', async () => {
    const {container} = renderSource(makeSegment(), {
      bridge: sourceEditorBridge,
    })
    fireEvent.mouseDown(chipEls(container)[0], {button: 0})
    await flush()
    expect(SegmentActions.highlightTags).toHaveBeenLastCalledWith(
      '1',
      '1',
      'cm-source-10-6',
    )
  })

  test('a highlight event marks the clicked chip and every chip with the same id and placeholder', async () => {
    const {container} = renderSource(
      makeSegment({segment: 'a <g id="1">b</g> <x id="2"/>'}),
      {bridge: sourceEditorBridge},
    )
    act(() =>
      SegmentStore.emit(SegmentConstants.HIGHLIGHT_TAGS, '1', '1', 'draft-key'),
    )
    await flush()
    expect(
      chipEls(container).map((el) => el.classList.contains('tag-clicked')),
    ).toEqual([true, true, false])
    act(() => SegmentStore.emit(SegmentConstants.HIGHLIGHT_TAGS))
    await flush()
    expect(container.querySelectorAll('.tag-clicked')).toHaveLength(0)
  })

  test('selecting text that holds a tag shows the tag focused; moving away clears the target highlight', async () => {
    const {container} = renderSource(makeSegment())
    const view = viewOf(container)
    selectText(view, 'o <g id="1">w')
    await flush()
    expect(
      chipEls(container).map((el) => el.classList.contains('tag-focused')),
    ).toEqual([true, false])
    expect(SegmentActions.highlightTags).toHaveBeenCalledWith()
    select(view, 1, 1)
    await flush()
    expect(container.querySelectorAll('.tag-focused')).toHaveLength(0)
  })

  test('a copy carries the selection as a raw segment slice the target editors paste with its tags', () => {
    const {container} = renderSource(makeSegment())
    const view = viewOf(container)
    selectText(view, 'o <g id="1">wo')
    const data = {}
    const clipboardData = {
      setData: (t, v) => (data[t] = v),
      getData: (t) => data[t] ?? '',
      clearData: () => Object.keys(data).forEach((k) => delete data[k]),
    }
    const event = Object.assign(
      new Event('copy', {bubbles: true, cancelable: true}),
      {clipboardData},
    )
    view.contentDOM.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
    expect(readSegmentClipboard(data['text/html'])).toBe('o <g id="1">wo')
    expect(data['text/plain']).toBe('o 1wo')
  })

  test('cut does nothing to the source, not even a copy', () => {
    const {container} = renderSource(makeSegment())
    const view = viewOf(container)
    selectText(view, 'Hello')
    const setData = jest.fn()
    const event = Object.assign(
      new Event('cut', {bubbles: true, cancelable: true}),
      {clipboardData: {setData, clearData: jest.fn()}},
    )
    view.contentDOM.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
    expect(setData).not.toHaveBeenCalled()
    expect(view.state.doc.toString()).toBe('Hello <g id="1">world</g>')
  })

  test('a triple click selects the whole source', () => {
    const {container} = renderSource(makeSegment())
    const view = viewOf(container)
    fireEvent.mouseDown(view.contentDOM, {button: 0, detail: 3})
    const {from, to} = view.state.selection.main
    expect([from, to]).toEqual([0, view.state.doc.length])
  })

  test('blurring the source clears the tag highlight and focus everywhere and hides the toolbar', async () => {
    const {container} = renderSource(makeSegment())
    selectText(viewOf(container), 'Hello')
    fireEvent.mouseUp(sourceEl(container))
    await flush()
    expect(container.querySelector('.optionsToolbar')).toBeInTheDocument()
    jest.clearAllMocks()
    fireEvent.blur(sourceEl(container))
    await flush()
    expect(SegmentActions.highlightTags).toHaveBeenCalledWith()
    expect(SegmentActions.focusTags).toHaveBeenCalledWith([])
    expect(container.querySelector('.optionsToolbar')).not.toBeInTheDocument()
  })
})

describe('SourceEditor selection toolbar', () => {
  const showToolbar = async (container, text) => {
    selectText(viewOf(container), text)
    fireEvent.mouseUp(sourceEl(container))
    await flush()
  }

  test('adds the selected text, without its tags, to the termbase', async () => {
    const {container} = renderSource(makeSegment())
    await showToolbar(container, 'o <g id="1">wo')
    fireEvent.mouseDown(
      screen.getByTitle('Click to add the highlighted text to the termbase'),
    )
    expect(SegmentActions.openGlossaryFormPrefill).toHaveBeenCalledWith(
      expect.objectContaining({sid: '10', originalTerm: 'o wo'}),
    )
  })

  test('stays hidden while nothing is selected', async () => {
    const {container} = renderSource(makeSegment())
    fireEvent.mouseUp(sourceEl(container))
    await flush()
    expect(container.querySelector('.optionsToolbar')).not.toBeInTheDocument()
  })

  test('arrow keys show it for a keyboard selection', async () => {
    const {container} = renderSource(makeSegment())
    selectText(viewOf(container), 'Hello')
    fireEvent.keyUp(sourceEl(container), {key: 'ArrowRight'})
    expect(container.querySelector('.optionsToolbar')).toBeInTheDocument()
  })

  test('offers the AI assistant to users without it, disabled over the word limit', async () => {
    window.config.isOpenAiEnabled = true
    const userInfo = {metadata: {ai_assistant: 0}}
    const {container} = renderSource(
      makeSegment({segment: 'one two three four'}),
      {userInfo},
    )
    await showToolbar(container, 'one two')
    const button = screen.getByTitle(
      'See the meaning of the highlighted text in this context',
    )
    fireEvent.mouseDown(button)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'one two',
    })
    await showToolbar(container, 'one two three four')
    expect(screen.getByTitle(/over the AI assistant's limit/)).toBeDisabled()
  })

  test('users with the AI assistant get it asked on selection, not as a button', async () => {
    window.config.isOpenAiEnabled = true
    const {container} = renderSource(makeSegment({segment: 'one two'}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await showToolbar(container, 'one')
    expect(
      screen.queryByTitle(
        'See the meaning of the highlighted text in this context',
      ),
    ).not.toBeInTheDocument()
    await flush(250)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'one',
    })
  })

  test('a second selection cancels the AI request pending for the first', async () => {
    window.config.isOpenAiEnabled = true
    const {container} = renderSource(makeSegment({segment: 'one two'}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await showToolbar(container, 'one')
    await showToolbar(container, 'two')
    await flush(250)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledTimes(1)
    expect(SegmentActions.helpAiAssistant).toHaveBeenCalledWith({
      sid: '10',
      value: 'two',
    })
  })

  test('no AI request when OpenAI is off', async () => {
    const {container} = renderSource(makeSegment({segment: 'one two'}), {
      userInfo: {metadata: {ai_assistant: 1}},
    })
    await showToolbar(container, 'one')
    await flush(250)
    expect(SegmentActions.helpAiAssistant).not.toHaveBeenCalled()
  })
})

describe('SourceEditor concordance shortcut', () => {
  const press = (selection) => {
    const original = window.getSelection
    window.getSelection = () => selection
    const event = {preventDefault: jest.fn()}
    mockConcordance.callback(event)
    window.getSelection = original
    return event
  }

  test('searches the selected text in the concordance', () => {
    renderSource(makeSegment())
    const event = press({type: 'Range', toString: () => '  world '})
    expect(event.preventDefault).toHaveBeenCalled()
    expect(SegmentActions.openConcordance).toHaveBeenCalledWith(
      '10',
      'world',
      false,
    )
  })

  test('ignores blank selections and a bare caret', () => {
    renderSource(makeSegment())
    press({type: 'Range', toString: () => '   '})
    press({type: 'Caret', toString: () => ''})
    expect(SegmentActions.openConcordance).not.toHaveBeenCalled()
  })
})

describe('SourceEditor split mode', () => {
  const splitting = (overrides) => makeSegment({openSplit: true, ...overrides})
  const caretAfter = (view, text) => {
    const at = view.state.doc.toString().indexOf(text) + text.length
    select(view, at, at)
  }

  test('a click puts a split point at the caret and confirm sends the split text', () => {
    const segment = splitting({segment: 'One &lt;two&gt; three'})
    const {container} = renderSource(segment)
    expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()
    caretAfter(viewOf(container), 'One ')
    fireEvent.click(sourceEl(container))
    expect(container.querySelector('.splitNum .num')).toHaveTextContent('1')
    fireEvent.click(screen.getByRole('button', {name: 'Confirm'}))
    expect(SegmentActions.splitSegment).toHaveBeenCalledWith(
      '10',
      `One ${SPLIT_PLACEHOLDER}<two> three`,
    )
  })

  test('a click that ends a text selection only clears it', () => {
    const {container} = renderSource(splitting({segment: 'One two'}))
    const view = viewOf(container)
    selectText(view, 'One')
    fireEvent.click(sourceEl(container))
    expect(view.state.selection.main.empty).toBe(true)
    expect(view.state.doc.toString()).toBe('One two')
    expect(container.querySelector('.splitNum')).not.toBeInTheDocument()
  })

  test('clicking a split point removes it, and the click does not add another', () => {
    const {container} = renderSource(splitting({segment: 'One two'}))
    const view = viewOf(container)
    caretAfter(view, 'One')
    fireEvent.click(sourceEl(container))
    const split = chipEls(container)[0]
    fireEvent.mouseDown(sourceEl(container))
    fireEvent.mouseDown(split, {button: 0})
    fireEvent.click(sourceEl(container))
    expect(view.state.doc.toString()).toBe('One two')
    expect(container.querySelector('.splitNum')).not.toBeInTheDocument()
  })

  test('clicking another tag in split mode neither splits nor highlights', async () => {
    const {container} = renderSource(splitting())
    fireEvent.mouseDown(sourceEl(container))
    fireEvent.mouseDown(chipEls(container)[0], {button: 0})
    fireEvent.click(sourceEl(container))
    await flush()
    expect(chipEls(container)).toHaveLength(2)
    expect(SegmentActions.highlightTags).not.toHaveBeenCalledWith(
      '1',
      expect.anything(),
      expect.anything(),
    )
  })

  test('a segment already split shows its whole group, split points between the parts', () => {
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({
      segment: {'10-1': 'One', '10-2': 'two'}[sid],
    }))
    const segment = makeSegment({
      splitted: true,
      split_group: ['10-1', '10-2'],
      segment: 'One',
    })
    const {container, update} = renderSource(segment)
    update({...segment, openSplit: true})
    expect(viewOf(container).state.doc.toString()).toBe(
      `One${SPLIT_PLACEHOLDER}two`,
    )
    expect(container.querySelector('.splitNum .num')).toHaveTextContent('1')
    // the count of an already split segment does not move
    caretAfter(viewOf(container), 'tw')
    fireEvent.click(sourceEl(container))
    expect(container.querySelector('.splitNum .num')).toHaveTextContent('1')
  })

  test('closing split mode restores the source and the split count', () => {
    const segment = splitting({segment: 'One two'})
    const {container} = renderSource(segment)
    caretAfter(viewOf(container), 'One')
    fireEvent.click(sourceEl(container))
    act(() => SegmentStore.emit(SegmentConstants.CLOSE_SPLIT_SEGMENT))
    expect(viewOf(container).state.doc.toString()).toBe('One two')
    expect(container.querySelector('.splitNum')).not.toBeInTheDocument()
  })

  test('cancel closes split mode', () => {
    renderSource(splitting())
    fireEvent.click(screen.getByText('Cancel'))
    expect(SegmentActions.closeSplitSegment).toHaveBeenCalled()
  })
})

describe('helpers', () => {
  test.each([
    ['one two three', 'en-US', true],
    ['one two three four', 'en-US', false],
    ['我们的客房很', 'zh-CN', true],
    ['我们的客房很好', 'zh-TW', false],
    ['あいうえおかきくけこ', 'ja-JP', true],
    ['あいうえおかきくけこさ', 'ja-JP', false],
    ['', 'en-US', false],
  ])('isValidPhraseToAiAssistant(%j, %s) is %s', (phrase, code, valid) => {
    expect(isValidPhraseToAiAssistant({phrase, sourceLanguageCode: code})).toBe(
      valid,
    )
  })

  test('the language defaults to the source language of the job', () => {
    window.config.source_code = 'ja-JP'
    expect(isValidPhraseToAiAssistant({phrase: 'a b c d'})).toBe(true)
  })

  test('highlightedChips: no id highlights only the clicked chip', () => {
    const chips = [
      {from: 0, to: 1, key: 'a', identity: {id: null, placeholder: null}},
      {from: 2, to: 3, key: 'b', identity: {id: null, placeholder: null}},
    ]
    expect(highlightedChips(chips, {tagId: null, key: 'b'})).toEqual([
      {from: 2, to: 3, className: 'tag-clicked'},
    ])
  })

  test('highlightedChips: the placeholder must match too', () => {
    const chips = [
      {from: 0, to: 1, key: 'a', identity: {id: '1', placeholder: 'x'}},
      {from: 2, to: 3, key: 'b', identity: {id: '1', placeholder: 'y'}},
    ]
    expect(
      highlightedChips(chips, {tagId: '1', tagPlaceholder: 'y', key: 'z'}),
    ).toEqual([{from: 2, to: 3, className: 'tag-clicked'}])
  })

  test('joinSplitGroup joins the parts of a split segment with split points', () => {
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({
      segment: sid,
    }))
    expect(joinSplitGroup(['a', 'b', 'c'])).toBe(
      `a${SPLIT_PLACEHOLDER}b${SPLIT_PLACEHOLDER}c`,
    )
  })
})

describe('SourceEditor show whitespace option', () => {
  const spaceMarks = (container) =>
    container.querySelectorAll('.cm-content .special-char-space').length

  test("follows the user's option, and applies a change live without remounting", async () => {
    const segment = makeSegment({segment: 'a b c'})
    const tree = (showWhitespace) => (
      <ApplicationWrapperContext.Provider
        value={{userInfo: {metadata: {show_whitespace: showWhitespace}}}}
      >
        <SegmentContext.Provider value={{segment}}>
          <SourceEditor
            segment={segment}
            value={segment.segment}
            bridge={fakeBridge()}
          />
        </SegmentContext.Provider>
      </ApplicationWrapperContext.Provider>
    )
    const {container, rerender} = render(tree(1))
    await flush()
    const view = viewOf(container)
    expect(spaceMarks(container)).toBe(2)

    rerender(tree(0))
    expect(spaceMarks(container)).toBe(0)
    rerender(tree(1))
    expect(spaceMarks(container)).toBe(2)
    // the same editor: the option is not a remount
    expect(viewOf(container)).toBe(view)
  })

  test('marks no space without a logged user', async () => {
    const segment = makeSegment({segment: 'a b c'})
    const {container} = render(
      <SegmentContext.Provider value={{segment}}>
        <SourceEditor
          segment={segment}
          value={segment.segment}
          bridge={fakeBridge()}
        />
      </SegmentContext.Provider>,
    )
    await flush()
    expect(spaceMarks(container)).toBe(0)
  })
})
