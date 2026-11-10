// The target of the open segment in the CodeMirror editor: editable, wired to the segment store and actions the way
// Editarea (the Draft target) is, behind the same handle SegmentTarget and its toolbar use. Picked by SegmentTarget
// when the new editor is active, so source and target always switch together.
import React, {
  forwardRef,
  useContext,
  useEffect,
  useImperativeHandle,
  useLayoutEffect,
  useRef,
  useState,
} from 'react'
import {EditorSelection, Prec} from '@codemirror/state'
import {EditorView} from '@codemirror/view'
import {debounce, findIndex, isEqual} from 'lodash'

import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentConstants from '../../../constants/SegmentConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import ContextPreviewChannel from '../../../utils/contextPreviewChannel'
import TextUtils from '../../../utils/textUtils'
import {classifyCompressibleTag} from '../../../utils/segmentText/compressibleTagUtils'
import {SegmentContext} from '../../segments/SegmentContext'
import TagBox from '../../segments/TagMenu/TagBox'
import {createSegmentEditor} from '../core/createSegmentEditor'
import {insertRaw} from '../core/docModel'
import {chipTokens, setTagState} from '../core/tags'
import {chipsWithIdentity, highlightedChips} from './SourceEditor'

const MENU_MIN_WIDTH = 300

/**
 * Where the tag menu goes, relative to the editor box, for the caret at `coords` (viewport based): under the caret,
 * pulled left when the caret is too close to the right edge for the menu, at 50/50 when the caret has no geometry.
 */
export const tagMenuPosition = (coords, box, minWidth = MENU_MIN_WIDTH) => {
  if (!coords || (!coords.bottom && !coords.left && !coords.top))
    return {top: 50, left: 50}
  const left = coords.left - box.left
  const room = box.right - coords.left
  return {
    top: coords.bottom - box.top + (coords.bottom - coords.top),
    left: room < minWidth ? left - (minWidth - room) : left,
  }
}

/**
 * The warning class of a target tag (`raw`, its markup), as the Draft tag shows it: an error when the QA check lists
 * it as a target mismatch, a warning when it lists it as out of order. Compressible (pc) tags are never marked in
 * the target.
 */
export const tagWarningClass = (raw, {tagMismatch} = {}) => {
  if (classifyCompressibleTag(raw)?.kind === 'pc' || !tagMismatch) return ''
  if (tagMismatch.target?.length > 0)
    return tagMismatch.target.includes(raw) ? 'tag-mismatch-error' : ''
  if (tagMismatch.order?.includes(raw)) return 'tag-mismatch-warning'
  return ''
}

// The menu entries in the order the menu shows them: tags missing from the target first.
const menuEntries = ({missingTags = [], sourceTags = []}) => [
  ...missingTags,
  ...sourceTags,
]

const CLOSED_MENU = {open: false, suggestions: {}, focused: 0, position: {}}

const isArrowKey = (key) =>
  key === 'ArrowLeft' ||
  key === 'ArrowRight' ||
  key === 'ArrowUp' ||
  key === 'ArrowDown'

/**
 * @param {object} props
 * @param {object} props.segment the segment from the store
 * @param {string} props.value the segment string the editor starts with (tags already removed by tag projection)
 * @param {string} props.translation the translation of the store, which the counter starts from
 * @param {(counter: number) => void} props.updateCounter
 * @param {(show: boolean) => void} props.toggleFormatMenu
 * @param {object} props.bridge targetEditorBridge: what the store and the counter need computed by Draft
 */
