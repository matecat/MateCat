/* eslint-disable testing-library/no-node-access, testing-library/no-container --
   The CodeMirror editor and its chips are plain DOM with no roles or labels: they are found by class. */
import React, {createRef} from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import {EditorView} from '@codemirror/view'

import TargetEditor, {tagMenuPosition, tagWarningClass} from './TargetEditor'
import {SegmentContext} from '../../segments/SegmentContext'
import {ApplicationWrapperContext} from '../../common/ApplicationWrapper/ApplicationWrapperContext'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import ContextPreviewChannel from '../../../utils/contextPreviewChannel'
import {
  targetEditorBridge,
  translationUpdate,
} from '../../segments/targetEditorBridge'
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
    },
  }
})

jest.mock('../../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    focusTags: jest.fn(),
    getSegmentsQa: jest.fn(),
    highlightTags: jest.fn(),
    startSegmentQACheck: jest.fn(),
    updateTranslation: jest.fn(),
  },
}))

jest.mock('../../../stores/CatToolStore', () => ({
  __esModule: true,
  default: {
    getCurrentProjectTemplate: () => ({
      characterCounterCountTags: false,
    }),
  },
}))

jest.mock('../../../utils/contextPreviewChannel', () => ({
  __esModule: true,
  default: {sendMessage: jest.fn()},
}))

// jsdom has no layout: CodeMirror only needs these to exist.
beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

const SOURCE = 'Hello <g id="1">world</g> <x id="2"/>'
const sourceTagMap = translationUpdate(SOURCE).tagMap

const makeSegment = (overrides = {}) => ({
  sid: '10',
  segment: SOURCE,
  translation: 'Ciao <g id="1">mondo</g>',
  decodedTranslation: 'Ciao mondo',
  opened: true,
  muted: false,
  openSplit: false,
  sourceTagMap,
  missingTagsInTarget: [],
  targetTagMap: [],
  ...overrides,
})

const renderTarget = (
  segment = makeSegment(),
  {value = segment.translation, context = {}, bridge = targetEditorBridge} = {},
) => {
  const ref = createRef()
  const props = {
    updateCounter: jest.fn(),
    toggleFormatMenu: jest.fn(),
  }
  const tree = (seg) => (
    <SegmentContext.Provider value={{segment: seg, ...context}}>
      <TargetEditor
        ref={ref}
        segment={seg}
        value={value}
        translation={seg.translation}
        bridge={bridge}
        {...props}
      />
    </SegmentContext.Provider>
  )
  const utils = render(tree(segment))
  return {...utils, ...props, ref, update: (seg) => utils.rerender(tree(seg))}
}

const viewOf = (container) =>
  EditorView.findFromDOM(container.querySelector('.cm-editor'))
const wrapperEl = (container) => container.querySelector('#segment-10-editarea')
const chipEls = (container) => [
  ...container.querySelectorAll('.cm-content .tag'),
]
const docOf = (container) => viewOf(container).state.doc.toString()

const flush = async (ms = 0) => {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, ms))
  })
}

const type = (view, text) =>
  act(() => {
    const {from, to} = view.state.selection.main
    view.dispatch({
      changes: {from, to, insert: text},
      selection: {anchor: from + text.length},
      userEvent: 'input.type',
    })
  })
const select = (view, anchor, head = anchor) =>
  act(() => {
    view.dispatch({selection: {anchor, head}, userEvent: 'select'})
  })
const key = (view, init) => fireEvent.keyDown(view.contentDOM, init)

beforeEach(() => {
  window.config = {...window.config, target_code: 'it-IT', isTargetRTL: false}
  Object.keys(SegmentStore.listeners).forEach(
    (k) => delete SegmentStore.listeners[k],
  )
  jest.clearAllMocks()
})

