// The source of the open segment in the CodeMirror editor: read-only, wired to the segment store and actions the way
// SegmentSource (the Draft source) is. Picked by SegmentWrapper when the build enables the new editor.
import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {EditorSelection, Prec} from '@codemirror/state'
import {EditorView} from '@codemirror/view'

import SegmentStore from '../../../stores/SegmentStore'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentConstants from '../../../constants/SegmentConstants'
import {Shortcuts} from '../../../utils/shortcuts'
import {UseHotKeysComponent} from '../../../hooks/UseHotKeysComponent'
import {SegmentContext} from '../../segments/SegmentContext'
import {TERM_FORM_FIELDS} from '../../segments/SegmentFooterTabGlossary/GlossaryConstants'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import Assistant from '../../../../img/icons/Assistant'
import Education from '../../../../img/icons/Education'
import {createSegmentEditor} from '../core/createSegmentEditor'
import {serialize} from '../core/docModel'
import {SEARCH_LAYER, setLayer} from '../core/layers'
import {chipTokens, setTagState} from '../core/tags'
import {searchRanges} from './layers/search'
import {glossaryRanges, qaCheckGlossaryRanges} from './layers/glossary'
import {lexiqaRanges} from './layers/lexiqa'
import {icuRanges} from './layers/icu'
import {layerTooltipExtensions} from './layerTooltip'

export const SPLIT_PLACEHOLDER = '##$_SPLIT$##'

/** Whether the AI assistant takes `phrase`: up to 3 words, 6 Chinese or 10 Japanese characters. */
export const isValidPhraseToAiAssistant = ({
  phrase,
  sourceLanguageCode = config.source_code,
}) => {
  if (!phrase) return false
  const limits = {
    'zh-CN': (value) => value.split('').length <= 6,
    'zh-TW': (value) => value.split('').length <= 6,
    'zh-HK': (value) => value.split('').length <= 6,
    'zh-MO': (value) => value.split('').length <= 6,
    'ja-JP': (value) => value.split('').length <= 10,
  }
  const check = limits[sourceLanguageCode] ?? ((v) => v.split(' ').length <= 3)
  return check(phrase)
}

/**
 * The source of a split group joined back, a split point between each part, as SegmentSource shows it when a
 * segment that was split enters split mode.
 */
export const joinSplitGroup = (splitGroup) =>
  splitGroup
    .map((sid) => SegmentStore.getSegmentByIdToJS(sid).segment)
    .join(SPLIT_PLACEHOLDER)

/** Chip classes for a HIGHLIGHT_TAGS event: the clicked chip and every chip with the same Draft id and placeholder. */
export const highlightedChips = (chips, {tagId, tagPlaceholder, key}) =>
  chips
    .filter(
      (chip) =>
        chip.key === key ||
        (tagId &&
          chip.identity?.id === tagId &&
          chip.identity?.placeholder === tagPlaceholder),
    )
    .map(({from, to}) => ({from, to, className: 'tag-clicked'}))

/**
 * Each chip with its Draft identity (the n-th chip with some markup is the n-th Draft tag with that markup) and the
 * key a highlight event names it by: `<keyPrefix>-<from>`.
 */
export const chipsWithIdentity = (state, identities, keyPrefix) => {
  const seen = {}
  return chipTokens(state).map(({from, to, token}) => {
    const n = (seen[token.raw] = (seen[token.raw] ?? -1) + 1)
    const identity = identities.filter(({raw}) => raw === token.raw)[n]
    return {from, to, token, identity, key: `${keyPrefix}-${from}`}
  })
}

const isArrowKey = (key) =>
  key === 'ArrowLeft' ||
  key === 'ArrowRight' ||
  key === 'ArrowUp' ||
  key === 'ArrowDown'

/**
 * @param {object} props
 * @param {object} props.segment the segment from the store
 * @param {string} props.value the segment string to show (tags already removed when tag projection is on)
 * @param {{publishSource: (sid: string, source: string) => void,
 *   tagIdentities: (source: string) => Array<{raw: string, id: string, placeholder: string}>}} [props.bridge]
 *   what the target editor needs from the source: the store update after a load, and the tag identities a tag
 *   click highlights in the target
 */
