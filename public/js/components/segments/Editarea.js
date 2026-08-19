import React, {
  forwardRef,
  useContext,
  useEffect,
  useImperativeHandle,
  useReducer,
  useRef,
  useState,
} from 'react'
import {fromJS} from 'immutable'
import {
  Modifier,
  Editor,
  EditorState,
  getDefaultKeyBinding,
  KeyBindingUtil,
  CompositeDecorator,
  SelectionState, // eslint-disable-line no-unused-vars
} from 'draft-js'
import {remove, cloneDeep, findIndex, size, isEqual} from 'lodash'
import {debounce} from 'lodash/function'

import SegmentConstants from '../../constants/SegmentConstants'
import EditAreaConstants from '../../constants/EditAreaConstants'
import SegmentStore from '../../stores/SegmentStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import * as DraftMatecatConstants from './utils/DraftMatecatUtils/editorConstants'
import TagEntity from './TagEntity/TagEntity.component'
import SegmentUtils from '../../utils/segmentUtils'
import CommonUtils from '../../utils/commonUtils'
import TagBox from './utils/DraftMatecatUtils/TagMenu/TagBox'
import insertTag from './utils/DraftMatecatUtils/TagMenu/insertTag'
import checkForMissingTags from './utils/DraftMatecatUtils/TagMenu/checkForMissingTag'
import LexiqaUtils from '../../utils/lxq.main'
// eslint-disable-next-line no-unused-vars
import transformLexiqaPoints from './utils/DraftMatecatUtils/transformLexiqaPoints'
import updateOffsetBasedOnEditorState from './utils/DraftMatecatUtils/updateOffsetBasedOnEditorState'
import {tagSignatures} from './utils/DraftMatecatUtils/tagModel'
import SegmentActions from '../../actions/SegmentActions'
import getFragmentFromSelection from './utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import matchTypingSequence from '../../utils/matchTypingSequence/matchTypingSequence'
import {SegmentContext} from './SegmentContext'
import CatToolStore from '../../stores/CatToolStore'
import {
  checkCaretIsNearEntity,
  adjustCaretPosition,
  isCaretInsideEntity,
  checkCaretIsNearZwsp,
  isSelectedEntity,
  getEntitiesSelected,
} from './utils/DraftMatecatUtils/manageCaretPositionNearEntity'
import {
  createICUDecorator,
  createIcuTokens,
  isEqualICUTokens,
} from './utils/DraftMatecatUtils/createICUDecorator'
import {isMacOS} from '../../utils/Utils'
import {removeZeroWidthSpace} from './utils/DraftMatecatUtils/tagUtils'
import textUtils from '../../utils/textUtils'
import ContextPreviewChannel from '../../utils/contextPreviewChannel'

const {hasCommandModifier, isOptionKeyCommand, isCtrlKeyCommand} =
  KeyBindingUtil

const editorSync = {
  editorFocused: true,
  clickedOnTag: false,
  onComposition: false,
}

// typing chars sequence
const typingWordJoiner = matchTypingSequence(
  [
    [50, 98],
    [48, 96],
    [54, 102],
    [48, 96],
  ],
  2000,
)