describe('TargetEditor rendering', () => {
  test('shows the translation with its tags as chips, editable, in the cattool edit area', () => {
    const {container} = renderTarget()
    expect(wrapperEl(container)).toHaveClass('targetarea', 'editarea')
    expect(wrapperEl(container)).toHaveAttribute('lang', 'it-IT')
    const view = viewOf(container)
    expect(view.state.readOnly).toBe(false)
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
    expect(chipEls(container)).toHaveLength(2)
  })

  test('a locked segment shows the plain area, read-only; unlocking makes it editable again', () => {
    const segment = makeSegment()
    const {container, rerender, ref} = renderTarget(segment, {
      context: {locked: true},
    })
    expect(wrapperEl(container)).toHaveClass('targetarea', 'area')
    expect(viewOf(container).state.readOnly).toBe(true)
    rerender(
      <SegmentContext.Provider value={{segment}}>
        <TargetEditor
          ref={ref}
          segment={segment}
          value={segment.translation}
          translation={segment.translation}
          bridge={targetEditorBridge}
          updateCounter={jest.fn()}
          toggleFormatMenu={jest.fn()}
        />
      </SegmentContext.Provider>,
    )
    expect(viewOf(container).state.readOnly).toBe(false)
  })

  test('a muted segment is read-only and takes no typing', () => {
    const {container} = renderTarget(makeSegment({muted: true}))
    const view = viewOf(container)
    expect(view.state.readOnly).toBe(true)
    key(view, {key: ' ', ctrlKey: true, shiftKey: true})
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })

  test('the editor starts from the value it is given (tags removed by tag projection)', () => {
    const {container} = renderTarget(makeSegment(), {value: 'Ciao mondo'})
    expect(docOf(container)).toBe('Ciao mondo')
    expect(chipEls(container)).toHaveLength(0)
  })
})

describe('TargetEditor store updates', () => {
  test('on mount the counter starts from the store translation, then the store is updated and the end focused', async () => {
    const {container, updateCounter} = renderTarget()
    expect(updateCounter).toHaveBeenCalledWith(10)
    expect(SegmentActions.updateTranslation).not.toHaveBeenCalled()
    await flush()
    const [sid, translation, plainText, tagMap, missing, lxq] =
      SegmentActions.updateTranslation.mock.calls[0]
    expect([sid, translation]).toEqual(['10', 'Ciao <g id="1">mondo</g>'])
    // Draft's shapes: tags as their placeholders in the plain text, tag structs, the source x missing
    expect(plainText).toBe('Ciao 1mondo1')
    expect(tagMap.map((tag) => tag.data.name)).toEqual(['g', 'gCl'])
    expect(missing.map((tag) => tag.data.encodedText)).toEqual(['<x id="2"/>'])
    expect(typeof lxq).toBe('string')
    expect(SegmentActions.startSegmentQACheck).toHaveBeenCalled()
    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith({
      type: 'updateTranslation',
      sid: '10',
      target: 'Ciao <g id="1">mondo</g>',
    })
    const view = viewOf(container)
    expect(view.state.selection.main.head).toBe(view.state.doc.length)
  })

  test('edits reach the store once, 100 ms after the last one, with the missing source tags', async () => {
    const {container, updateCounter} = renderTarget()
    await flush()
    jest.clearAllMocks()
    const view = viewOf(container)
    select(view, view.state.doc.length)
    type(view, ' a')
    type(view, 'b')
    await flush(50)
    expect(SegmentActions.updateTranslation).not.toHaveBeenCalled()
    await flush(100)
    expect(SegmentActions.updateTranslation).toHaveBeenCalledTimes(1)
    const [sid, translation, , tagMap, missing] =
      SegmentActions.updateTranslation.mock.calls[0]
    expect([sid, translation]).toEqual(['10', 'Ciao <g id="1">mondo</g> ab'])
    expect(tagMap).toHaveLength(2)
    expect(missing.map((tag) => tag.data.encodedText)).toEqual(['<x id="2"/>'])
    expect(updateCounter).toHaveBeenLastCalledWith(13)
  })

  test('an empty target only zeroes the counter', async () => {
    const {updateCounter} = renderTarget(makeSegment({translation: ''}))
    await flush()
    expect(updateCounter).toHaveBeenLastCalledWith(0)
    expect(SegmentActions.updateTranslation).not.toHaveBeenCalled()
  })

  test('a new source tag map updates the store again, without touching the text', async () => {
    const segment = makeSegment({sourceTagMap: []})
    const {container, update} = renderTarget(segment)
    await flush(150)
    jest.clearAllMocks()
    update({...segment, sourceTagMap})
    await flush(150)
    expect(SegmentActions.updateTranslation).toHaveBeenCalledTimes(1)
    expect(SegmentActions.updateTranslation.mock.calls[0][4]).toHaveLength(1)
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })
})

