// The segment editor: a CodeMirror 6 view over one segment string. No app state: everything the host needs goes
// through the options and the returned api.
import {Compartment, EditorSelection, EditorState} from '@codemirror/state'
import {EditorView} from '@codemirror/view'
import {isolateHistory, redo, undo} from '@codemirror/commands'
import {
  insertContent,
  insertRaw,
  selectionText,
  serialize,
  tagRangeSet,
  tagRanges,
  toDoc,
} from './docModel'
import {tagExtensions} from './tags'
import {displayExtensions, setShowWhitespaceEffect} from './display'
import {keymapExtensions} from './keymap'
import {clipboardExtensions} from './clipboard'
import {historyExtensions} from './history'
import {layerExtensions} from './layers'
import {closeTagMenu, insertMenuTag, tagMenuExtensions} from './tagMenu'
import {replaceOccurrenceSpec, transformSelectionSpec} from './commands'

const theme = EditorView.theme({
  '&': {fontFamily: 'inherit', fontSize: 'inherit'},
  '&.cm-focused': {outline: 'none'},
  // The editor grows with its text, like the Draft editors, and never scrolls inside itself: with the default
  // `overflow: auto` the cattool tooltips of the marks on the first line were cut off.
  '.cm-scroller': {
    fontFamily: 'inherit',
    lineHeight: 'inherit',
    overflow: 'visible',
  },
  '.cm-content': {padding: '0', caretColor: 'auto'},
  '.cm-line': {padding: '0'},
})

// Read-only can change while the editor lives (a target that gets locked): it sits in a compartment.
const readOnlyCompartment = new Compartment()

/**
 * The extensions of a segment editor state. Exported so the state can be built and tested without a view.
 * @param {object} options see `createSegmentEditor`
 */
export const segmentEditorExtensions = ({
  mode = 'target',
  lang,
  dir = 'ltr',
  readOnly = mode === 'source',
  showWhitespace = false,
  onEvent = () => {},
  tagMenu,
  extensions = [],
} = {}) => {
  // The target keeps its editing extensions while read-only, so it can be made editable again.
  const editable = mode === 'target' || !readOnly
  return [
    tagExtensions({dir, onEvent}),
    layerExtensions(),
    displayExtensions({showWhitespace}),
    editable ? tagMenuExtensions(tagMenu) : [],
    keymapExtensions({editable}),
    clipboardExtensions({editable}),
    editable ? historyExtensions() : [],
    readOnlyCompartment.of(EditorState.readOnly.of(readOnly)),
    EditorView.lineWrapping,
    EditorView.contentAttributes.of({
      spellcheck: 'true',
      ...(lang ? {lang} : {}),
      dir,
    }),
    theme,
    extensions,
  ]
}

/** An editor state holding `value` (a segment string). */
export const createSegmentState = (value, options) => {
  const {text, tags} = toDoc(value ?? '')
  return EditorState.create({
    doc: text,
    extensions: [
      tagRanges.init(() => tagRangeSet(tags)),
      segmentEditorExtensions(options),
    ],
  })
}

/**
 * @param {object} options
 * @param {HTMLElement} options.parent element the editor mounts in
 * @param {string} [options.value] segment string
 * @param {'source'|'target'} [options.mode] the source is read-only unless `readOnly` says otherwise
 * @param {string} [options.lang]
 * @param {'ltr'|'rtl'} [options.dir]
 * @param {boolean} [options.readOnly]
 * @param {boolean} [options.showWhitespace] mark spaces (display only)
 * @param {(value: string) => void} [options.onChange] the segment string after every document change
 * @param {(name: string, payload: object) => void} [options.onEvent] `tagClick`, `tagHover` (with `truncated`, the
 *   full `label` and the chip `rect`) and `tagLeave`
 * @param {object} [options.tagMenu] `canOpen`, `onChange`, `onKey` of the tag menu (see tagMenu.js)
 * @param {import('@codemirror/state').Extension} [options.extensions] added to the editor, kept across `setValue`
 */
export const createSegmentEditor = ({
  parent,
  value = '',
  onChange,
  ...options
}) => {
  const notify = EditorView.updateListener.of((update) => {
    if (update.docChanged && onChange) onChange(serialize(update.state))
  })
  const stateOptions = {
    ...options,
    extensions: [notify, options.extensions ?? []],
  }
  const view = new EditorView({
    parent,
    state: createSegmentState(value, stateOptions),
  })

  const replaceSelection = (content) => {
    const {from, to} = view.state.selection.main
    view.dispatch(insertContent(view.state, from, to, content), {
      userEvent: 'input',
      scrollIntoView: true,
    })
  }

  const dispatchSpec = (spec, userEvent) => {
    if (!spec || view.state.readOnly) return false
    view.dispatch(spec, {userEvent})
    return true
  }

  return {
    view,
    /** The current segment string. */
    getValue: () => serialize(view.state),
    /**
     * Replaces the whole content. `undoable` (default): one history step, caret at the end. Otherwise the editor
     * starts over with an empty history (loading a segment).
     */
    setValue(str, {undoable = true} = {}) {
      if (!undoable) {
        view.setState(createSegmentState(str, stateOptions))
        return
      }
      const spec = insertRaw(view.state, 0, view.state.doc.length, str ?? '')
      view.dispatch(spec, {
        annotations: isolateHistory.of('full'),
        userEvent: 'set',
      })
    },
    /** Makes the editor read-only or editable again; kept across `setValue`. */
    setReadOnly(readOnly) {
      stateOptions.readOnly = readOnly
      view.dispatch({
        effects: readOnlyCompartment.reconfigure(
          EditorState.readOnly.of(readOnly),
        ),
      })
    },
    /** Turns the space marks on or off (display only); kept across `setValue`. */
    setShowWhitespace(showWhitespace) {
      stateOptions.showWhitespace = showWhitespace
      view.dispatch({effects: setShowWhitespaceEffect(showWhitespace)})
    },
    undo: () => undo(view),
    redo: () => redo(view),
    /** Focuses the editor; `pos` `'start'` or `'end'` also moves the caret. */
    focus(pos) {
      view.focus()
      if (pos === 'start' || pos === 'end') {
        const at = pos === 'start' ? 0 : view.state.doc.length
        view.dispatch({selection: EditorSelection.cursor(at)})
      }
    },
    /** The selection as a segment string and as plain text without tags. */
    getSelection: () => selectionText(view.state),
    /** Inserts plain text at the selection: markup in it stays text. */
    insertText: (text) => replaceSelection({text, tags: []}),
    /** Inserts a segment string at the selection: its tags become tags. */
    insertRaw: (raw) => replaceSelection(toDoc(raw)),
    /** Replaces the selected text, between its tags, with `fn(text)`. False when nothing is selected. */
    transformSelection: (fn) =>
      dispatchSpec(transformSelectionSpec(view.state, fn), 'input'),
    /** Replaces the `index`-th match of `regex` in the text between tags. False when there is none. */
    replaceOccurrence: (regex, index, text) =>
      dispatchSpec(
        replaceOccurrenceSpec(view.state, regex, index, text),
        'input.replace',
      ),
    /** Puts the tag `raw` in from the open tag menu (over the `<` that opened it) and closes the menu. */
    insertMenuTag: (raw) => insertMenuTag(view, raw),
    closeTagMenu: () => closeTagMenu(view),
    destroy: () => view.destroy(),
  }
}