const SourceEditor = ({segment, value, bridge}) => {
  const {segment: contextSegment, userInfo} = useContext(SegmentContext)

  const parentRef = useRef(null)
  // Set by a click on a chip, so the click that follows it does not also add a split point.
  const chipClickedRef = useRef(false)
  const editorRef = useRef(null)
  const delayAiAssistantRef = useRef()
  const layerKeyRef = useRef(null)
  const highlightRef = useRef([])
  const focusRef = useRef([])

  // Fixed for the life of the segment, as in SegmentSource: what split mode goes back to, and data-original.
  const [initialValue] = useState(() => value)
  const [originalSource] = useState(() => segment.segment)
  const [splitCount, setSplitCount] = useState(() =>
    segment.split_group ? segment.split_group.length - 1 : 0,
  )
  // The selection toolbar and the words it acts on, read when a selection ends (mouse up, arrow keys).
  const [toolbar, setToolbar] = useState({visible: false, words: ''})

  // Read by the editor callbacks and store listeners, which are created once.
  const latestRef = useRef({})
  latestRef.current = {segment, contextSegment, userInfo, value, bridge}

  const applyTagState = () => {
    const editor = editorRef.current
    if (!editor) return
    const byRange = new Map()
    ;[...highlightRef.current, ...focusRef.current].forEach((r) => {
      const k = `${r.from}-${r.to}`
      const prev = byRange.get(k)
      byRange.set(
        k,
        prev ? {...prev, className: `${prev.className} ${r.className}`} : r,
      )
    })
    editor.view.dispatch({effects: setTagState.of([...byRange.values()])})
  }

  const chips = () => {
    const {bridge, segment} = latestRef.current
    const state = editorRef.current.view.state
    return chipsWithIdentity(
      state,
      bridge?.tagIdentities?.(serialize(state)) ?? [],
      `cm-source-${segment.sid}`,
    )
  }

  // The decoration layers, recomputed when what they show changes. Search hides the others while it is on.
  const refreshLayers = useCallback((force = false) => {
    const editor = editorRef.current
    if (!editor) return
    const {segment} = latestRef.current
    const {state} = editor.view
    const key = JSON.stringify([
      state.doc.length,
      segment.inSearch,
      segment.currentInSearch,
      segment.currentInSearchIndex,
      segment.searchParams,
      segment.glossary,
      segment.lexiqa?.source,
      segment.lxqDecodedSource,
      segment.icu,
    ])
    if (!force && key === layerKeyRef.current) return
    layerKeyRef.current = key
    const inSearch = segment.inSearch && segment.searchParams?.source
    const layers = {
      [SEARCH_LAYER]: inSearch ? searchRanges(state, segment) : [],
      glossary: inSearch ? [] : glossaryRanges(state, segment),
      qaCheckGlossary: inSearch ? [] : qaCheckGlossaryRanges(state, segment),
      lexiqa: inSearch ? [] : lexiqaRanges(state, segment),
      icu: segment.icu && !inSearch ? icuRanges(state, config.source_code) : [],
    }
    editor.view.dispatch({
      effects: Object.entries(layers).map(([name, ranges]) =>
        setLayer.of({name, ranges}),
      ),
    })
  }, [])

  const publish = useCallback(() => {
    const {bridge, segment} = latestRef.current
    if (editorRef.current) {
      bridge?.publishSource?.(segment.sid, editorRef.current.getValue())
    }
  }, [])

  // Loads a segment string: a fresh document and history, the layers rebuilt.
  const load = useCallback(
    (str) => {
      editorRef.current.setValue(str, {undoable: false})
      highlightRef.current = []
      focusRef.current = []
      refreshLayers(true)
    },
    [refreshLayers],
  )

  const changeSplitCount = (step) => {
    if (latestRef.current.segment.splitted) return
    setSplitCount((count) => count + step)
  }

  const onTagClick = ({name, from, to}) => {
    const {contextSegment, segment} = latestRef.current
    chipClickedRef.current = true
    if (contextSegment.openSplit) {
      if (name === 'splitPoint') {
        editorRef.current.view.dispatch({
          changes: {from, to},
          selection: EditorSelection.cursor(from),
        })
        changeSplitCount(-1)
      }
      return
    }
    const chip = chips().find((c) => c.from === from)
    const identity = chip?.identity ?? {}
    setTimeout(() =>
      SegmentActions.highlightTags(
        identity.id ?? null,
        identity.placeholder ?? null,
        chip?.key ?? `cm-source-${segment.sid}-${from}`,
      ),
    )
  }

  // The chips covered by a selection look focused, as the Draft source shows the tags it selects.
  const focusSelectedChips = (state) => {
    const {from, to, empty} = state.selection.main
    focusRef.current = empty
      ? []
      : chipTokens(state)
          .filter((c) => c.from >= from && c.to <= to)
          .map((c) => ({from: c.from, to: c.to, className: 'tag-focused'}))
  }

  const eventsRef = useRef(null)
  eventsRef.current = {onTagClick, focusSelectedChips, applyTagState}

  useEffect(() => {
    const editor = createSegmentEditor({
      parent: parentRef.current,
      value,
      mode: 'source',
      lang: config.source_code,
      dir: config.isSourceRTL ? 'rtl' : 'ltr',
      onEvent: (name, payload) => {
        if (name === 'tagClick') eventsRef.current.onTagClick(payload)
      },
      extensions: [
        layerTooltipExtensions(),
        Prec.highest(
          EditorView.domEventHandlers({
            // The source is never cut, not even as a copy (SegmentSource swallows cut)
            cut: (event) => {
              event.preventDefault()
              return true
            },
            // Triple click selects the whole source, not only the clicked line
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
          if (!update.selectionSet) return
          const {state} = update
          if (update.transactions.some((tr) => tr.isUserEvent('select'))) {
            const {from, to} = state.selection.main
            const onChip = chipTokens(state).some(
              (c) => c.from === from && c.to === to,
            )
            if (!onChip) setTimeout(() => SegmentActions.highlightTags())
          }
          eventsRef.current.focusSelectedChips(state)
          // Dispatching from an update listener is not allowed: after it.
          setTimeout(() => eventsRef.current.applyTagState())
        }),
      ],
    })
    editorRef.current = editor
    refreshLayers(true)
    const timer = setTimeout(publish)
    return () => {
      clearTimeout(timer)
      clearTimeout(delayAiAssistantRef.current)
      editorRef.current = null
      editor.destroy()
    }
    // Mounted once per segment, as SegmentSource builds its editor state once.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Store events: tag map refreshes and Guess tag reload the source; tag highlights come from either editor.
  useEffect(() => {
    const reload = () => {
      if (!editorRef.current) return
      load(latestRef.current.value)
      publish()
    }
    const onTagged = (sid) => {
      if (sid === latestRef.current.segment.sid) setTimeout(reload)
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
    const onCloseSplit = () => {
      const {contextSegment} = latestRef.current
      setSplitCount(
        contextSegment.split_group ? contextSegment.split_group.length - 1 : 0,
      )
      if (contextSegment.openSplit && editorRef.current) load(initialValue)
    }
    SegmentStore.addListener(SegmentConstants.REFRESH_TAG_MAP, reload)
    SegmentStore.addListener(SegmentConstants.SET_SEGMENT_TAGGED, onTagged)
    SegmentStore.addListener(SegmentConstants.HIGHLIGHT_TAGS, onHighlight)
    SegmentStore.addListener(SegmentConstants.CLOSE_SPLIT_SEGMENT, onCloseSplit)
    return () => {
      SegmentStore.removeListener(SegmentConstants.REFRESH_TAG_MAP, reload)
      SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_TAGGED, onTagged)
      SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_TAGS, onHighlight)
      SegmentStore.removeListener(
        SegmentConstants.CLOSE_SPLIT_SEGMENT,
        onCloseSplit,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // A new value (tag projection switched off by Guess tag, …) is loaded, except while splitting.
  const prevValueRef = useRef(value)
  useEffect(() => {
    if (prevValueRef.current === value) return
    prevValueRef.current = value
    if (!editorRef.current || segment.openSplit) return
    load(value)
    publish()
  }, [value, segment.openSplit, load, publish])

  // Entering split mode: a segment already split shows its whole group, split points included.
  const prevOpenSplitRef = useRef(segment.openSplit)
  useEffect(() => {
    const wasOpen = prevOpenSplitRef.current
    prevOpenSplitRef.current = segment.openSplit
    if (
      !wasOpen &&
      segment.openSplit &&
      segment.splitted &&
      editorRef.current
    ) {
      load(joinSplitGroup(segment.split_group))
    }
  }, [segment.openSplit, segment.splitted, segment.split_group, load])

  useEffect(() => {
    refreshLayers()
  })

  const getSelectedWords = () =>
    editorRef.current ? editorRef.current.getSelection().textWithoutTags : ''

  const helpAiAssistant = () => {
    clearTimeout(delayAiAssistantRef.current)
    const isOpenAiEnabled =
      Boolean(config.isOpenAiEnabled) &&
      latestRef.current.userInfo?.metadata?.ai_assistant === 1
    if (!isOpenAiEnabled) return
    // Read 200 ms later: the selection may have moved on.
    delayAiAssistantRef.current = setTimeout(() => {
      const phrase = getSelectedWords()
      if (isValidPhraseToAiAssistant({phrase})) {
        SegmentActions.helpAiAssistant({
          sid: latestRef.current.contextSegment.sid,
          value: phrase,
        })
      }
    }, 200)
  }

  const updateOptionsToolbarVisibility = () => {
    if (!editorRef.current) return
    setToolbar({
      visible: !editorRef.current.view.state.selection.main.empty,
      words: getSelectedWords(),
    })
    helpAiAssistant()
  }

  const openConcordance = (e) => {
    e.preventDefault()
    const selection = window.getSelection()
    if (selection.type === 'Range') {
      const str = selection.toString().trim()
      if (str.length) SegmentActions.openConcordance(segment.sid, str, false)
    }
  }

  const onBlur = () => {
    setTimeout(() => {
      SegmentActions.highlightTags()
      SegmentActions.focusTags([])
    })
    setToolbar({visible: false, words: ''})
  }

  // A click in split mode puts a split point at the caret; a click that only ends a text selection clears it.
  const addSplitPoint = () => {
    const editor = editorRef.current
    if (!editor || chipClickedRef.current) return
    const {main} = editor.view.state.selection
    if (!main.empty) {
      editor.view.dispatch({selection: EditorSelection.cursor(main.head)})
      return
    }
    editor.insertRaw(SPLIT_PLACEHOLDER)
    changeSplitCount(1)
  }

  const confirmSplit = () => {
    // The backend takes angle brackets unescaped
    const text = editorRef.current
      .getValue()
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
    SegmentActions.splitSegment(segment.original_sid, text)
  }

  const handlers = contextSegment.openSplit
    ? {
        onMouseDownCapture: () => (chipClickedRef.current = false),
        onClick: addSplitPoint,
        onBlur,
      }
    : {
        onBlur,
        onMouseUp: () => setTimeout(updateOptionsToolbarVisibility),
        onKeyUp: (event) => {
          if (isArrowKey(event.key)) updateOptionsToolbarVisibility()
        },
      }

  const selectedWords = toolbar.words
  const isEnabledAiAssistantButton = isValidPhraseToAiAssistant({
    phrase: selectedWords,
  })

  const optionsToolbar = toolbar.visible && (
    <div className="optionsToolbar">
      {Boolean(config.isOpenAiEnabled) &&
        userInfo?.metadata?.ai_assistant === 0 && (
          <Button
            className="segment-target-toolbar-icon"
            size={BUTTON_SIZE.ICON_SMALL}
            mode={BUTTON_MODE.OUTLINE}
            title={
              isEnabledAiAssistantButton
                ? 'See the meaning of the highlighted text in this context'
                : "Your selection is over the AI assistant's limit of 3 words, 6 Chinese characters or 10 Japanese characters, please reduce it."
            }
            onMouseDown={() => {
              if (isEnabledAiAssistantButton) {
                SegmentActions.helpAiAssistant({
                  sid: contextSegment.sid,
                  value: selectedWords,
                })
              }
            }}
            disabled={!isEnabledAiAssistantButton}
          >
            <Assistant />
          </Button>
        )}
      <Button
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        title="Click to add the highlighted text to the termbase"
        onMouseDown={() => {
          SegmentActions.openGlossaryFormPrefill({
            sid: contextSegment.sid,
            [TERM_FORM_FIELDS.ORIGINAL_TERM]: selectedWords,
          })
        }}
      >
        <Education />
      </Button>
    </div>
  )

  const editorHtml = (
    <div
      className="source item"
      tabIndex={0}
      id={'segment-' + contextSegment.sid + '-source'}
      data-original={originalSource}
      {...handlers}
    >
      <UseHotKeysComponent
        shortcut={
          Shortcuts.cattol.events.searchInConcordance.keystrokes[
            Shortcuts.shortCutsKeyType
          ]
        }
        callback={openConcordance}
      />
      <div ref={parentRef} className="source-editor" />
      {optionsToolbar}
    </div>
  )

  return contextSegment.openSplit ? (
    <div className="splitContainer">
      {editorHtml}
      <div className="splitBar">
        {!!splitCount && (
          <div className="splitNum">
            Split in <span className="num">{splitCount}</span> segment
            <span className="plural" />
          </div>
        )}
        <div className="buttons">
          <Button
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.SMALL}
            onClick={() => SegmentActions.closeSplitSegment()}
          >
            Cancel
          </Button>
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.SMALL}
            disabled={!splitCount}
            onClick={confirmSplit}
          >
            Confirm
          </Button>
        </div>
      </div>
    </div>
  ) : (
    editorHtml
  )
}

export default SourceEditor