describe('TargetEditor store events', () => {
  test('a replaced translation becomes the content in one undo step, with the counter', async () => {
    const {container, updateCounter} = renderTarget()
    await flush()
    act(() =>
      SegmentStore.emit(SegmentConstants.REPLACE_TRANSLATION, '10', 'Nuovo'),
    )
    expect(docOf(container)).toBe('Nuovo')
    expect(updateCounter).toHaveBeenLastCalledWith(5)
    await flush(150)
    expect(SegmentActions.updateTranslation).toHaveBeenLastCalledWith(
      '10',
      'Nuovo',
      'Nuovo',
      [],
      expect.any(Array),
      expect.any(String),
    )
    key(viewOf(container), {key: 'z', ctrlKey: true})
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })

  test('a translation replaced for another segment is ignored', () => {
    const {container} = renderTarget()
    act(() =>
      SegmentStore.emit(SegmentConstants.REPLACE_TRANSLATION, '11', 'Nuovo'),
    )
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })

  test('a glossary term copied into the target goes in at the caret as text', () => {
    const {container} = renderTarget()
    const view = viewOf(container)
    select(view, 4)
    act(() =>
      SegmentStore.emit(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        {sid: '10'},
        ' <b>',
      ),
    )
    expect(docOf(container)).toBe('Ciao <b> <g id="1">mondo</g>')
    expect(chipEls(container)).toHaveLength(2)
    act(() =>
      SegmentStore.emit(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        {sid: '11'},
        'x',
      ),
    )
    expect(docOf(container)).toBe('Ciao <b> <g id="1">mondo</g>')
  })

  test('replacing the current search occurrence replaces that one only and updates the store at once', async () => {
    const segment = makeSegment({
      translation: 'Mondo <g id="1">mondo</g> mondo',
      currentInSearch: true,
      currentInSearchIndex: 7,
      searchParams: {target: 'mondo'},
      occurrencesInSearch: {
        occurrences: [
          {searchProgressiveIndex: 6},
          {searchProgressiveIndex: 7},
          {searchProgressiveIndex: 8},
        ],
      },
    })
    const {container} = renderTarget(segment)
    await flush()
    jest.clearAllMocks()
    act(() =>
      SegmentStore.emit(EditAreaConstants.REPLACE_SEARCH_RESULTS, 'terra'),
    )
    expect(docOf(container)).toBe('Mondo <g id="1">terra</g> mondo')
    expect(SegmentActions.updateTranslation).toHaveBeenCalledTimes(1)
  })

  test('a search replace with no current occurrence in this segment changes nothing', () => {
    const {container} = renderTarget(
      makeSegment({currentInSearch: false, searchParams: {target: 'mondo'}}),
    )
    act(() =>
      SegmentStore.emit(EditAreaConstants.REPLACE_SEARCH_RESULTS, 'terra'),
    )
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })

  test('a tag map refresh loads the store translation when the editor differs from it', async () => {
    const {container} = renderTarget()
    const view = viewOf(container)
    select(view, 0)
    type(view, 'X')
    act(() => SegmentStore.emit(SegmentConstants.REFRESH_TAG_MAP))
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
    act(() => SegmentStore.emit(SegmentConstants.REFRESH_TAG_MAP))
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g>')
  })

  test('new counter rules recount the current text and leave it as it is', () => {
    const {container, updateCounter} = renderTarget()
    const view = viewOf(container)
    select(view, 0)
    type(view, 'AB')
    act(() =>
      SegmentStore.emit(SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES),
    )
    expect(updateCounter).toHaveBeenLastCalledWith(12)
    expect(docOf(container)).toBe('ABCiao <g id="1">mondo</g>')
  })

  test('QA warnings mark the mismatched target tags, compressible tags never', async () => {
    const segment = makeSegment()
    const {container, update} = renderTarget(segment)
    update({...segment, tagMismatch: {target: ['</g>']}})
    act(() => SegmentStore.emit(SegmentConstants.SET_SEGMENT_WARNINGS, '10'))
    await flush(550)
    expect(
      chipEls(container).map((el) =>
        el.classList.contains('tag-mismatch-error'),
      ),
    ).toEqual([false, true])
  })
})

describe('TargetEditor tags', () => {
  test('clicking a target tag highlights it and its twin in the source, by the Draft id and placeholder', async () => {
    const {container} = renderTarget()
    fireEvent.mouseDown(chipEls(container)[0], {button: 0})
    await flush()
    expect(SegmentActions.highlightTags).toHaveBeenLastCalledWith(
      '1',
      '1',
      'cm-target-10-5',
    )
  })

  test('a highlight event from the source marks the target tags with the same id and placeholder', async () => {
    const {container} = renderTarget()
    act(() =>
      SegmentStore.emit(SegmentConstants.HIGHLIGHT_TAGS, '1', '1', 'cm-src'),
    )
    await flush()
    expect(
      chipEls(container).map((el) => el.classList.contains('tag-clicked')),
    ).toEqual([true, true])
    act(() => SegmentStore.emit(SegmentConstants.HIGHLIGHT_TAGS))
    await flush()
    expect(container.querySelectorAll('.tag-clicked')).toHaveLength(0)
  })

  test('selecting text that holds a tag shows it focused; moving the caret clears the highlight', async () => {
    const {container} = renderTarget()
    await flush()
    const view = viewOf(container)
    act(() => view.focus())
    select(view, 3, 18)
    await flush()
    expect(
      chipEls(container).map((el) => el.classList.contains('tag-focused')),
    ).toEqual([true, false])
    expect(SegmentActions.highlightTags).toHaveBeenCalledWith()
    select(view, 1)
    await flush()
    expect(container.querySelectorAll('.tag-focused')).toHaveLength(0)
  })

  test('a triple click selects the whole target', () => {
    const {container} = renderTarget()
    const view = viewOf(container)
    fireEvent.mouseDown(view.contentDOM, {button: 0, detail: 3})
    const {from, to} = view.state.selection.main
    expect([from, to]).toEqual([0, view.state.doc.length])
  })
})