const Editarea = forwardRef((props, ref) => {
  const context = useContext(SegmentContext)

  const instanceRef = useRef({})
  const methodsAssignedRef = useRef(false)
  const liveRef = useRef({})

  // Plain instance fields (createRef equivalents) preserved as-is
  const isShiftPressedOnNavigationRef = useRef(undefined)
  const wasTripleClickTriggeredRef = useRef(undefined)
  const compositionEventChecksRef = useRef(undefined)
  const editorRef = useRef(null)
  const editAreaDomRef = useRef(null)
  // this.prevIcuTokens (plain mutable instance field, internal only)
  const prevIcuTokensRef = useRef(undefined)

  const [icuEnabled] = useState(() => props.segment.icu)

  // ---- stable method closures (useRef-seeded once, always dispatched via instanceRef.current) ----

  const getTextToApplyCounterRef = useRef((translation) => {
    const canCountTagsAsChars =
      CatToolStore.getCurrentProjectTemplate().characterCounterCountTags
    if (canCountTagsAsChars) {
      return DraftMatecatUtils.excludeSomeTagsTransformToText(translation, [
        'g',
        'bx',
        'ex',
        'x',
      ])
    } else {
      return DraftMatecatUtils.decodePlaceholdersToPlainText(
        DraftMatecatUtils.removeTagsFromText(translation),
      )
    }
  })

  const getSearchParamsRef = useRef(() => {
    const {
      inSearch,
      currentInSearch,
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
    } = liveRef.current.props.segment
    if (inSearch && searchParams.target) {
      return {
        active: inSearch,
        currentActive: currentInSearch,
        textToReplace: searchParams.target,
        params: searchParams,
        occurrences: occurrencesInSearch.occurrences,
        currentInSearchIndex,
        isTarget: true,
      }
    } else {
      return {
        active: false,
      }
    }
  })

  const addIcuDecoratorRef = useRef((tokens) => {
    const newDecorator = createICUDecorator(tokens)
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === DraftMatecatConstants.ICU_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })

  const addSearchDecoratorRef = useRef(() => {
    const {tagRange} = liveRef.current
    const {searchParams, occurrencesInSearch, currentInSearchIndex} =
      liveRef.current.props.segment
    console.log('occurrencesInSearch', occurrencesInSearch)
    const textToSearch = searchParams.target ? searchParams.target : ''
    const newDecorator = DraftMatecatUtils.activateSearch(
      textToSearch,
      searchParams,
      occurrencesInSearch.occurrences,
      currentInSearchIndex,
      tagRange,
    )
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === DraftMatecatConstants.SEARCH_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })

  const addQaBlacklistGlossaryDecoratorRef = useRef(() => {
    const {qaBlacklistGlossary, sid} = liveRef.current.props.segment
    const newDecorator = DraftMatecatUtils.activateQaCheckBlacklist(
      qaBlacklistGlossary,
      sid,
    )
    remove(
      decoratorsStructureRef.current,
      (decorator) =>
        decorator.name === DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })

  const addLexiqaDecoratorRef = useRef(() => {
    const {editorState} = liveRef.current
    const {lexiqa, sid, lxqDecodedTranslation} = liveRef.current.props.segment
    // pass decoded translation with tags like <g id='1'>
    const ranges = LexiqaUtils.getRanges(
      cloneDeep(lexiqa.target),
      lxqDecodedTranslation,
      false,
    )
    const updatedLexiqaWarnings = updateOffsetBasedOnEditorState(
      editorState,
      ranges,
    )
    if (updatedLexiqaWarnings.length > 0) {
      const newDecorator = DraftMatecatUtils.activateLexiqa(
        editorState,
        updatedLexiqaWarnings,
        sid,
        false,
        instanceRef.current.getUpdatedSegmentInfo,
        instanceRef.current.replaceWordAt,
      )
      remove(
        decoratorsStructureRef.current,
        (decorator) =>
          decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      decoratorsStructureRef.current.push(newDecorator)
    } else {
      instanceRef.current.removeDecorator(
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )
    }
  })

  // Receive the new translation and decode it for draftJS
  const setNewTranslationRef = useRef((sid, translation) => {
    if (sid === liveRef.current.props.segment.sid) {
      const {editorState} = liveRef.current
      const contentEncoded = DraftMatecatUtils.encodeContent(
        editorState,
        translation,
        liveRef.current.props.segment.sourceTagMap,
      )
      // this must be done to make the Undo action possible, otherwise encodeContent will delete all editor history
      let {editorState: newEditorState} = contentEncoded
      const newContentState = newEditorState.getCurrentContent()
      newEditorState = EditorState.push(
        editorState,
        newContentState,
        'insert-fragment',
      )
      newEditorState = EditorState.moveSelectionToEnd(newEditorState)

      liveRef.current.props.updateCounter(
        DraftMatecatUtils.getCharactersCounter(
          instanceRef.current.getTextToApplyCounter(translation),
        ),
      )
      instanceRef.current.setState(
        {
          editorState: newEditorState,
        },
        () => {
          instanceRef.current.updateTranslationDebounced()
        },
      )
    }
  })

  const replaceCurrentSearchRef = useRef((text) => {
    const {
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
      currentInSearch,
    } = liveRef.current.props.segment
    if (currentInSearch && searchParams.target) {
      const index = findIndex(
        occurrencesInSearch.occurrences,
        (item) => item.searchProgressiveIndex === currentInSearchIndex,
      )
      const newEditorState = DraftMatecatUtils.replaceOccurrences(
        liveRef.current.editorState,
        searchParams.target,
        text,
        index,
      )
      instanceRef.current.setState(
        {
          editorState: newEditorState,
        },
        () => {
          instanceRef.current.updateTranslationInStore()
        },
      )
    }
  })

  const updateTranslationInStoreRef = useRef(() => {
    const {editorState} = liveRef.current
    const {
      segment,
      segment: {sourceTagMap},
    } = liveRef.current.props
    const {decodedSegment, entitiesRange} =
      DraftMatecatUtils.decodeSegment(editorState)
    if (decodedSegment !== '') {
      const contentState = editorState.getCurrentContent()
      const plainText = removeZeroWidthSpace(contentState.getPlainText())

      // Matches tag without compute tag id
      const currentTagRange = DraftMatecatUtils.matchTagInEditor(
        editorState,
        entitiesRange,
      )
      // Add missing tag to store for highlight warnings on tags
      const {missingTags} = checkForMissingTags(sourceTagMap, currentTagRange)

      const lxqDecodedTranslation =
        DraftMatecatUtils.prepareTextForLexiqa(decodedSegment)

      SegmentActions.updateTranslation(
        segment.sid,
        decodedSegment,
        plainText,
        currentTagRange,
        missingTags,
        lxqDecodedTranslation,
      )
      ContextPreviewChannel.sendMessage({
        type: 'updateTranslation',
        sid: segment.sid,
        target: decodedSegment,
      })
      liveRef.current.props.updateCounter(
        DraftMatecatUtils.getCharactersCounter(
          instanceRef.current.getTextToApplyCounter(decodedSegment),
        ),
      )
      SegmentActions.startSegmentQACheck()
    } else {
      liveRef.current.props.updateCounter(0)
    }
  })

  const checkDecoratorsRef = useRef((prevProps) => {
    let changedDecorator = false
    const {inSearch} = liveRef.current.props.segment
    const prevActiveDecorators = liveRef.current.activeDecorators
    const {editorState} = liveRef.current
    const activeDecorators = {...prevActiveDecorators}

    if (!inSearch) {
      // Qa Check Blacklist
      const {qaBlacklistGlossary} = liveRef.current.props.segment
      const prevQaBlacklistGlossary = prevProps
        ? prevProps.segment.qaBlacklistGlossary
        : undefined
      if (
        (qaBlacklistGlossary &&
          qaBlacklistGlossary.length > 0 &&
          !activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR]) ||
        (activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] &&
          !isEqual(qaBlacklistGlossary, prevQaBlacklistGlossary))
      ) {
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = true
        changedDecorator = true
        instanceRef.current.addQaBlacklistGlossaryDecorator()
      } else if (
        prevQaBlacklistGlossary &&
        prevQaBlacklistGlossary.length > 0 &&
        (!qaBlacklistGlossary || qaBlacklistGlossary.length === 0)
      ) {
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
        changedDecorator = true
        instanceRef.current.removeDecorator(
          DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
        )
      }

      // Lexiqa
      const {lexiqa} = liveRef.current.props.segment
      const prevLexiqa = prevProps ? prevProps.segment.lexiqa : undefined
      const currentLexiqaTarget = lexiqa && lexiqa.target && size(lexiqa.target)
      const prevLexiqaTarget =
        prevLexiqa && prevLexiqa.target && size(prevLexiqa.target)
      const lexiqaChanged =
        prevLexiqaTarget &&
        currentLexiqaTarget &&
        !fromJS(prevLexiqa.target).equals(fromJS(lexiqa.target))

      if (
        // Condition to understand if the job has tm keys or if the check glossary request has been made (blacklist must take precedence over lexiqa)
        (CatToolStore.getHaveKeysGlossary() === false ||
          Array.isArray(qaBlacklistGlossary)) &&
        currentLexiqaTarget &&
        (!prevLexiqaTarget ||
          lexiqaChanged ||
          !prevActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
        changedDecorator = true
        instanceRef.current.addLexiqaDecorator()
      } else if (prevLexiqaTarget && !currentLexiqaTarget) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        changedDecorator = true
        instanceRef.current.removeDecorator(
          DraftMatecatConstants.LEXIQA_DECORATOR,
        )
      }
      // Search
      if (prevProps && prevProps.segment.inSearch) {
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
        changedDecorator = true
        instanceRef.current.removeDecorator(
          DraftMatecatConstants.SEARCH_DECORATOR,
        )
      }
      const contentState = editorState.getCurrentContent()
      const plainText = textUtils.removeWhitespacePlaceholders(
        contentState.getPlainText(),
      )
      if (liveRef.current.icuEnabled) {
        const icuTokens = createIcuTokens(
          plainText,
          editorState,
          config.target_rfc,
        )

        if (
          !prevProps ||
          !prevIcuTokensRef.current ||
          !isEqualICUTokens(icuTokens, prevIcuTokensRef.current)
        ) {
          prevIcuTokensRef.current = icuTokens
          changedDecorator = true
          instanceRef.current.addIcuDecorator(icuTokens)
        }
      }
    } else {
      // Search
      if (
        liveRef.current.props.segment.searchParams.target &&
        (!prevProps ||
          !prevProps.segment.inSearch || // Before was not active
          (prevProps.segment.inSearch &&
            !fromJS(prevProps.segment.searchParams).equals(
              fromJS(liveRef.current.props.segment.searchParams),
            )) || // Before was active but some params change
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearch !==
              liveRef.current.props.segment.currentInSearch) || // Before was the current
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearchIndex !==
              liveRef.current.props.segment.currentInSearchIndex))
      ) {
        // There are more occurrences and the current change
        // Cleanup all decorators
        instanceRef.current.removeDecorator()
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
        instanceRef.current.addSearchDecorator()
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
        changedDecorator = true
      }
    }

    if (changedDecorator) {
      const timer = inSearch ? 400 : 0
      const decorator = new CompositeDecorator(decoratorsStructureRef.current)
      setTimeout(() => {
        instanceRef.current.setState({
          editorState: EditorState.set(editorState, {decorator}),
          activeDecorators,
        })
      }, timer)
    }
  })

  const copyGlossaryToEditAreaRef = useRef((segment, glossaryTranslation) => {
    if (segment.sid === liveRef.current.props.segment.sid) {
      const {editorState} = liveRef.current
      const newEditorState = DraftMatecatUtils.insertText(
        editorState,
        glossaryTranslation,
      )
      instanceRef.current.setState(
        {
          editorState: newEditorState,
        },
        () => {
          instanceRef.current.updateTranslationDebounced()
        },
      )
    }
  })

  const refreshTagMapRef = useRef(() => {
    instanceRef.current.setNewTranslation(
      liveRef.current.props.segment.sid,
      liveRef.current.props.translation,
    )
    setTimeout(() => instanceRef.current.checkDecorators(), 100)
  })

  const refreshCharactersCounterRulesRef = useRef(() => {
    instanceRef.current.setNewTranslation(
      liveRef.current.props.segment.sid,
      liveRef.current.props.translation,
    )
  })

  const onCompositionStartRef = useRef(() => {
    compositionEventChecksRef.current = {
      startIsInsideEntity: isCaretInsideEntity(),
      endIsTriggered: false,
    }
  })

  const onCompositionEndRef = useRef(() => {
    compositionEventChecksRef.current = {
      ...compositionEventChecksRef.current,
      endIsTriggered: true,
    }
  })

  const replaceWordAtRef = useRef(({newWord, start, end}) => {
    const startIndex = start
    const endIndex = end
    const selection = liveRef.current.editorState.getSelection().merge({
      anchorOffset: startIndex,
      focusOffset: endIndex,
    })
    const contentState = Modifier.replaceText(
      liveRef.current.editorState.getCurrentContent(),
      selection,
      newWord,
    )
    const updatedState = EditorState.push(
      liveRef.current.editorState,
      contentState,
    )
    instanceRef.current.setState({editorState: updatedState}, () => {
      // Reactivate decorators
      instanceRef.current.updateTranslationDebounced()
      // Stop composition mode
      instanceRef.current.onCompositionStopDebounced()
    })
  })

  const focusEditorRef = useRef(() => {
    if (editorRef.current) editorRef.current.focus()
  })

  const typeTextInEditorRef = useRef((textToInsert) => {
    const {editorState} = liveRef.current
    editorSync.onComposition = true
    let newEditorState = instanceRef.current.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    newEditorState = DraftMatecatUtils.insertText(newEditorState, textToInsert)
    instanceRef.current.setState(
      (prevState) => ({
        activeDecorators: {
          ...prevState.activeDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        },
        editorState: newEditorState,
        triggerText: textToInsert,
      }),
      () => {
        // Update translation
        instanceRef.current.updateTranslationDebounced()
        // Reactivate decorators
        instanceRef.current.onCompositionStopDebounced()
      },
    )
  })

  const myKeyBindingFnRef = useRef((e) => {
    const {displayPopover} = liveRef.current
    const isChromeBook = navigator.userAgent.indexOf('CrOS') > -1
    if (
      (e.keyCode === 84 || e.key === 't' || e.key === '™') &&
      (isOptionKeyCommand(e) || e.altKey) &&
      !e.shiftKey
    ) {
      instanceRef.current.setState({triggerText: null})
      return 'toggle-tag-menu'
    } else if (e.key === '<' && !hasCommandModifier(e)) {
      instanceRef.current.typeTextInEditor('<')
      return 'toggle-tag-menu'
    } else if (e.key === 'ArrowUp' && !hasCommandModifier(e)) {
      if (displayPopover) return 'up-arrow-press'
    } else if (e.key === 'ArrowDown' && !hasCommandModifier(e)) {
      if (displayPopover) return 'down-arrow-press'
    } else if (e.key === 'Enter') {
      if (
        (e.altKey && e.ctrlKey) ||
        (e.ctrlKey && isOptionKeyCommand(e) && e.shiftKey)
      ) {
        return 'add-issue'
      } else if (displayPopover && !hasCommandModifier(e)) {
        return 'enter-press'
      } else if ((e.ctrlKey || e.metaKey) && e.shiftKey) {
        return 'next-translate'
      } else if (e.ctrlKey || e.metaKey) {
        return 'translate'
      }
    } else if (e.key === 'Escape') {
      return 'close-tag-menu'
    } else if (e.key === 'Tab') {
      return e.shiftKey ? null : 'insert-tab-tag'
    } else if (
      e.code === 'Space' &&
      !e.ctrlKey &&
      !e.altKey &&
      !e.shiftKey &&
      tagSignatures.space
    ) {
      return 'insert-space-tag'
    } else if (
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      ((isCtrlKeyCommand(e) && e.shiftKey) ||
        (isMacOS() && isOptionKeyCommand(e) && !e.ctrlKey))
    ) {
      return 'insert-nbsp-tag' // Windows && Mac
    } else if (
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      !e.shiftKey &&
      e.altKey &&
      isChromeBook
    ) {
      return 'insert-nbsp-tag' // Chromebook
    } else if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && !e.altKey) {
      isShiftPressedOnNavigationRef.current = e.shiftKey

      const direction = e.key === 'ArrowLeft' ? 'left' : 'right'

      // check caret is near zwsp char and move caret position
      const updatedStateNearZwsp = checkCaretIsNearZwsp({
        editorState: liveRef.current.editorState,
        direction,
        isShiftPressed: e.shiftKey,
      })

      // check caret is near entity and move caret position
      const updatedStateNearEntity = checkCaretIsNearEntity({
        editorState: updatedStateNearZwsp
          ? updatedStateNearZwsp
          : liveRef.current.editorState,
        direction,
        isShiftPressed: e.shiftKey,
      })

      if (updatedStateNearEntity || updatedStateNearZwsp) {
        instanceRef.current.setState({
          editorState: updatedStateNearEntity
            ? updatedStateNearEntity
            : updatedStateNearZwsp,
        })
        return `${direction}-nav`
      }
    } else if (e.ctrlKey && e.key === 'k') {
      return 'tm-search'
    } else if (
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      ((e.ctrlKey && e.altKey) || (isMacOS() && e.shiftKey))
    ) {
      return 'insert-word-joiner-tag'
    } else if (e.code === 'BracketLeft' || e.code === 'BracketRight') {
      if (e.code === 'BracketLeft' && isCtrlKeyCommand(e)) {
        if (e.shiftKey) {
          instanceRef.current.typeTextInEditor('“')
        } else {
          instanceRef.current.typeTextInEditor('‘')
        }
        return 'quote-shortcut'
      }
      if (e.code === 'BracketRight' && isCtrlKeyCommand(e)) {
        if (e.shiftKey) {
          instanceRef.current.typeTextInEditor('”')
        } else {
          instanceRef.current.typeTextInEditor('’')
        }
        return 'quote-shortcut'
      }
    } else if (e.altKey && !e.shiftKey && !e.ctrlKey) {
      const {get, reset} = typingWordJoiner
      if (e.key !== 'Alt') {
        const result = get(e.keyCode)
        if (result) {
          return 'insert-word-joiner-tag'
        }
      } else {
        reset()
      }
    } else if (
      (e.key === 'Backspace' || e.key === 'Delete') &&
      !isSelectedEntity(liveRef.current.editorState) &&
      window.getSelection().type === 'Caret'
    ) {
      const isRTL = Boolean(config.isTargetRTL)
      const direction =
        e.key === 'Backspace'
          ? !isRTL
            ? 'left'
            : 'right'
          : !isRTL
            ? 'right'
            : 'left'

      const updatedStateNearZwsp = checkCaretIsNearZwsp({
        editorState: liveRef.current.editorState,
        direction,
        isShiftPressed: true,
      })

      // check caret is near entity and move caret position
      const updatedStateNearEntity = checkCaretIsNearEntity({
        editorState: updatedStateNearZwsp
          ? updatedStateNearZwsp
          : liveRef.current.editorState,
        direction,
        isShiftPressed: true,
        isBackspacePressed: e.key === 'Backspace',
      })

      if (updatedStateNearEntity) {
        const selectionState = updatedStateNearEntity.getSelection()
        const contentState = updatedStateNearEntity.getCurrentContent()

        const updatedEditorState = EditorState.push(
          updatedStateNearEntity,
          Modifier.replaceText(contentState, selectionState, null),
          'insert-characters',
        )
        instanceRef.current.onChange(updatedEditorState)
        return 'delete-entity'
      }
    }
    return getDefaultKeyBinding(e)
  })

  const handleKeyCommandRef = useRef((command) => {
    const {
      segment: {sourceTagMap, missingTagsInTarget},
    } = liveRef.current.props

    switch (command) {
      case 'toggle-tag-menu': {
        const tagSuggestions = {
          missingTags: missingTagsInTarget,
          sourceTags: sourceTagMap,
        }
        if (tagSuggestions.sourceTags && tagSuggestions.sourceTags.length > 0) {
          instanceRef.current.openPopover(
            tagSuggestions,
            instanceRef.current.getEditorRelativeSelectionOffset(),
          )
        }
        return 'handled'
      }
      case 'close-tag-menu':
        instanceRef.current.closePopover()
        return 'handled'
      case 'up-arrow-press':
        instanceRef.current.moveUpTagMenuSelection()
        return 'handled'
      case 'down-arrow-press':
        instanceRef.current.moveDownTagMenuSelection()
        return 'handled'
      case 'enter-press':
        instanceRef.current.acceptTagMenuSelection()
        return 'handled'
      case 'left-nav':
        return 'handled'
      case 'right-nav':
        return 'handled'
      case 'insert-tab-tag':
        instanceRef.current.insertTagAtSelectionDebounced('tab')
        return 'handled'
      case 'insert-space-tag':
        if (tagSignatures.space) {
          instanceRef.current.insertTagAtSelectionDebounced('space')
          return 'handled'
        } else {
          return 'not-handled'
        }

      case 'insert-nbsp-tag':
        instanceRef.current.insertTagAtSelectionDebounced('nbsp')
        return 'handled'
      case 'add-issue':
        return 'handled'
      case 'insert-word-joiner-tag':
        instanceRef.current.insertTagAtSelectionDebounced('wordJoiner')
        return 'handled'
      case 'delete-entity':
        return 'handled'
      case 'translate':
        return 'not-handled'
      case 'next-translate':
        return 'not-handled'
      case 'quote-shortcut':
        return 'handled'
      default:
        return 'not-handled'
    }
  })

  const insertTagAtSelectionRef = useRef((tagName) => {
    const {editorState} = liveRef.current
    const customTag = DraftMatecatUtils.structFromName(tagName)
    // If tag creation has failed, return
    if (!customTag) return
    // Start composition mode and remove lexiqa
    editorSync.onComposition = true
    let newEditorState = instanceRef.current.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )

    newEditorState = insertTag(customTag, newEditorState)

    instanceRef.current.setState(
      (prevState) => ({
        activeDecorators: {
          ...prevState.activeDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        },
        editorState: newEditorState,
      }),
      () => {
        // Reactivate decorators
        instanceRef.current.updateTranslationDebounced()
        // Stop composition mode
        instanceRef.current.onCompositionStopDebounced()
      },
    )
  })

  const onMouseUpEventRef = useRef(() => {
    const {toggleFormatMenu} = liveRef.current.props
    toggleFormatMenu(
      !editorRef.current._latestEditorState.getSelection().isCollapsed(),
    )
  })

  const onKeyUpEventRef = useRef((event) => {
    if (
      event.key === 'ArrowLeft' ||
      event.key === 'ArrowRight' ||
      event.key === 'ArrowUp' ||
      event.key === 'ArrowDown'
    ) {
      const {toggleFormatMenu} = liveRef.current.props
      toggleFormatMenu(
        !editorRef.current._latestEditorState.getSelection().isCollapsed(),
      )
    }
  })

  const onBlurEventRef = useRef(() => {
    const {toggleFormatMenu} = liveRef.current.props
    editorSync.editorFocused = false
    // Hide Edit Toolbar
    toggleFormatMenu(false)
  })

  // Focus on editor trigger 2 onChange events
  /*onBlur = () => {
        if (!editorSync.clickedOnTag) {
            this.setState({
                displayPopover: false,
                editorFocused: false
            });
            editorSync.editorFocused = false;
        }
    };*/

  const onFocusRef = useRef(() => {
    editorSync.editorFocused = true
  })

  const onCompositionStopRef = useRef(() => {
    if (editorSync.onComposition) {
      editorSync.onComposition = false
      // Tell tags to update themself
      setTimeout(() => {
        SegmentActions.editAreaChanged(liveRef.current.props.segment.sid, true)
      })
    }
  })

  const removeDecoratorRef = useRef((decoratorName) => {
    if (!decoratorName) {
      remove(
        decoratorsStructureRef.current,
        (decorator) => decorator.name !== DraftMatecatConstants.TAGS_DECORATOR,
      )
    } else {
      remove(
        decoratorsStructureRef.current,
        (decorator) => decorator.name === decoratorName,
      )
    }
  })

  // has to be followed by a setState for editorState
  const disableDecoratorRef = useRef((editorState, decoratorName) => {
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === decoratorName,
    )
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    return EditorState.set(editorState, {decorator})
  })

  const onChangeRef = useRef((editorState) => {
    const {displayPopover, activeDecorators} = liveRef.current
    const prevEditorState = liveRef.current.editorState

    // check caret is inside entity and restore previous editorState
    if (
      isCaretInsideEntity() ||
      compositionEventChecksRef.current?.startIsInsideEntity
    ) {
      const updatedStateNearEntity = checkCaretIsNearEntity({
        editorState,
      })

      instanceRef.current.setState(
        () => ({
          editorState: updatedStateNearEntity
            ? updatedStateNearEntity
            : prevEditorState,
        }),
        () => {
          instanceRef.current.onCompositionStopDebounced()
        },
      )
      if (compositionEventChecksRef?.endIsTriggered)
        compositionEventChecksRef.current = {
          startIsInsideEntity: false,
          endIsTriggered: false,
        }
      return
    }

    const contentChanged =
      editorState.getCurrentContent().getPlainText() !==
      prevEditorState.getCurrentContent().getPlainText()

    // if not on an entity, remove any previous selection highlight
    const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
    let newActiveDecorators = {...activeDecorators}
    // select no tag
    if (!entityKey)
      setTimeout(() => {
        SegmentActions.highlightTags()
      })

    // if opened, close TagsMenu
    if (displayPopover) instanceRef.current.closePopover()
    if (contentChanged) {
      // Stop checking decorators while typing...
      editorSync.onComposition = true
      // ...remove unwanted decorators like lexiqa and qa blacklist...
      if (activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR]) {
        editorState = instanceRef.current.disableDecorator(
          editorState,
          DraftMatecatConstants.LEXIQA_DECORATOR,
        )
        newActiveDecorators = {
          ...newActiveDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        }
      }
      if (activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR]) {
        editorState = instanceRef.current.disableDecorator(
          editorState,
          DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
        )
        newActiveDecorators = {
          ...newActiveDecorators,
          [DraftMatecatConstants.QA_BLACKLIST_DECORATOR]: false,
        }
      }
      editorState = EditorState.acceptSelection(
        editorState,
        editorState.getSelection().set('hasFocus', true),
      )
      instanceRef.current.setState(
        () => ({
          activeDecorators: newActiveDecorators,
          editorState: editorState,
        }),
        () => {
          // Reactivate decorators
          instanceRef.current.updateTranslationDebounced()
          instanceRef.current.onCompositionStopDebounced()
        },
      )
    } else {
      instanceRef.current.setState(
        () => ({
          editorState: editorState,
        }),
        () => {
          instanceRef.current.onCompositionStopDebounced()
        },
      )
    }
  })

  // fix cursor jump at the beginning
  const forceSelectionFocusRef = useRef((editorState) => {
    const currentSelection = editorState.getSelection()
    if (!currentSelection.getHasFocus()) {
      const selection = currentSelection.set('hasFocus', true)
      editorState = EditorState.acceptSelection(editorState, selection)
    }
    return editorState
  })

  // Methods for TagMenu ---- START
  const moveUpTagMenuSelectionRef = useRef(() => {
    const {displayPopover} = liveRef.current
    if (!displayPopover) return
    const {
      focusedTagIndex,
      autocompleteSuggestions: {missingTags, sourceTags},
    } = liveRef.current
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    const newFocusedTagIndex =
      focusedTagIndex - 1 < 0
        ? mergeAutocompleteSuggestions.length - 1
        : (focusedTagIndex - 1) % mergeAutocompleteSuggestions.length

    instanceRef.current.setState({
      focusedTagIndex: newFocusedTagIndex,
    })
  })

  const moveDownTagMenuSelectionRef = useRef(() => {
    const {displayPopover} = liveRef.current
    if (!displayPopover) return
    const {
      focusedTagIndex,
      autocompleteSuggestions: {missingTags, sourceTags},
    } = liveRef.current
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    instanceRef.current.setState({
      focusedTagIndex:
        (focusedTagIndex + 1) % mergeAutocompleteSuggestions.length,
    })
  })

  const acceptTagMenuSelectionRef = useRef(() => {
    const {
      focusedTagIndex,
      displayPopover,
      editorState,
      triggerText,
      autocompleteSuggestions: {missingTags = [], sourceTags},
    } = liveRef.current
    if (!displayPopover) return
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    const selectedTag = mergeAutocompleteSuggestions[focusedTagIndex]
    // Start typing
    editorSync.onComposition = true
    // Remove lexiqa while typing
    const newEditorState = instanceRef.current.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    const editorStateWithSuggestedTag = insertTag(
      selectedTag,
      newEditorState,
      triggerText,
    )
    instanceRef.current.setState(
      (prevState) => ({
        activeDecorators: {
          ...prevState.activeDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        },
        editorState: editorStateWithSuggestedTag,
        displayPopover: false,
        clickedTag: selectedTag,
        clickedOnTag: true,
        triggerText: null,
      }),
      () => {
        // Reactivate decorators
        instanceRef.current.updateTranslationDebounced()
        // Stop typing
        instanceRef.current.onCompositionStopDebounced()
      },
    )
  })

  const openPopoverRef = useRef((suggestions, position) => {
    // Posizione da salvare e passare al compoennte
    const popoverPosition = {
      top: position.top,
      left: position.left,
    }

    instanceRef.current.setState({
      displayPopover: true,
      autocompleteSuggestions: suggestions,
      focusedTagIndex: 0,
      popoverPosition: popoverPosition,
    })
  })

  const closePopoverRef = useRef(() => {
    instanceRef.current.setState({
      displayPopover: false,
      triggerText: null,
    })
  })

  const onTagClickRef = useRef((suggestionTag) => {
    const {editorState, triggerText} = liveRef.current
    // Start typing...
    editorSync.onComposition = true
    // Disable lexiqa while typing
    const newEditorState = instanceRef.current.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    const editorStateWithSuggestedTag = insertTag(
      suggestionTag,
      newEditorState,
      triggerText,
    )
    instanceRef.current.setState(
      (prevState) => ({
        activeDecorators: {
          ...prevState.activeDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        },
        editorState: editorStateWithSuggestedTag,
        editorFocused: true,
        clickedOnTag: true,
        clickedTag: suggestionTag,
        displayPopover: false,
        triggerText: null,
      }),
      () => {
        // Reactivate decorators
        instanceRef.current.updateTranslationDebounced()
        // Stop typing
        instanceRef.current.onCompositionStopDebounced()
      },
    )
  })
  // Methods for TagMenu ---- END

  const onPasteRef = useRef(() => {
    const {editorState} = liveRef.current
    const internalClipboard = editorRef.current.getClipboard()
    if (internalClipboard) {
      const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
        internalClipboard,
        editorState,
      )
      instanceRef.current.onChange(clipboardEditorPasted)
      instanceRef.current.setState({
        editorState: clipboardEditorPasted,
      })
      return true
    } else {
      return false
    }
  })

  const pasteFragmentRef = useRef((text) => {
    const {editorState} = liveRef.current
    const {fragment: clipboardFragment, plainText: clipboardPlainText} =
      SegmentStore.getFragmentFromClipboard()
    // if text in standard clipboard matches the the plainClipboard saved in store proceed using fragment
    // otherwise we're handling an external copy
    if (
      clipboardFragment &&
      text &&
      clipboardPlainText.replace(/\n/g, '') === text.replace(/\n/g, '')
    ) {
      try {
        const fragmentContent = JSON.parse(clipboardFragment)
        const fragment = DraftMatecatUtils.buildFragmentFromJson(
          fragmentContent.orderedMap,
        )
        const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
          fragment,
          editorState,
          fragmentContent.entitiesMap,
        )
        instanceRef.current.setState(
          {
            editorState: clipboardEditorPasted,
          },
          () => {
            instanceRef.current.updateTranslationDebounced()
          },
        )
        // Paste fragment
        return true
      } catch (e) {
        // Paste plain standard clipboard
        return false
      }
    } else if (text) {
      // we're handling an external copy, special chars must be striped from text
      // and we have to add tag for external entities like nbsp or tab
      let cleanText = DraftMatecatUtils.removeTagsFromText(text)
      // Replace with placeholder
      const nbspSign = tagSignatures['nbsp'].encodedPlaceholder
      const tabSign = tagSignatures['tab'].encodedPlaceholder
      cleanText = cleanText.replace(/°/gi, nbspSign).replace(/\t/gi, tabSign)
      const plainTextClipboardFragment =
        DraftMatecatUtils.buildFragmentFromText(cleanText)
      const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
        plainTextClipboardFragment,
        editorState,
      )
      instanceRef.current.setState(
        {
          editorState: clipboardEditorPasted,
        },
        () => {
          instanceRef.current.updateTranslationDebounced()
        },
      )
      // Paste fragment
      return true
    }
    // Paste plain standard clipboard
    return false
  })

  const copyFragmentRef = useRef((e) => {
    const internalClipboard = editorRef.current.getClipboard()
    const {editorState} = liveRef.current
    if (internalClipboard) {
      e.preventDefault()
      // Get plain text form internalClipboard fragment
      const plainText = internalClipboard
        .map((block) => block.getText())
        .join('\n')
        .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')
        .replace(/·/g, ' ')

      const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
        internalClipboard,
        editorState,
      )
      const fragment = JSON.stringify({
        orderedMap: internalClipboard,
        entitiesMap: entitiesMap,
      })
      e.clipboardData.setData('text/plain', plainText)
      SegmentActions.copyFragmentToClipboard(fragment, plainText)
    }
  })

  const onDragEventRef = useRef(() => {
    editorSync.draggingFromEditArea = true
  })

  const onDragEndRef = useRef(() => {
    editorSync.draggingFromEditArea = false
  })

  const handleDropRef = useRef((selection, dataTransfer) => {
    let {editorState} = liveRef.current
    const text = dataTransfer.getText()

    // get selection of dragged text
    const dragSelection = editorState.getSelection()
    const dragSelectionLength =
      dragSelection.focusOffset - dragSelection.anchorOffset
    // get the fragment from current selection in editor (the highlighted tag)
    const fragmentFromSelection = getFragmentFromSelection(editorState)
    // Il fragment di draft NON FUNZIONA quindi lo ricostruisco
    const tempFrag = DraftMatecatUtils.buildFragmentFromJson(
      fragmentFromSelection,
    )
    // set selection to drop point and check dropping zone
    editorState = EditorState.forceSelection(editorState, selection)
    // Check: Cannot drop anything on entities
    const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
    if (entityKey) return 'handled'

    if (text && !editorSync.draggingFromEditArea) {
      try {
        const fragmentContent = JSON.parse(text)
        const fragment = DraftMatecatUtils.buildFragmentFromJson(
          fragmentContent.orderedMap,
        )
        const editorStateWithFragment = DraftMatecatUtils.duplicateFragment(
          fragment,
          editorState,
          fragmentContent.entitiesMap,
        )
        instanceRef.current.setState(
          {
            editorState: editorStateWithFragment,
          },
          () => {
            instanceRef.current.updateTranslationDebounced()
          },
        )
        return 'handled'
      } catch (err) {
        return 'not-handled'
      }
    } else {
      // when drop is inside the same editor, use default behavior
      // update: default behavior not working
      try {
        // remove drag selected range from editor state
        let contentState = editorState.getCurrentContent()
        contentState = Modifier.removeRange(
          contentState,
          dragSelection,
          dragSelection.isBackward ? 'backward' : 'forward',
        )

        // Aggiornala nel caso in cui sposti in avanti il drag nello stesso blocco
        const dragBlockKey = dragSelection.getAnchorKey()
        const dropBlockKey = selection.getAnchorKey()
        selection =
          dragSelection.anchorOffset < selection.anchorOffset &&
          dragBlockKey === dropBlockKey
            ? selection.merge({
                anchorOffset: selection.anchorOffset - dragSelectionLength,
                focusOffset: selection.focusOffset - dragSelectionLength,
              })
            : selection

        // Inserisci il fragment
        contentState = Modifier.replaceWithFragment(
          contentState,
          selection,
          tempFrag,
        )

        editorState = EditorState.push(
          editorState,
          contentState,
          'insert-fragment',
        )
        editorState = EditorState.forceSelection(editorState, selection)

        instanceRef.current.setState(
          {
            editorState: editorState,
          },
          () => {
            instanceRef.current.updateTranslationDebounced()
            setTimeout(() => {
              SegmentActions.highlightTags()
            })
          },
        )
        return 'handled'
      } catch (err) {
        console.log(err)
        return 'not-handled'
      }
    }
  })

  const onEntityClickRef = useRef((start, end) => {
    const {editorState} = liveRef.current
    // Use _latestEditorState
    try {
      // Selection
      const latestEditorState = editorRef.current._latestEditorState
      const selectionState = latestEditorState.getSelection()
      const currentBlockText = latestEditorState
        .getCurrentContent()
        .getBlockForKey(selectionState.getFocusKey())
        .getText()
      const zwsp = String.fromCharCode(parseInt('200B', 16))
      const selectedTextAfter = currentBlockText.slice(end, end + 1)
      const selectedTextBefore = currentBlockText.slice(start - 1, start)
      const addZwspExtraStepBefore = zwsp === selectedTextBefore ? 1 : 0
      const addZwspExtraStepAfter = zwsp === selectedTextAfter ? 1 : 0

      const newSelection = selectionState.merge({
        anchorOffset: start - addZwspExtraStepBefore, // -1 is to catch the zero-width space char placed before every entity
        focusOffset: end + addZwspExtraStepAfter, // +1 is to catch the zero-width space char placed after every entity
      })
      const newEditorState = EditorState.forceSelection(
        editorState,
        newSelection,
      )
      instanceRef.current.setState({editorState: newEditorState})
      // Highlight
    } catch (e) {
      console.log('Invalid selection')
    }
  })

  /**
   *
   * @param minWidth - min length of element to show
   * @returns {{top: number, left: number}}
   */
  const getEditorRelativeSelectionOffsetRef = useRef((minWidth = 300) => {
    const editorBoundingRect = editorRef.current.editor.getBoundingClientRect()
    const selectionBoundingRect = window
      .getSelection()
      .getRangeAt(0)
      .getBoundingClientRect()
    const leftInitial = selectionBoundingRect.x - editorBoundingRect.x
    const leftAdjusted =
      editorBoundingRect.right - selectionBoundingRect.left < minWidth
        ? leftInitial -
          (minWidth - (editorBoundingRect.right - selectionBoundingRect.left))
        : leftInitial
    if (
      selectionBoundingRect.bottom === 0 &&
      selectionBoundingRect.left === 0 &&
      selectionBoundingRect.height === 0
    ) {
      return {
        top: 50,
        left: 50,
      }
    }
    return {
      top:
        selectionBoundingRect.bottom -
        editorBoundingRect.top +
        selectionBoundingRect.height,
      left: leftAdjusted,
    }
  })

  const getUpdatedSegmentInfoRef = useRef(() => {
    const {
      segment: {
        sid,
        warnings,
        tagMismatch,
        opened,
        missingTagsInTarget,
        openSplit,
      },
    } = liveRef.current.props
    const {tagRange, editorState} = liveRef.current
    return {
      sid,
      warnings,
      tagMismatch,
      tagRange,
      segmentOpened: opened,
      missingTagsInTarget,
      currentSelection: editorRef.current
        ? editorRef.current._latestEditorState.getSelection()
        : editorState.getSelection(),
      openSplit,
    }
  })

  const formatSelectionRef = useRef((format) => {
    const {editorState} = liveRef.current
    // Todo: if selectionIsEntity return
    if (editorState.getSelection().isCollapsed()) {
      return
    }

    const selectionsText = DraftMatecatUtils.getSelectedTextWithoutEntities(
      editorState,
    ).map((selected) => ({
      ...selected,
      value: DraftMatecatUtils.formatText(selected.value, format),
    }))
    const newEditorState = DraftMatecatUtils.replaceMultipleText(
      editorState,
      selectionsText,
    )

    instanceRef.current.setState(
      {
        editorState: newEditorState,
      },
      () => {
        instanceRef.current.updateTranslationDebounced()
      },
    )
  })

  const addMissingSourceTagsToTargetRef = useRef(() => {
    const {segment} = liveRef.current.props
    const {editorState} = liveRef.current
    // Append missing tag at the end of the current translation string
    let newTranslation = segment.translation
    let newDecodedTranslation = segment.decodedTranslation
    let newEditorState = editorState
    segment.missingTagsInTarget.forEach((tag) => {
      newTranslation += tag.data.encodedText
      newDecodedTranslation += tag.data.placeholder
      newEditorState = DraftMatecatUtils.addTagEntityToEditor(
        newEditorState,
        tag,
      )
    })
    // Append missing tags to targetTagMap
    const segmentTargetTagMap = [
      ...segment.targetTagMap,
      ...segment.missingTagsInTarget,
    ]
    // Insert tag entity in current editor without recompute tags associations
    instanceRef.current.setState({
      editorState: newEditorState,
    })
    // lock tags and run again getWarnings
    setTimeout(() => {
      SegmentActions.updateTranslation(
        segment.sid,
        newTranslation,
        newDecodedTranslation,
        segmentTargetTagMap,
        [],
      )
      SegmentActions.getSegmentsQa({
        ...liveRef.current.props.segment,
        translation: newTranslation,
      })
    }, 100)
  })

  // ---- decoratorsStructure (mutable buffer, seeded once) ----
  const decoratorsStructureRef = useRef(null)
  if (decoratorsStructureRef.current === null) {
    decoratorsStructureRef.current = [
      {
        name: 'tags',
        strategy: getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
        props: {
          isTarget: true,
          onClick: onEntityClickRef.current,
          getUpdatedSegmentInfo: getUpdatedSegmentInfoRef.current,
          getSearchParams: getSearchParamsRef.current, //TODO: Make it general ?
          isRTL: config.isTargetRTL,
          sid: props.segment.sid,
        },
      },
    ]
  }

  // ---- initial content, computed once ----
  const initialContentRef = useRef(null)
  if (initialContentRef.current === null) {
    const translation = props.translation

    // If GuessTag is Enabled, clean translation from tags
    const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(
      props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation

    const decorator = new CompositeDecorator(decoratorsStructureRef.current)

    // Inizializza Editor State con solo testo
    const plainEditorState = EditorState.createEmpty(decorator)
    const contentEncoded = DraftMatecatUtils.encodeContent(
      plainEditorState,
      cleanTranslation,
    )
    initialContentRef.current = {
      editorState: contentEncoded.editorState,
      tagRange: contentEncoded.tagRange,
    }
  }

  const [editorState, setEditorState] = useState(
    () => initialContentRef.current.editorState,
  )
  const [editAreaClasses, setEditAreaClasses] = useState(['targetarea'])
  const [tagRange, setTagRange] = useState(
    () => initialContentRef.current.tagRange,
  )
  // TagMenu
  const [autocompleteSuggestions, setAutocompleteSuggestions] = useState([])
  const [focusedTagIndex, setFocusedTagIndex] = useState(0)
  const [displayPopover, setDisplayPopover] = useState(false)
  const [popoverPosition, setPopoverPosition] = useState({})
  const [editorFocused, setEditorFocused] = useState(true)
  const [clickedOnTag, setClickedOnTag] = useState(false)
  const [triggerText, setTriggerText] = useState(null)
  const [activeDecorators, setActiveDecorators] = useState(() => ({
    [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
    [DraftMatecatConstants.QA_BLACKLIST_DECORATOR]: false,
    [DraftMatecatConstants.SEARCH_DECORATOR]: false,
    [DraftMatecatConstants.ICU_DECORATOR]: icuEnabled,
  }))
  const [previousSourceTagMap, setPreviousSourceTagMap] = useState(null)
  const [clickedTag, setClickedTag] = useState(undefined)

  // constructor-time synchronous side effect: this.props.updateCounter(...)
  // No test can spy on the instance before mount completes, so calling the raw
  // seeded closure here (instead of instanceRef.current, not assigned yet) is safe.
  const constructorRanRef = useRef(false)
  if (!constructorRanRef.current) {
    constructorRanRef.current = true
    props.updateCounter(
      DraftMatecatUtils.getCharactersCounter(
        getTextToApplyCounterRef.current(props.translation),
      ),
    )
  }

  // debounced functions, constructed once (recreating them would drop pending timers)
  const updateTranslationDebouncedRef = useRef(null)
  if (updateTranslationDebouncedRef.current === null) {
    updateTranslationDebouncedRef.current = debounce(
      () => instanceRef.current.updateTranslationInStore(),
      100,
    )
  }
  const onCompositionStopDebouncedRef = useRef(null)
  if (onCompositionStopDebouncedRef.current === null) {
    onCompositionStopDebouncedRef.current = debounce(
      () => instanceRef.current.onCompositionStop(),
      1000,
    )
  }
  // insertTagAtSelection debounced function avoids broken insert for languages with oncomposition event ex. Korean
  const insertTagAtSelectionDebouncedRef = useRef(null)
  if (insertTagAtSelectionDebouncedRef.current === null) {
    insertTagAtSelectionDebouncedRef.current = debounce(
      (tagName) => instanceRef.current.insertTagAtSelection(tagName),
      1,
    )
  }

  // refresh liveRef every render so stable closures always see current data
  liveRef.current.props = props
  liveRef.current.editorState = editorState
  liveRef.current.editAreaClasses = editAreaClasses
  liveRef.current.tagRange = tagRange
  liveRef.current.autocompleteSuggestions = autocompleteSuggestions
  liveRef.current.focusedTagIndex = focusedTagIndex
  liveRef.current.displayPopover = displayPopover
  liveRef.current.popoverPosition = popoverPosition
  liveRef.current.editorFocused = editorFocused
  liveRef.current.clickedOnTag = clickedOnTag
  liveRef.current.triggerText = triggerText
  liveRef.current.activeDecorators = activeDecorators
  liveRef.current.previousSourceTagMap = previousSourceTagMap
  liveRef.current.clickedTag = clickedTag
  liveRef.current.icuEnabled = icuEnabled

  const [, bumpForceRender] = useReducer((x) => x + 1, 0)

  const isFirstRenderRef = useRef(true)
  const prevPropsRef = useRef(props)
  const prevStateRef = useRef(null)

  // componentDidMount / componentWillUnmount equivalent
  useEffect(() => {
    // captured once: instanceRef.current is a stable object for the component's whole
    // lifetime (only mutated in place, never reassigned), so this local alias is safe to
    // reuse in the cleanup below without re-reading the ref.
    const instance = instanceRef.current

    SegmentStore.addListener(
      SegmentConstants.REPLACE_TRANSLATION,
      instance.setNewTranslation,
    )
    SegmentStore.addListener(
      EditAreaConstants.REPLACE_SEARCH_RESULTS,
      instance.replaceCurrentSearch,
    )
    SegmentStore.addListener(
      EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
      instance.copyGlossaryToEditArea,
    )
    SegmentStore.addListener(
      SegmentConstants.REFRESH_TAG_MAP,
      instance.refreshTagMap,
    )
    SegmentStore.addListener(
      SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
      instance.refreshCharactersCounterRules,
    )
    setTimeout(() => {
      instance.checkDecorators()
      instance.updateTranslationInStore()
      if (liveRef.current.props.segment.opened) {
        instance.focusEditor()
      }
    })

    const {editor: editorElement} = editorRef.current
    editorElement.addEventListener(
      'compositionstart',
      instance.onCompositionStart,
    )
    editorElement.addEventListener('compositionend', instance.onCompositionEnd)

    new CommonUtils.DetectTripleClick(editAreaDomRef.current, () => {
      wasTripleClickTriggeredRef.current = true
    })

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.REPLACE_TRANSLATION,
        instance.setNewTranslation,
      )
      SegmentStore.removeListener(
        EditAreaConstants.REPLACE_SEARCH_RESULTS,
        instance.replaceCurrentSearch,
      )
      SegmentStore.removeListener(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        instance.copyGlossaryToEditArea,
      )
      SegmentStore.removeListener(
        SegmentConstants.REFRESH_TAG_MAP,
        instance.refreshTagMap,
      )
      SegmentStore.removeListener(
        SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
        instance.refreshCharactersCounterRules,
      )

      // captured above, not re-read here: by the time this passive-effect
      // cleanup runs, React has already nulled editorRef.current
      editorElement.removeEventListener(
        'compositionstart',
        instance.onCompositionStart,
      )
      editorElement.removeEventListener(
        'compositionend',
        instance.onCompositionEnd,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // componentDidUpdate equivalent
  useEffect(() => {
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false
      prevPropsRef.current = props
      prevStateRef.current = instanceRef.current.state
      return
    }

    const prevProps = prevPropsRef.current
    const prevState = prevStateRef.current

    if (!prevProps.segment.opened && props.segment.opened) {
      const newEditorState = EditorState.moveFocusToEnd(editorState)
      instanceRef.current.setState({editorState: newEditorState})
    } else if (prevProps.segment.opened && !props.segment.opened) {
      const newEditorState = EditorState.moveSelectionToEnd(editorState)
      instanceRef.current.setState({editorState: newEditorState})
    }
    if (!editorState.isInCompositionMode() && !editorSync.onComposition) {
      instanceRef.current.checkDecorators(prevProps)
    }

    // update editor state when receive prop of segment "sourceTagMap"
    if (
      props.segment.sourceTagMap?.length &&
      !isEqual(previousSourceTagMap, props.segment.sourceTagMap)
    ) {
      instanceRef.current.setState({
        previousSourceTagMap: props.segment.sourceTagMap,
      })
      instanceRef.current.setNewTranslation(
        props.segment.sid,
        props.translation,
      )
    }

    // Adjust caret position and set focus to entity
    if (prevState.editorState !== editorState) {
      const entitiesSelected = getEntitiesSelected(editorState)
      SegmentActions.focusTags(editorSync.editorFocused ? entitiesSelected : [])

      const currentFocusOffset = editorState.getSelection().getFocusOffset()
      const prevFocusOffset = prevState.editorState
        .getSelection()
        .getFocusOffset()

      if (prevFocusOffset !== currentFocusOffset) {
        const direction =
          currentFocusOffset > prevFocusOffset ? 'right' : 'left'

        adjustCaretPosition({
          direction,
          isShiftPressed: isShiftPressedOnNavigationRef.current,
        })
      }
    } else {
      const selection = window.getSelection()
      if (selection.focusNode) {
        const direction =
          selection.focusOffset < selection.focusNode.length / 2
            ? 'left'
            : 'right'

        adjustCaretPosition({
          direction,
          isShiftPressed: isShiftPressedOnNavigationRef.current,
          shouldMoveCursorPreviousElementTag:
            wasTripleClickTriggeredRef.current,
        })
      }
    }

    // Select all triple click
    if (wasTripleClickTriggeredRef.current) {
      const contentState = editorState.getCurrentContent()

      const selectAll = editorState.getSelection().merge({
        anchorKey: contentState.getFirstBlock().getKey(),
        anchorOffset: 0,
        focusOffset: contentState.getLastBlock().getText().length,
        focusKey: contentState.getLastBlock().getKey(),
      })

      const newEditorState = EditorState.forceSelection(editorState, selectAll)
      instanceRef.current.setState({editorState: newEditorState})
    }

    wasTripleClickTriggeredRef.current = false

    prevPropsRef.current = props
    prevStateRef.current = instanceRef.current.state
  })

  instanceRef.current.state = {
    editorState,
    editAreaClasses,
    tagRange,
    autocompleteSuggestions,
    focusedTagIndex,
    displayPopover,
    popoverPosition,
    editorFocused,
    clickedOnTag,
    triggerText,
    activeDecorators,
    previousSourceTagMap,
    clickedTag,
  }

  if (!methodsAssignedRef.current) {
    methodsAssignedRef.current = true

    instanceRef.current.setState = (partial, callback) => {
      const resolved =
        typeof partial === 'function'
          ? partial(instanceRef.current.state)
          : partial

      if ('editorState' in resolved) setEditorState(resolved.editorState)
      if ('editAreaClasses' in resolved)
        setEditAreaClasses(resolved.editAreaClasses)
      if ('tagRange' in resolved) setTagRange(resolved.tagRange)
      if ('autocompleteSuggestions' in resolved)
        setAutocompleteSuggestions(resolved.autocompleteSuggestions)
      if ('focusedTagIndex' in resolved)
        setFocusedTagIndex(resolved.focusedTagIndex)
      if ('displayPopover' in resolved)
        setDisplayPopover(resolved.displayPopover)
      if ('popoverPosition' in resolved)
        setPopoverPosition(resolved.popoverPosition)
      if ('editorFocused' in resolved) setEditorFocused(resolved.editorFocused)
      if ('clickedOnTag' in resolved) setClickedOnTag(resolved.clickedOnTag)
      if ('triggerText' in resolved) setTriggerText(resolved.triggerText)
      if ('activeDecorators' in resolved)
        setActiveDecorators(resolved.activeDecorators)
      if ('previousSourceTagMap' in resolved)
        setPreviousSourceTagMap(resolved.previousSourceTagMap)
      if ('clickedTag' in resolved) setClickedTag(resolved.clickedTag)

      // Mirror the resolved partial onto the bridge's state snapshot immediately, so
      // instance.state and any setState callback see up-to-date values without forcing a
      // synchronous React commit (flushSync here previously caused a nested-update-depth
      // crash when triggered from within an in-progress commit, e.g. via focus handlers).
      instanceRef.current.state = {...instanceRef.current.state, ...resolved}

      if (callback) callback()
    }

    instanceRef.current.forceUpdate = () => bumpForceRender()

    instanceRef.current.icuEnabled = icuEnabled

    instanceRef.current.getTextToApplyCounter = getTextToApplyCounterRef.current
    instanceRef.current.getSearchParams = getSearchParamsRef.current
    instanceRef.current.addIcuDecorator = addIcuDecoratorRef.current
    instanceRef.current.addSearchDecorator = addSearchDecoratorRef.current
    instanceRef.current.addQaBlacklistGlossaryDecorator =
      addQaBlacklistGlossaryDecoratorRef.current
    instanceRef.current.addLexiqaDecorator = addLexiqaDecoratorRef.current
    instanceRef.current.setNewTranslation = setNewTranslationRef.current
    instanceRef.current.replaceCurrentSearch = replaceCurrentSearchRef.current
    instanceRef.current.updateTranslationInStore =
      updateTranslationInStoreRef.current
    instanceRef.current.checkDecorators = checkDecoratorsRef.current
    instanceRef.current.copyGlossaryToEditArea =
      copyGlossaryToEditAreaRef.current
    instanceRef.current.refreshTagMap = refreshTagMapRef.current
    instanceRef.current.refreshCharactersCounterRules =
      refreshCharactersCounterRulesRef.current
    instanceRef.current.onCompositionStart = onCompositionStartRef.current
    instanceRef.current.onCompositionEnd = onCompositionEndRef.current
    instanceRef.current.replaceWordAt = replaceWordAtRef.current
    instanceRef.current.focusEditor = focusEditorRef.current
    instanceRef.current.typeTextInEditor = typeTextInEditorRef.current
    instanceRef.current.myKeyBindingFn = myKeyBindingFnRef.current
    instanceRef.current.handleKeyCommand = handleKeyCommandRef.current
    instanceRef.current.insertTagAtSelection = insertTagAtSelectionRef.current
    instanceRef.current.onMouseUpEvent = onMouseUpEventRef.current
    instanceRef.current.onKeyUpEvent = onKeyUpEventRef.current
    instanceRef.current.onBlurEvent = onBlurEventRef.current
    instanceRef.current.onFocus = onFocusRef.current
    instanceRef.current.onCompositionStop = onCompositionStopRef.current
    instanceRef.current.removeDecorator = removeDecoratorRef.current
    instanceRef.current.disableDecorator = disableDecoratorRef.current
    instanceRef.current.onChange = onChangeRef.current
    instanceRef.current.forceSelectionFocus = forceSelectionFocusRef.current
    instanceRef.current.moveUpTagMenuSelection =
      moveUpTagMenuSelectionRef.current
    instanceRef.current.moveDownTagMenuSelection =
      moveDownTagMenuSelectionRef.current
    instanceRef.current.acceptTagMenuSelection =
      acceptTagMenuSelectionRef.current
    instanceRef.current.openPopover = openPopoverRef.current
    instanceRef.current.closePopover = closePopoverRef.current
    instanceRef.current.onTagClick = onTagClickRef.current
    instanceRef.current.onPaste = onPasteRef.current
    instanceRef.current.pasteFragment = pasteFragmentRef.current
    instanceRef.current.copyFragment = copyFragmentRef.current
    instanceRef.current.onDragEvent = onDragEventRef.current
    instanceRef.current.onDragEnd = onDragEndRef.current
    instanceRef.current.handleDrop = handleDropRef.current
    instanceRef.current.onEntityClick = onEntityClickRef.current
    instanceRef.current.getEditorRelativeSelectionOffset =
      getEditorRelativeSelectionOffsetRef.current
    instanceRef.current.getUpdatedSegmentInfo = getUpdatedSegmentInfoRef.current
    instanceRef.current.formatSelection = formatSelectionRef.current
    instanceRef.current.addMissingSourceTagsToTarget =
      addMissingSourceTagsToTargetRef.current

    instanceRef.current.updateTranslationDebounced =
      updateTranslationDebouncedRef.current
    instanceRef.current.onCompositionStopDebounced =
      onCompositionStopDebouncedRef.current
    instanceRef.current.insertTagAtSelectionDebounced =
      insertTagAtSelectionDebouncedRef.current

    Object.defineProperties(instanceRef.current, {
      decoratorsStructure: {
        get: () => decoratorsStructureRef.current,
        configurable: true,
      },
      editor: {
        get: () => editorRef.current,
        set: (value) => {
          editorRef.current = value
        },
        configurable: true,
      },
      props: {
        get: () => liveRef.current.props,
        configurable: true,
      },
    })

    instanceRef.current.isShiftPressedOnNavigation =
      isShiftPressedOnNavigationRef
    instanceRef.current.wasTripleClickTriggered = wasTripleClickTriggeredRef
    instanceRef.current.compositionEventChecks = compositionEventChecksRef
  }

  useImperativeHandle(ref, () => instanceRef.current)

  let lang = ''
  let readonly = false

  if (props.segment) {
    lang = config.target_rfc
    readonly =
      context.readonly ||
      context.locked ||
      props.segment.muted ||
      !props.segment.opened
  }
  const classes = editAreaClasses.slice()
  if (context.locked || context.readonly) {
    classes.push('area')
  } else {
    classes.push('editarea')
  }

  return (
    <div
      className={classes.join(' ')}
      ref={editAreaDomRef}
      id={'segment-' + props.segment.sid + '-editarea'}
      data-sid={props.segment.sid}
      tabIndex="-1"
      onCopy={instanceRef.current.copyFragment}
      onCut={instanceRef.current.copyFragment}
      onMouseUp={instanceRef.current.onMouseUpEvent}
      onBlur={instanceRef.current.onBlurEvent}
      onDragStart={instanceRef.current.onDragEvent}
      onDragEnd={instanceRef.current.onDragEnd}
      onDrop={instanceRef.current.onDragEnd}
      onFocus={instanceRef.current.onFocus}
      onKeyUp={instanceRef.current.onKeyUpEvent}
      lang={config.target_rfc}
      spellCheck={true}
    >
      <Editor
        lang={lang}
        editorState={editorState}
        onChange={instanceRef.current.onChange}
        handlePastedText={instanceRef.current.pasteFragment}
        ref={editorRef}
        readOnly={readonly}
        handleKeyCommand={instanceRef.current.handleKeyCommand}
        keyBindingFn={instanceRef.current.myKeyBindingFn}
        handleDrop={instanceRef.current.handleDrop}
        spellCheck={true}
        textAlignment={config.isTargetRTL ? 'right' : 'left'}
        textDirectionality={config.isTargetRTL ? 'RTL' : 'LTR'}
      />
      <TagBox
        displayPopover={displayPopover}
        suggestions={autocompleteSuggestions}
        onTagClick={instanceRef.current.onTagClick}
        focusedTagIndex={focusedTagIndex}
        popoverPosition={popoverPosition}
      />
    </div>
  )
})

function getEntityStrategy(mutability) {
  return function (contentBlock, callback, contentState) {
    contentBlock.findEntityRanges((character) => {
      const entityKey = character.getEntity()
      if (entityKey === null) {
        return false
      }
      return contentState.getEntity(entityKey).getMutability() === mutability
    }, callback)
  }
}

Editarea.displayName = 'Editarea'

export default Editarea