const TargetEditor = forwardRef(
  (
    {segment, value, translation, updateCounter, toggleFormatMenu, bridge},
    ref,
  ) => {
    const context = useContext(SegmentContext)

    const parentRef = useRef(null)
    const wrapperRef = useRef(null)
    const editorRef = useRef(null)
    // Set while the editor changes for addMissingSourceTagsToTarget, which updates the store itself.
    const silentRef = useRef(false)
    const highlightRef = useRef([])
    const focusRef = useRef([])
    const warningRef = useRef([])

    const [menu, setMenu] = useState(CLOSED_MENU)

    const readOnly = Boolean(
      context.readonly || context.locked || segment.muted || !segment.opened,
    )

    // Read by the editor callbacks and store listeners, which are created once.
    const latestRef = useRef({})
    latestRef.current = {
      segment,
      translation,
      updateCounter,
      toggleFormatMenu,
      bridge,
      menu,
    }

    const counterOf = (str) =>
      latestRef.current.bridge.charactersCounter(
        str,
        CatToolStore.getCurrentProjectTemplate().characterCounterCountTags,
      )

    // The store update after an edit, as Editarea's updateTranslationInStore.
    const updateTranslationInStore = () => {
      const editor = editorRef.current
      if (!editor) return
      const {segment, bridge, updateCounter} = latestRef.current
      const str = editor.getValue()
      if (str === '') {
        updateCounter(0)
        return
      }
      const {plainText, tagMap, missingTags, lxqDecodedTranslation} =
        bridge.translationUpdate(str, segment.sourceTagMap)
      SegmentActions.updateTranslation(
        segment.sid,
        str,
        plainText,
        tagMap,
        missingTags,
        lxqDecodedTranslation,
      )
      ContextPreviewChannel.sendMessage({
        type: 'updateTranslation',
        sid: segment.sid,
        target: str,
      })
      updateCounter(counterOf(str))
      SegmentActions.startSegmentQACheck()
    }

    const chips = () => {
      const {bridge, segment} = latestRef.current
      const {state} = editorRef.current.view
      return chipsWithIdentity(
        state,
        bridge.tagIdentities(
          editorRef.current.getValue(),
          segment.sourceTagMap,
        ),
        `cm-target-${segment.sid}`,
      )
    }

    const applyTagState = () => {
      const editor = editorRef.current
      if (!editor) return
      const byRange = new Map()
      ;[
        ...highlightRef.current,
        ...focusRef.current,
        ...warningRef.current,
      ].forEach((r) => {
        const k = `${r.from}-${r.to}`
        const prev = byRange.get(k)
        byRange.set(
          k,
          prev ? {...prev, className: `${prev.className} ${r.className}`} : r,
        )
      })
      editor.view.dispatch({effects: setTagState.of([...byRange.values()])})
    }

    const refreshWarnings = () => {
      if (!editorRef.current) return
      const {segment} = latestRef.current
      warningRef.current = chipTokens(editorRef.current.view.state)
        .map(({from, to, token}) => ({
          from,
          to,
          className: tagWarningClass(token.raw, segment),
        }))
        .filter((r) => r.className)
      applyTagState()
    }

    // The chips covered by a selection look focused while the editor has the focus.
    const focusSelectedChips = (view) => {
      const {from, to, empty} = view.state.selection.main
      focusRef.current =
        empty || !view.hasFocus
          ? []
          : chipTokens(view.state)
              .filter((c) => c.from >= from && c.to <= to)
              .map((c) => ({from: c.from, to: c.to, className: 'tag-focused'}))
    }

    const onTagClick = ({from}) => {
      const {segment} = latestRef.current
      const chip = chips().find((c) => c.from === from)
      const identity = chip?.identity ?? {}
      if (segment.openSplit) return
      setTimeout(() =>
        SegmentActions.highlightTags(
          identity.id ?? null,
          identity.placeholder ?? null,
          chip?.key ?? `cm-target-${segment.sid}-${from}`,
        ),
      )
    }

    // ---- tag menu ----
    const onMenuChange = (state) => {
      // Closed, the menu keeps its entries, as TagBox expects
      if (!state) {
        setMenu((current) => ({...current, open: false}))
        return
      }
      const {segment} = latestRef.current
      setMenu({
        open: true,
        suggestions: {
          missingTags: segment.missingTagsInTarget,
          sourceTags: segment.sourceTagMap,
        },
        focused: 0,
        position: tagMenuPosition(
          state.coords,
          editorRef.current.view.dom.getBoundingClientRect(),
        ),
      })
    }

    const acceptMenuTag = (tag) => {
      if (tag?.data?.encodedText)
        editorRef.current.insertMenuTag(tag.data.encodedText)
    }

    const onMenuKey = (key) => {
      const {menu} = latestRef.current
      const entries = menuEntries(menu.suggestions)
      if (key === 'enter') {
        acceptMenuTag(entries[menu.focused])
        return
      }
      if (!entries.length) return
      const step = key === 'up' ? -1 : 1
      setMenu((current) => ({
        ...current,
        focused: (current.focused + step + entries.length) % entries.length,
      }))
    }

    const eventsRef = useRef(null)
    eventsRef.current = {
      updateTranslationInStore,
      onTagClick,
      focusSelectedChips,
      applyTagState,
      onMenuChange,
      onMenuKey,
    }

    // Debounced once: recreating it would drop the pending update.
    const [updateTranslationDebounced] = useState(() =>
      debounce(() => eventsRef.current.updateTranslationInStore(), 100),
    )
    const [refreshWarningsDebounced] = useState(() =>
      debounce(() => refreshWarnings(), 500),
    )

    useEffect(() => {
      const editor = createSegmentEditor({
        parent: parentRef.current,
        value,
        mode: 'target',
        lang: config.target_code,
        dir: config.isTargetRTL ? 'rtl' : 'ltr',
        readOnly,
        onChange: () => {
          if (!silentRef.current) updateTranslationDebounced()
        },
        onEvent: (name, payload) => {
          if (name === 'tagClick') eventsRef.current.onTagClick(payload)
        },
        tagMenu: {
          canOpen: () => latestRef.current.segment.sourceTagMap?.length > 0,
          onChange: (state) => eventsRef.current.onMenuChange(state),
          onKey: (key) => eventsRef.current.onMenuKey(key),
        },
        extensions: [
          Prec.highest(
            EditorView.domEventHandlers({
              // A triple click selects the whole target, not only the clicked line
              mousedown: (event, view) => {
                if (event.detail < 3 || event.button !== 0) return false
                event.preventDefault()
                view.dispatch({
                  selection: EditorSelection.range(0, view.state.doc.length),
                })
                return true
              },
            }),
          ),
          EditorView.updateListener.of((update) => {
            if (!update.selectionSet && !update.focusChanged) return
            const {state} = update
            const byUser = update.transactions.some(
              (tr) => tr.isUserEvent('select') || tr.isUserEvent('input'),
            )
            if (byUser) {
              const {from, to} = state.selection.main
              const onChip = chipTokens(state).some(
                (c) => c.from === from && c.to === to,
              )
              if (!onChip) setTimeout(() => SegmentActions.highlightTags())
            }
            eventsRef.current.focusSelectedChips(update.view)
            // Dispatching from an update listener is not allowed: after it.
            setTimeout(() => eventsRef.current.applyTagState())
          }),
        ],
      })
      editorRef.current = editor
      const timer = setTimeout(() => {
        eventsRef.current.updateTranslationInStore()
        if (latestRef.current.segment.opened) editor.focus('end')
      })
      return () => {
        clearTimeout(timer)
        updateTranslationDebounced.cancel()
        refreshWarningsDebounced.cancel()
        editorRef.current = null
        editor.destroy()
      }
      // Mounted once per segment, as Editarea builds its editor state once.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    // The counter starts from the store translation, before paint.
    useLayoutEffect(() => {
      updateCounter(counterOf(translation))
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    // Store events, as Editarea listens to them.
    useEffect(() => {
      const replaceTranslation = (sid, str) => {
        const editor = editorRef.current
        if (!editor || sid !== latestRef.current.segment.sid) return
        editor.setValue(str)
        latestRef.current.updateCounter(counterOf(str))
        updateTranslationDebounced()
      }
      const replaceSearchResult = (text) => {
        const editor = editorRef.current
        const {segment} = latestRef.current
        const {searchParams, occurrencesInSearch, currentInSearchIndex} =
          segment
        if (!editor || !segment.currentInSearch || !searchParams?.target) return
        const index = findIndex(
          occurrencesInSearch?.occurrences,
          (item) => item.searchProgressiveIndex === currentInSearchIndex,
        )
        const regex = new RegExp(
          TextUtils.escapeRegExp(searchParams.target),
          'gi',
        )
        if (editor.replaceOccurrence(regex, index, text)) {
          updateTranslationDebounced.cancel()
          eventsRef.current.updateTranslationInStore()
        }
      }
      const copyGlossary = (glossarySegment, text) => {
        if (
          !editorRef.current ||
          glossarySegment?.sid !== latestRef.current.segment.sid
        )
          return
        editorRef.current.insertText(text)
      }
      // A refreshed tag map rebuilds the chips; the translation of the store is loaded when it differs.
      const refreshTagMap = () => {
        const editor = editorRef.current
        if (!editor) return
        const {translation} = latestRef.current
        if (translation !== undefined && translation !== editor.getValue())
          editor.setValue(translation)
        else eventsRef.current.applyTagState()
        latestRef.current.updateCounter(counterOf(editor.getValue()))
        updateTranslationDebounced()
      }
      const refreshCounter = () => {
        if (!editorRef.current) return
        latestRef.current.updateCounter(counterOf(editorRef.current.getValue()))
      }
      const onHighlight = (tagId, tagPlaceholder, key) => {
        if (!editorRef.current) return
        highlightRef.current = highlightedChips(chips(), {
          tagId,
          tagPlaceholder,
          key,
        })
        applyTagState()
      }
      SegmentStore.addListener(
        SegmentConstants.REPLACE_TRANSLATION,
        replaceTranslation,
      )
      SegmentStore.addListener(
        EditAreaConstants.REPLACE_SEARCH_RESULTS,
        replaceSearchResult,
      )
      SegmentStore.addListener(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        copyGlossary,
      )
      SegmentStore.addListener(SegmentConstants.REFRESH_TAG_MAP, refreshTagMap)
      SegmentStore.addListener(
        SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
        refreshCounter,
      )
      SegmentStore.addListener(SegmentConstants.HIGHLIGHT_TAGS, onHighlight)
      SegmentStore.addListener(
        SegmentConstants.SET_SEGMENT_WARNINGS,
        refreshWarningsDebounced,
      )
      return () => {
        SegmentStore.removeListener(
          SegmentConstants.REPLACE_TRANSLATION,
          replaceTranslation,
        )
        SegmentStore.removeListener(
          EditAreaConstants.REPLACE_SEARCH_RESULTS,
          replaceSearchResult,
        )
        SegmentStore.removeListener(
          EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
          copyGlossary,
        )
        SegmentStore.removeListener(
          SegmentConstants.REFRESH_TAG_MAP,
          refreshTagMap,
        )
        SegmentStore.removeListener(
          SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
          refreshCounter,
        )
        SegmentStore.removeListener(
          SegmentConstants.HIGHLIGHT_TAGS,
          onHighlight,
        )
        SegmentStore.removeListener(
          SegmentConstants.SET_SEGMENT_WARNINGS,
          refreshWarningsDebounced,
        )
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    // A new source tag map changes the missing tags and the tag identities: the store is updated again.
    const prevSourceTagMapRef = useRef(null)
    useEffect(() => {
      const {sourceTagMap} = segment
      if (
        !sourceTagMap?.length ||
        isEqual(prevSourceTagMapRef.current, sourceTagMap)
      )
        return
      prevSourceTagMapRef.current = sourceTagMap
      if (!editorRef.current) return
      updateCounter(counterOf(editorRef.current.getValue()))
      updateTranslationDebounced()
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [segment.sourceTagMap])

    // Locked, muted or closed: read-only.
    useEffect(() => {
      editorRef.current?.setReadOnly(readOnly)
    }, [readOnly])

    // Opening the segment focuses the target at the end.
    const prevOpenedRef = useRef(segment.opened)
    useEffect(() => {
      const wasOpened = prevOpenedRef.current
      prevOpenedRef.current = segment.opened
      if (!wasOpened && segment.opened) editorRef.current?.focus('end')
    }, [segment.opened])

    // Appends the source tags missing from the target, then updates the store and runs the QA check, as Editarea.
    const addMissingSourceTagsToTarget = () => {
      const editor = editorRef.current
      if (!editor) return
      const {segment} = latestRef.current
      const missing = segment.missingTagsInTarget ?? []
      const raw = missing.map((tag) => tag.data.encodedText).join('')
      const end = editor.view.state.doc.length
      silentRef.current = true
      editor.view.dispatch(insertRaw(editor.view.state, end, end, raw), {
        userEvent: 'input',
      })
      silentRef.current = false
      const newTranslation = segment.translation + raw
      const newDecodedTranslation =
        segment.decodedTranslation +
        missing.map((tag) => tag.data.placeholder).join('')
      setTimeout(() => {
        SegmentActions.updateTranslation(
          segment.sid,
          newTranslation,
          newDecodedTranslation,
          [...(segment.targetTagMap ?? []), ...missing],
          [],
        )
        SegmentActions.getSegmentsQa({
          ...latestRef.current.segment,
          translation: newTranslation,
        })
      }, 100)
    }

    // The toolbar's upper case, lower case and capitalize, applied to the selected text and never to its tags.
    const formatSelection = (format) => {
      editorRef.current?.transformSelection((text) =>
        latestRef.current.bridge.formatText(text, format),
      )
    }

    // Editarea's handle: SegmentTarget, the toolbar and AiAlternatives reach the editor through it.
    const handleRef = useRef(null)
    handleRef.current = {addMissingSourceTagsToTarget, formatSelection}
    useImperativeHandle(
      ref,
      () => ({
        addMissingSourceTagsToTarget: (...args) =>
          handleRef.current.addMissingSourceTagsToTarget(...args),
        formatSelection: (...args) =>
          handleRef.current.formatSelection(...args),
        /** The selection as `{textWithTags, textWithoutTags}`, what AiAlternatives offers to Lara. */
        getSelection: () =>
          editorRef.current?.getSelection() ?? {
            textWithTags: '',
            textWithoutTags: '',
          },
        get editAreaRef() {
          return wrapperRef.current
        },
      }),
      [],
    )

    const showFormatMenu = () => {
      const editor = editorRef.current
      if (editor)
        latestRef.current.toggleFormatMenu(
          !editor.view.state.selection.main.empty,
        )
    }

    const onBlur = () => {
      latestRef.current.toggleFormatMenu(false)
      focusRef.current = []
      setTimeout(() => applyTagState())
    }

    const locked = context.locked || context.readonly

    return (
      <div
        className={`targetarea ${locked ? 'area' : 'editarea'}`}
        ref={wrapperRef}
        id={'segment-' + segment.sid + '-editarea'}
        data-sid={segment.sid}
        tabIndex="-1"
        lang={config.target_code}
        onMouseUp={showFormatMenu}
        onKeyUp={(event) => {
          if (isArrowKey(event.key)) showFormatMenu()
        }}
        onBlur={onBlur}
      >
        <div ref={parentRef} className="target-editor" />
        <TagBox
          displayPopover={menu.open}
          suggestions={menu.suggestions}
          onTagClick={acceptMenuTag}
          focusedTagIndex={menu.focused}
          popoverPosition={menu.position}
        />
      </div>
    )
  },
)

TargetEditor.displayName = 'TargetEditor'

export default TargetEditor