describe('TargetEditor tag menu', () => {
  const menuItems = () =>
    [...document.querySelectorAll('.tag-menu-suggestion')].map(
      (el) => el.textContent,
    )
  const missing = translationUpdate('<x id="2"/>').tagMap

  test('Alt+T opens the menu with the missing tags first; the arrows move and Enter puts the tag in', async () => {
    const {container} = renderTarget(
      makeSegment({missingTagsInTarget: missing}),
    )
    await flush()
    const view = viewOf(container)
    select(view, 4)
    key(view, {key: 't', keyCode: 84, altKey: true})
    await flush(20)
    const box = container.querySelector('.tag-box')
    expect(box).toBeVisible()
    expect(screen.getByText(/Missing source/)).toBeInTheDocument()
    expect(menuItems()).toHaveLength(1 + sourceTagMap.length)
    key(view, {key: 'ArrowDown'})
    key(view, {key: 'ArrowDown'})
    key(view, {key: 'ArrowUp'})
    key(view, {key: 'Enter'})
    expect(docOf(container)).toBe('Ciao<g id="1"> <g id="1">mondo</g>')
    expect(box).not.toBeVisible()
  })

  test('typing < opens the menu and the chosen tag replaces it; Escape closes the menu leaving the <', async () => {
    const {container} = renderTarget()
    await flush()
    const view = viewOf(container)
    select(view, 4)
    key(view, {key: '<'})
    await flush(20)
    expect(docOf(container)).toBe('Ciao< <g id="1">mondo</g>')
    expect(container.querySelector('.tag-box')).toBeVisible()
    fireEvent.mouseDown(container.querySelectorAll('.tag-menu-suggestion')[2])
    expect(docOf(container)).toBe('Ciao<x id="2"/> <g id="1">mondo</g>')
    expect(chipEls(container)).toHaveLength(3)
    select(view, 0)
    key(view, {key: '<'})
    await flush(20)
    key(view, {key: 'Escape'})
    await flush()
    expect(container.querySelector('.tag-box')).not.toBeVisible()
    expect(docOf(container)).toBe('<Ciao<x id="2"/> <g id="1">mondo</g>')
  })

  test('with no source tags < is plain text and the menu stays shut', async () => {
    const {container} = renderTarget(makeSegment({sourceTagMap: []}))
    await flush()
    const view = viewOf(container)
    select(view, 0)
    key(view, {key: 't', keyCode: 84, altKey: true})
    key(view, {key: '<'})
    await flush(20)
    expect(container.querySelector('.tag-box')).not.toBeVisible()
  })
})

describe('TargetEditor handle', () => {
  test('addMissingSourceTagsToTarget appends the missing tags, then updates the store with them and runs the QA check', async () => {
    const missing = translationUpdate('<x id="2"/>').tagMap
    const segment = makeSegment({missingTagsInTarget: missing})
    const {container, ref} = renderTarget(segment)
    await flush(150)
    jest.clearAllMocks()
    act(() => ref.current.addMissingSourceTagsToTarget())
    expect(docOf(container)).toBe('Ciao <g id="1">mondo</g><x id="2"/>')
    await flush(150)
    expect(SegmentActions.updateTranslation).toHaveBeenCalledTimes(1)
    expect(SegmentActions.updateTranslation).toHaveBeenCalledWith(
      '10',
      'Ciao <g id="1">mondo</g><x id="2"/>',
      'Ciao mondo' + missing[0].data.placeholder,
      missing,
      [],
    )
    expect(SegmentActions.getSegmentsQa).toHaveBeenCalledWith(
      expect.objectContaining({
        translation: 'Ciao <g id="1">mondo</g><x id="2"/>',
      }),
    )
  })

  test('formatSelection changes the case of the selected text and leaves its tags alone', async () => {
    const {container, ref} = renderTarget()
    select(viewOf(container), 2, 21)
    act(() => ref.current.formatSelection('uppercase'))
    expect(docOf(container)).toBe('CiAO <g id="1">MONDO</g>')
    select(viewOf(container), 0, 0)
    act(() => ref.current.formatSelection('lowercase'))
    expect(docOf(container)).toBe('CiAO <g id="1">MONDO</g>')
  })

  test('getSelection gives the selection with and without tags; editAreaRef is the edit area', () => {
    const {container, ref} = renderTarget()
    select(viewOf(container), 3, 19)
    expect(ref.current.getSelection()).toEqual({
      textWithTags: 'o <g id="1">mond',
      textWithoutTags: 'o mond',
    })
    expect(ref.current.editAreaRef).toBe(wrapperEl(container))
  })

  test('the format toolbar shows on mouse up over a selection and hides on blur', () => {
    const {container, toggleFormatMenu} = renderTarget()
    select(viewOf(container), 0, 4)
    fireEvent.mouseUp(wrapperEl(container))
    expect(toggleFormatMenu).toHaveBeenLastCalledWith(true)
    select(viewOf(container), 2)
    fireEvent.keyUp(wrapperEl(container), {key: 'ArrowLeft'})
    expect(toggleFormatMenu).toHaveBeenLastCalledWith(false)
    toggleFormatMenu.mockClear()
    fireEvent.keyUp(wrapperEl(container), {key: 'a'})
    expect(toggleFormatMenu).not.toHaveBeenCalled()
    fireEvent.blur(wrapperEl(container))
    expect(toggleFormatMenu).toHaveBeenLastCalledWith(false)
  })
})

const PC_OPEN =
  '<ph id="1" ctype="x-original_pc_open" equiv-text="base64:PHBjIGlkPSIxIj4="/>'

describe('helpers', () => {
  const box = {top: 100, left: 50, right: 650}

  test('the tag menu goes under the caret, pulled left near the right edge', () => {
    expect(tagMenuPosition({top: 110, bottom: 130, left: 80}, box)).toEqual({
      top: 50,
      left: 30,
    })
    expect(tagMenuPosition({top: 110, bottom: 130, left: 500}, box)).toEqual({
      top: 50,
      left: 300,
    })
  })

  test('a caret with no geometry puts the tag menu at a fixed place', () => {
    expect(tagMenuPosition({top: 0, bottom: 0, left: 0}, box)).toEqual({
      top: 50,
      left: 50,
    })
    expect(tagMenuPosition(null, box)).toEqual({top: 50, left: 50})
  })

  test('a target tag is an error when listed as a target mismatch, a warning when out of order', () => {
    expect(tagWarningClass('</g>', {tagMismatch: {target: ['</g>']}})).toBe(
      'tag-mismatch-error',
    )
    expect(
      tagWarningClass('<x id="2"/>', {tagMismatch: {target: ['</g>']}}),
    ).toBe('')
    expect(
      tagWarningClass('<x id="2"/>', {tagMismatch: {order: ['<x id="2"/>']}}),
    ).toBe('tag-mismatch-warning')
    expect(tagWarningClass('</g>', {})).toBe('')
  })

  test('a compressible tag is never marked in the target', () => {
    expect(tagWarningClass(PC_OPEN, {tagMismatch: {target: [PC_OPEN]}})).toBe(
      '',
    )
  })
})

describe('TargetEditor show whitespace option', () => {
  const spaceMarks = (container) =>
    container.querySelectorAll('.cm-content .special-char-space').length

  test("follows the user's option, and applies a change live without remounting", async () => {
    const segment = makeSegment({translation: 'a b c'})
    const tree = (showWhitespace) => (
      <ApplicationWrapperContext.Provider
        value={{userInfo: {metadata: {show_whitespace: showWhitespace}}}}
      >
        <SegmentContext.Provider value={{segment}}>
          <TargetEditor
            segment={segment}
            value={segment.translation}
            translation={segment.translation}
            bridge={targetEditorBridge}
            updateCounter={jest.fn()}
            toggleFormatMenu={jest.fn()}
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
    const segment = makeSegment({translation: 'a b c'})
    const {container} = render(
      <SegmentContext.Provider value={{segment}}>
        <TargetEditor
          segment={segment}
          value={segment.translation}
          translation={segment.translation}
          bridge={targetEditorBridge}
          updateCounter={jest.fn()}
          toggleFormatMenu={jest.fn()}
        />
      </SegmentContext.Provider>,
    )
    await flush()
    expect(spaceMarks(container)).toBe(0)
  })
})
