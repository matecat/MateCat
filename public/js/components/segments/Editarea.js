import React, {
  forwardRef,
  useContext,
  useEffect,
  useImperativeHandle,
  useReducer,
  useRef,
} from 'react'
import {fromJS} from 'immutable'
import {
  Modifier,
  Editor,
  EditorState,
  getDefaultKeyBinding,
  CompositeDecorator,
} from 'draft-js'
import {remove, cloneDeep, findIndex, size, isEqual} from 'lodash'
import {debounce} from 'lodash/function'

import SegmentConstants from '../../constants/SegmentConstants'
import EditAreaConstants from '../../constants/EditAreaConstants'
import SegmentStore from '../../stores/SegmentStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import * as DraftMatecatConstants from './utils/DraftMatecatUtils/editorConstants'
import resolveEditorCommand from './utils/DraftMatecatUtils/resolveEditorCommand'
import getEditorRelativeSelectionOffset from './utils/DraftMatecatUtils/getEditorRelativeSelectionOffset'
import buildPastedEditorState from './utils/DraftMatecatUtils/buildPastedEditorState'
import resolveDrop from './utils/DraftMatecatUtils/resolveDrop'
import selectionAroundEntity from './utils/DraftMatecatUtils/selectionAroundEntity'
import TagEntity from './TagEntity/TagEntity.component'
import SegmentUtils from '../../utils/segmentUtils'
import CommonUtils from '../../utils/commonUtils'
import TagBox from './utils/DraftMatecatUtils/TagMenu/TagBox'
import insertTag from './utils/DraftMatecatUtils/TagMenu/insertTag'
import checkForMissingTags from './utils/DraftMatecatUtils/TagMenu/checkForMissingTag'
import LexiqaUtils from '../../utils/lxq.main'
import updateOffsetBasedOnEditorState from './utils/DraftMatecatUtils/updateOffsetBasedOnEditorState'
import {tagSignatures} from './utils/DraftMatecatUtils/tagModel'
import SegmentActions from '../../actions/SegmentActions'
import matchTypingSequence from '../../utils/matchTypingSequence/matchTypingSequence'
import {SegmentContext} from './SegmentContext'
import CatToolStore from '../../stores/CatToolStore'
import {
  checkCaretIsNearEntity,
  adjustCaretPosition,
  isCaretInsideEntity,
  getEntitiesSelected,
} from './utils/DraftMatecatUtils/manageCaretPositionNearEntity'
import {
  createICUDecorator,
  createIcuTokens,
  isEqualICUTokens,
} from './utils/DraftMatecatUtils/createICUDecorator'
import {removeZeroWidthSpace} from './utils/DraftMatecatUtils/tagUtils'
import textUtils from '../../utils/textUtils'
import ContextPreviewChannel from '../../utils/contextPreviewChannel'

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

/**
 * Editarea holds its state in one object, so a single dispatch replaces the
 * thirteen setters the class port needed. Merging rather than replacing keeps
 * the setState(partial) shape the rest of the component is written against.
 */
const mergeState = (state, partial) => ({...state, ...partial})

const Editarea = forwardRef(
  ({segment, translation, updateCounter, toggleFormatMenu}, ref) => {
    const context = useContext(SegmentContext)

    // A snapshot of this render's props, kept as one object because two things
    // need props *collectively* rather than field by field: propsRef, which the
    // frozen call sites read through, and prevPropsRef, which the
    // componentDidUpdate-equivalent effect diffs against.
    const currentProps = {segment, translation, updateCounter, toggleFormatMenu}

    const propsRef = useRef({})

    // Plain instance fields (createRef equivalents) preserved as-is
    const isShiftPressedOnNavigationRef = useRef(undefined)
    const wasTripleClickTriggeredRef = useRef(undefined)
    const compositionEventChecksRef = useRef(undefined)
    const editorRef = useRef(null)
    const editAreaDomRef = useRef(null)
    // Per instance, not per module: onComposition gates checkDecorators, so a
    // single shared object would let typing in one Editarea suppress decorator
    // recalculation in another. draggingFromEditArea was only ever created on
    // assignment; it is declared here so the shape is visible.
    const editorSyncRef = useRef({
      editorFocused: true,
      onComposition: false,
      draggingFromEditArea: false,
    })
    // The class held this node in `this.editAreaRef`, and consumers of the
    // imperative handle still read it under that name — the AI alternatives
    // button asks whether focus sits inside the editor. Mirror it onto the
    // instance on commit, exactly as the class's callback ref did, or that read
    // is `undefined.contains(...)` and takes the page down.
    const setEditAreaDom = (node) => {
      editAreaDomRef.current = node
    }
    // this.prevIcuTokens (plain mutable instance field, internal only)
    const prevIcuTokensRef = useRef(undefined)

    // ---- method closures: seeded once where a frozen call site needs a stable
    // identity, plain per-render consts otherwise. All read through propsRef and
    // stateRef, so either kind sees current data. ----

    const getTextToApplyCounter = (translation) => {
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
    }

    const getSearchParamsRef = useRef(() => {
      const {
        inSearch,
        currentInSearch,
        searchParams,
        occurrencesInSearch,
        currentInSearchIndex,
      } = propsRef.current.segment
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

    const addIcuDecorator = (tokens) => {
      const newDecorator = createICUDecorator(tokens)
      remove(
        decoratorsStructureRef.current,
        (decorator) => decorator.name === DraftMatecatConstants.ICU_DECORATOR,
      )
      decoratorsStructureRef.current.push(newDecorator)
    }

    const addSearchDecorator = () => {
      const {tagRange} = stateRef.current
      const {searchParams, occurrencesInSearch, currentInSearchIndex} =
        propsRef.current.segment
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
        (decorator) =>
          decorator.name === DraftMatecatConstants.SEARCH_DECORATOR,
      )
      decoratorsStructureRef.current.push(newDecorator)
    }

    const addQaBlacklistGlossaryDecorator = () => {
      const {qaBlacklistGlossary, sid} = propsRef.current.segment
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
    }

    const addLexiqaDecorator = () => {
      const {editorState} = stateRef.current
      const {lexiqa, sid, lxqDecodedTranslation} = propsRef.current.segment
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
          getUpdatedSegmentInfoRef.current,
          replaceWordAt,
        )
        remove(
          decoratorsStructureRef.current,
          (decorator) =>
            decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR,
        )
        decoratorsStructureRef.current.push(newDecorator)
      } else {
        removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
      }
    }

    // Receive the new translation and decode it for draftJS
    const setNewTranslationRef = useRef((sid, translation) => {
      if (sid === propsRef.current.segment.sid) {
        const {editorState} = stateRef.current
        const contentEncoded = DraftMatecatUtils.encodeContent(
          editorState,
          translation,
          propsRef.current.segment.sourceTagMap,
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

        propsRef.current.updateCounter(
          DraftMatecatUtils.getCharactersCounter(
            getTextToApplyCounter(translation),
          ),
        )
        setState(
          {
            editorState: newEditorState,
          },
          () => {
            updateTranslationDebouncedRef.current()
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
      } = propsRef.current.segment
      if (currentInSearch && searchParams.target) {
        const index = findIndex(
          occurrencesInSearch.occurrences,
          (item) => item.searchProgressiveIndex === currentInSearchIndex,
        )
        const newEditorState = DraftMatecatUtils.replaceOccurrences(
          stateRef.current.editorState,
          searchParams.target,
          text,
          index,
        )
        setState(
          {
            editorState: newEditorState,
          },
          () => {
            updateTranslationInStoreRef.current()
          },
        )
      }
    })

    const updateTranslationInStoreRef = useRef(() => {
      const {editorState} = stateRef.current
      const {
        segment,
        segment: {sourceTagMap},
      } = propsRef.current
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
        propsRef.current.updateCounter(
          DraftMatecatUtils.getCharactersCounter(
            getTextToApplyCounter(decodedSegment),
          ),
        )
        SegmentActions.startSegmentQACheck()
      } else {
        propsRef.current.updateCounter(0)
      }
    })

    const checkDecoratorsRef = useRef((prevProps) => {
      let changedDecorator = false
      const {inSearch} = propsRef.current.segment
      const prevActiveDecorators = stateRef.current.activeDecorators
      const {editorState} = stateRef.current
      const activeDecorators = {...prevActiveDecorators}

      if (!inSearch) {
        // Qa Check Blacklist
        const {qaBlacklistGlossary} = propsRef.current.segment
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
          addQaBlacklistGlossaryDecorator()
        } else if (
          prevQaBlacklistGlossary &&
          prevQaBlacklistGlossary.length > 0 &&
          (!qaBlacklistGlossary || qaBlacklistGlossary.length === 0)
        ) {
          activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
          changedDecorator = true
          removeDecorator(DraftMatecatConstants.QA_BLACKLIST_DECORATOR)
        }

        // Lexiqa
        const {lexiqa} = propsRef.current.segment
        const prevLexiqa = prevProps ? prevProps.segment.lexiqa : undefined
        const currentLexiqaTarget =
          lexiqa && lexiqa.target && size(lexiqa.target)
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
          addLexiqaDecorator()
        } else if (prevLexiqaTarget && !currentLexiqaTarget) {
          activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
          changedDecorator = true
          removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
        }
        // Search
        if (prevProps && prevProps.segment.inSearch) {
          activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
          changedDecorator = true
          removeDecorator(DraftMatecatConstants.SEARCH_DECORATOR)
        }
        const contentState = editorState.getCurrentContent()
        const plainText = textUtils.removeWhitespacePlaceholders(
          contentState.getPlainText(),
        )
        if (propsRef.current.segment.icu) {
          const icuTokens = createIcuTokens(
            plainText,
            editorState,
            config.target_code,
          )

          if (
            !prevProps ||
            !prevIcuTokensRef.current ||
            !isEqualICUTokens(icuTokens, prevIcuTokensRef.current)
          ) {
            prevIcuTokensRef.current = icuTokens
            changedDecorator = true
            addIcuDecorator(icuTokens)
          }
        }
      } else {
        // Search
        if (
          propsRef.current.segment.searchParams.target &&
          (!prevProps ||
            !prevProps.segment.inSearch || // Before was not active
            (prevProps.segment.inSearch &&
              !fromJS(prevProps.segment.searchParams).equals(
                fromJS(propsRef.current.segment.searchParams),
              )) || // Before was active but some params change
            (prevProps.segment.inSearch &&
              prevProps.segment.currentInSearch !==
                propsRef.current.segment.currentInSearch) || // Before was the current
            (prevProps.segment.inSearch &&
              prevProps.segment.currentInSearchIndex !==
                propsRef.current.segment.currentInSearchIndex))
        ) {
          // There are more occurrences and the current change
          // Cleanup all decorators
          removeDecorator()
          activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
          activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
          addSearchDecorator()
          activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
          changedDecorator = true
        }
      }

      if (changedDecorator) {
        const timer = inSearch ? 400 : 0
        const decorator = new CompositeDecorator(decoratorsStructureRef.current)
        setTimeout(() => {
          setState({
            editorState: EditorState.set(editorState, {decorator}),
            activeDecorators,
          })
        }, timer)
      }
    })

    const copyGlossaryToEditAreaRef = useRef((segment, glossaryTranslation) => {
      if (segment.sid === propsRef.current.segment.sid) {
        const {editorState} = stateRef.current
        const newEditorState = DraftMatecatUtils.insertText(
          editorState,
          glossaryTranslation,
        )
        setState(
          {
            editorState: newEditorState,
          },
          () => {
            updateTranslationDebouncedRef.current()
          },
        )
      }
    })

    const refreshTagMapRef = useRef(() => {
      setNewTranslationRef.current(
        propsRef.current.segment.sid,
        propsRef.current.translation,
      )
      setTimeout(() => checkDecoratorsRef.current(), 100)
    })

    const refreshCharactersCounterRulesRef = useRef(() => {
      setNewTranslationRef.current(
        propsRef.current.segment.sid,
        propsRef.current.translation,
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

    const replaceWordAt = ({newWord, start, end}) => {
      const startIndex = start
      const endIndex = end
      const selection = stateRef.current.editorState.getSelection().merge({
        anchorOffset: startIndex,
        focusOffset: endIndex,
      })
      const contentState = Modifier.replaceText(
        stateRef.current.editorState.getCurrentContent(),
        selection,
        newWord,
      )
      const updatedState = EditorState.push(
        stateRef.current.editorState,
        contentState,
      )
      setState({editorState: updatedState}, () => {
        // Reactivate decorators
        updateTranslationDebouncedRef.current()
        // Stop composition mode
        onCompositionStopDebouncedRef.current()
      })
    }

    const focusEditorRef = useRef(() => {
      if (editorRef.current) editorRef.current.focus()
    })

    const typeTextInEditor = (textToInsert) => {
      const {editorState} = stateRef.current
      editorSyncRef.current.onComposition = true
      let newEditorState = disableDecorator(
        editorState,
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      newEditorState = DraftMatecatUtils.insertText(
        newEditorState,
        textToInsert,
      )
      setState(
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
          updateTranslationDebouncedRef.current()
          // Reactivate decorators
          onCompositionStopDebouncedRef.current()
        },
      )
    }

    const myKeyBindingFn = (e) => {
      const {
        command,
        editorState: adjusted,
        applyVia,
        typeText,
        clearTriggerText,
        shiftOnNavigation,
      } = resolveEditorCommand(e, {
        displayPopover: stateRef.current.displayPopover,
        editorState: stateRef.current.editorState,
        isRTL: Boolean(config.isTargetRTL),
        isChromeBook: navigator.userAgent.indexOf('CrOS') > -1,
        hasSpaceTag: tagSignatures.space,
        selectionIsCaret: () => window.getSelection().type === 'Caret',
        typingWordJoiner,
      })

      // The resolver decides; applying what it decided happens here.
      if (shiftOnNavigation !== undefined)
        isShiftPressedOnNavigationRef.current = shiftOnNavigation
      if (clearTriggerText) setState({triggerText: null})
      if (typeText) typeTextInEditor(typeText)
      if (adjusted) {
        if (applyVia === 'onChange') onChange(adjusted)
        else setState({editorState: adjusted})
      }

      return command === null ? getDefaultKeyBinding(e) : command
    }

    const handleKeyCommand = (command) => {
      const {
        segment: {sourceTagMap, missingTagsInTarget},
      } = propsRef.current

      switch (command) {
        case 'toggle-tag-menu': {
          const tagSuggestions = {
            missingTags: missingTagsInTarget,
            sourceTags: sourceTagMap,
          }
          if (
            tagSuggestions.sourceTags &&
            tagSuggestions.sourceTags.length > 0
          ) {
            openPopover(
              tagSuggestions,
              getEditorRelativeSelectionOffset(editorRef.current.editor),
            )
          }
          return 'handled'
        }
        case 'close-tag-menu':
          closePopover()
          return 'handled'
        case 'up-arrow-press':
          moveUpTagMenuSelection()
          return 'handled'
        case 'down-arrow-press':
          moveDownTagMenuSelection()
          return 'handled'
        case 'enter-press':
          acceptTagMenuSelection()
          return 'handled'
        case 'insert-tab-tag':
          insertTagAtSelectionDebouncedRef.current('tab')
          return 'handled'
        case 'insert-space-tag':
          if (tagSignatures.space) {
            insertTagAtSelectionDebouncedRef.current('space')
            return 'handled'
          } else {
            return 'not-handled'
          }

        case 'insert-nbsp-tag':
          insertTagAtSelectionDebouncedRef.current('nbsp')
          return 'handled'
        case 'insert-word-joiner-tag':
          insertTagAtSelectionDebouncedRef.current('wordJoiner')
          return 'handled'
        case 'translate':
          return 'not-handled'
        case 'next-translate':
          return 'not-handled'
        // Nothing left to do for these here: the caret moves and the quote
        // insertion were already applied from resolveEditorCommand's result, and
        // add-issue is picked up by a shortcut listener outside the editor.
        // 'handled' is still required -- it is what stops Draft running its own
        // handling on top, which would move the caret or delete a second time.
        case 'left-nav':
        case 'right-nav':
        case 'add-issue':
        case 'delete-entity':
        case 'quote-shortcut':
          return 'handled'
        default:
          return 'not-handled'
      }
    }

    const insertTagAtSelectionRef = useRef((tagName) => {
      const {editorState} = stateRef.current
      const customTag = DraftMatecatUtils.structFromName(tagName)
      // If tag creation has failed, return
      if (!customTag) return
      // Start composition mode and remove lexiqa
      editorSyncRef.current.onComposition = true
      let newEditorState = disableDecorator(
        editorState,
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )

      newEditorState = insertTag(customTag, newEditorState)

      setState(
        (prevState) => ({
          activeDecorators: {
            ...prevState.activeDecorators,
            [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
          },
          editorState: newEditorState,
        }),
        () => {
          // Reactivate decorators
          updateTranslationDebouncedRef.current()
          // Stop composition mode
          onCompositionStopDebouncedRef.current()
        },
      )
    })

    const onMouseUpEvent = () => {
      const {toggleFormatMenu} = propsRef.current
      toggleFormatMenu(
        !editorRef.current._latestEditorState.getSelection().isCollapsed(),
      )
    }

    const onKeyUpEvent = (event) => {
      if (
        event.key === 'ArrowLeft' ||
        event.key === 'ArrowRight' ||
        event.key === 'ArrowUp' ||
        event.key === 'ArrowDown'
      ) {
        const {toggleFormatMenu} = propsRef.current
        toggleFormatMenu(
          !editorRef.current._latestEditorState.getSelection().isCollapsed(),
        )
      }
    }

    const onBlurEvent = () => {
      const {toggleFormatMenu} = propsRef.current
      editorSyncRef.current.editorFocused = false
      // Hide Edit Toolbar
      toggleFormatMenu(false)
    }

    const onFocus = () => {
      editorSyncRef.current.editorFocused = true
    }

    const onCompositionStopRef = useRef(() => {
      if (editorSyncRef.current.onComposition) {
        editorSyncRef.current.onComposition = false
        // Tell tags to update themself
        setTimeout(() => {
          SegmentActions.editAreaChanged(propsRef.current.segment.sid, true)
        })
      }
    })

    const removeDecorator = (decoratorName) => {
      if (!decoratorName) {
        remove(
          decoratorsStructureRef.current,
          (decorator) =>
            decorator.name !== DraftMatecatConstants.TAGS_DECORATOR,
        )
      } else {
        remove(
          decoratorsStructureRef.current,
          (decorator) => decorator.name === decoratorName,
        )
      }
    }

    // has to be followed by a setState for editorState
    const disableDecorator = (editorState, decoratorName) => {
      remove(
        decoratorsStructureRef.current,
        (decorator) => decorator.name === decoratorName,
      )
      const decorator = new CompositeDecorator(decoratorsStructureRef.current)
      return EditorState.set(editorState, {decorator})
    }

    const onChange = (editorState) => {
      const {displayPopover, activeDecorators} = stateRef.current
      const prevEditorState = stateRef.current.editorState

      // check caret is inside entity and restore previous editorState
      if (
        isCaretInsideEntity() ||
        compositionEventChecksRef.current?.startIsInsideEntity
      ) {
        const updatedStateNearEntity = checkCaretIsNearEntity({
          editorState,
        })

        setState(
          () => ({
            editorState: updatedStateNearEntity
              ? updatedStateNearEntity
              : prevEditorState,
          }),
          () => {
            onCompositionStopDebouncedRef.current()
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
      if (displayPopover) closePopover()
      if (contentChanged) {
        // Stop checking decorators while typing...
        editorSyncRef.current.onComposition = true
        // ...remove unwanted decorators like lexiqa and qa blacklist...
        if (activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR]) {
          editorState = disableDecorator(
            editorState,
            DraftMatecatConstants.LEXIQA_DECORATOR,
          )
          newActiveDecorators = {
            ...newActiveDecorators,
            [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
          }
        }
        if (activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR]) {
          editorState = disableDecorator(
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
        setState(
          () => ({
            activeDecorators: newActiveDecorators,
            editorState: editorState,
          }),
          () => {
            // Reactivate decorators
            updateTranslationDebouncedRef.current()
            onCompositionStopDebouncedRef.current()
          },
        )
      } else {
        setState(
          () => ({
            editorState: editorState,
          }),
          () => {
            onCompositionStopDebouncedRef.current()
          },
        )
      }
    }

    // fix cursor jump at the beginning
    // Methods for TagMenu ---- START
    const moveUpTagMenuSelection = () => {
      const {displayPopover} = stateRef.current
      if (!displayPopover) return
      const {
        focusedTagIndex,
        autocompleteSuggestions: {missingTags, sourceTags},
      } = stateRef.current
      const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
      const newFocusedTagIndex =
        focusedTagIndex - 1 < 0
          ? mergeAutocompleteSuggestions.length - 1
          : (focusedTagIndex - 1) % mergeAutocompleteSuggestions.length

      setState({
        focusedTagIndex: newFocusedTagIndex,
      })
    }

    const moveDownTagMenuSelection = () => {
      const {displayPopover} = stateRef.current
      if (!displayPopover) return
      const {
        focusedTagIndex,
        autocompleteSuggestions: {missingTags, sourceTags},
      } = stateRef.current
      const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
      setState({
        focusedTagIndex:
          (focusedTagIndex + 1) % mergeAutocompleteSuggestions.length,
      })
    }

    const acceptTagMenuSelection = () => {
      const {
        focusedTagIndex,
        displayPopover,
        editorState,
        triggerText,
        autocompleteSuggestions: {missingTags = [], sourceTags},
      } = stateRef.current
      if (!displayPopover) return
      const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
      const selectedTag = mergeAutocompleteSuggestions[focusedTagIndex]
      // Start typing
      editorSyncRef.current.onComposition = true
      // Remove lexiqa while typing
      const newEditorState = disableDecorator(
        editorState,
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      const editorStateWithSuggestedTag = insertTag(
        selectedTag,
        newEditorState,
        triggerText,
      )
      setState(
        (prevState) => ({
          activeDecorators: {
            ...prevState.activeDecorators,
            [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
          },
          editorState: editorStateWithSuggestedTag,
          displayPopover: false,
          triggerText: null,
        }),
        () => {
          // Reactivate decorators
          updateTranslationDebouncedRef.current()
          // Stop typing
          onCompositionStopDebouncedRef.current()
        },
      )
    }

    const openPopover = (suggestions, position) => {
      // Posizione da salvare e passare al compoennte
      const popoverPosition = {
        top: position.top,
        left: position.left,
      }

      setState({
        displayPopover: true,
        autocompleteSuggestions: suggestions,
        focusedTagIndex: 0,
        popoverPosition: popoverPosition,
      })
    }

    const closePopover = () => {
      setState({
        displayPopover: false,
        triggerText: null,
      })
    }

    const onTagClick = (suggestionTag) => {
      const {editorState, triggerText} = stateRef.current
      // Start typing...
      editorSyncRef.current.onComposition = true
      // Disable lexiqa while typing
      const newEditorState = disableDecorator(
        editorState,
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      const editorStateWithSuggestedTag = insertTag(
        suggestionTag,
        newEditorState,
        triggerText,
      )
      setState(
        (prevState) => ({
          activeDecorators: {
            ...prevState.activeDecorators,
            [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
          },
          editorState: editorStateWithSuggestedTag,
          editorFocused: true,
          displayPopover: false,
          triggerText: null,
        }),
        () => {
          // Reactivate decorators
          updateTranslationDebouncedRef.current()
          // Stop typing
          onCompositionStopDebouncedRef.current()
        },
      )
    }
    // Methods for TagMenu ---- END

    const pasteFragment = (text) => {
      const {editorState} = stateRef.current
      const {fragment: clipboardFragment, plainText: clipboardPlainText} =
        SegmentStore.getFragmentFromClipboard()

      const pasted = buildPastedEditorState({
        text,
        clipboardFragment,
        clipboardPlainText,
        editorState,
      })
      // null means let Draft paste the plain text itself
      if (!pasted) return false

      setState({editorState: pasted}, () => {
        updateTranslationDebouncedRef.current()
      })
      return true
    }

    const copyFragment = (e) => {
      const internalClipboard = editorRef.current.getClipboard()
      const {editorState} = stateRef.current
      if (internalClipboard) {
        e.preventDefault()
        // Get plain text form internalClipboard fragment
        const plainText = internalClipboard
          .map((block) => block.getText())
          .join('\n')
          .replace(
            new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'),
            '',
          )
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
    }

    const onDragEvent = () => {
      editorSyncRef.current.draggingFromEditArea = true
    }

    const onDragEnd = () => {
      editorSyncRef.current.draggingFromEditArea = false
    }

    const handleDrop = (selection, dataTransfer) => {
      const {editorState} = stateRef.current
      const {
        outcome,
        editorState: dropped,
        highlightTags,
      } = resolveDrop({
        editorState,
        selection,
        text: dataTransfer.getText(),
        draggingFromEditArea: editorSyncRef.current.draggingFromEditArea,
      })

      if (dropped) {
        setState({editorState: dropped}, () => {
          updateTranslationDebouncedRef.current()
          if (highlightTags) setTimeout(() => SegmentActions.highlightTags())
        })
      }
      return outcome
    }

    const onEntityClickRef = useRef((start, end) => {
      const {editorState} = stateRef.current
      try {
        // _latestEditorState, not ours: the click has already moved the caret
        const latestEditorState = editorRef.current._latestEditorState
        const selectionState = latestEditorState.getSelection()
        const blockText = latestEditorState
          .getCurrentContent()
          .getBlockForKey(selectionState.getFocusKey())
          .getText()

        setState({
          editorState: EditorState.forceSelection(
            editorState,
            selectionState.merge(selectionAroundEntity(blockText, start, end)),
          ),
        })
      } catch (e) {
        console.log('Invalid selection')
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
      } = propsRef.current
      const {tagRange, editorState} = stateRef.current
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

    const formatSelection = (format) => {
      const {editorState} = stateRef.current
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

      setState(
        {
          editorState: newEditorState,
        },
        () => {
          updateTranslationDebouncedRef.current()
        },
      )
    }

    const addMissingSourceTagsToTarget = () => {
      const {segment} = propsRef.current
      const {editorState} = stateRef.current
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
      setState({
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
          ...propsRef.current.segment,
          translation: newTranslation,
        })
      }, 100)
    }

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
            sid: segment.sid,
          },
        },
      ]
    }

    // ---- initial content, computed once ----
    const initialContentRef = useRef(null)
    if (initialContentRef.current === null) {
      // If GuessTag is Enabled, clean translation from tags
      const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(
        segment,
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

    const [state, dispatchState] = useReducer(mergeState, null, () => ({
      editorState: initialContentRef.current.editorState,
      editAreaClasses: ['targetarea'],
      tagRange: initialContentRef.current.tagRange,
      autocompleteSuggestions: [],
      focusedTagIndex: 0,
      displayPopover: false,
      popoverPosition: {},
      editorFocused: true,
      triggerText: null,
      activeDecorators: {
        [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        [DraftMatecatConstants.QA_BLACKLIST_DECORATOR]: false,
        [DraftMatecatConstants.SEARCH_DECORATOR]: false,
        [DraftMatecatConstants.ICU_DECORATOR]: segment.icu,
      },
      previousSourceTagMap: null,
    }))

    // Mirrors `state` for synchronous read-back; see setState below.
    const stateRef = useRef(state)
    stateRef.current = state

    /**
     * The setState the class port was written against. It advances stateRef
     * synchronously before dispatching, because a callback -- and a store
     * listener that sets state then reads straight back, as
     * replaceCurrentSearch does -- has to observe the new values, which a
     * dispatch alone would not provide until the next render.
     *
     * Safe for a once-built closure to capture: it touches only stateRef and
     * dispatchState, both stable for the life of the component.
     */
    const setState = (partial, callback) => {
      const resolved =
        typeof partial === 'function' ? partial(stateRef.current) : partial

      stateRef.current = {...stateRef.current, ...resolved}
      dispatchState(resolved)

      if (callback) callback()
    }

    const {
      editorState,
      editAreaClasses,
      autocompleteSuggestions,
      focusedTagIndex,
      displayPopover,
      popoverPosition,
      previousSourceTagMap,
    } = state
    // constructor-time synchronous side effect: this.props.updateCounter(...)
    const constructorRanRef = useRef(false)
    if (!constructorRanRef.current) {
      constructorRanRef.current = true
      updateCounter(
        DraftMatecatUtils.getCharactersCounter(
          getTextToApplyCounter(translation),
        ),
      )
    }

    // debounced functions, constructed once (recreating them would drop pending timers)
    const updateTranslationDebouncedRef = useRef(null)
    if (updateTranslationDebouncedRef.current === null) {
      updateTranslationDebouncedRef.current = debounce(
        () => updateTranslationInStoreRef.current(),
        100,
      )
    }
    const onCompositionStopDebouncedRef = useRef(null)
    if (onCompositionStopDebouncedRef.current === null) {
      onCompositionStopDebouncedRef.current = debounce(
        () => onCompositionStopRef.current(),
        1000,
      )
    }
    // insertTagAtSelection debounced function avoids broken insert for languages with oncomposition event ex. Korean
    const insertTagAtSelectionDebouncedRef = useRef(null)
    if (insertTagAtSelectionDebouncedRef.current === null) {
      insertTagAtSelectionDebouncedRef.current = debounce(
        (tagName) => insertTagAtSelectionRef.current(tagName),
        1,
      )
    }

    // refresh every render so stable closures always see the current props
    propsRef.current = currentProps

    const isFirstRenderRef = useRef(true)
    const prevPropsRef = useRef(currentProps)
    const prevStateRef = useRef(null)

    // componentDidMount / componentWillUnmount equivalent
    useEffect(() => {
      // Captured at mount so the cleanup removes the very same references,
      // rather than re-reading the refs after React has torn the component down.
      const setNewTranslation = setNewTranslationRef.current
      const replaceCurrentSearch = replaceCurrentSearchRef.current
      const copyGlossaryToEditArea = copyGlossaryToEditAreaRef.current
      const refreshTagMap = refreshTagMapRef.current
      const refreshCharactersCounterRules =
        refreshCharactersCounterRulesRef.current
      const onCompositionStart = onCompositionStartRef.current
      const onCompositionEnd = onCompositionEndRef.current

      SegmentStore.addListener(
        SegmentConstants.REPLACE_TRANSLATION,
        setNewTranslation,
      )
      SegmentStore.addListener(
        EditAreaConstants.REPLACE_SEARCH_RESULTS,
        replaceCurrentSearch,
      )
      SegmentStore.addListener(
        EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
        copyGlossaryToEditArea,
      )
      SegmentStore.addListener(SegmentConstants.REFRESH_TAG_MAP, refreshTagMap)
      SegmentStore.addListener(
        SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
        refreshCharactersCounterRules,
      )
      setTimeout(() => {
        checkDecoratorsRef.current()
        updateTranslationInStoreRef.current()
        if (propsRef.current.segment.opened) {
          focusEditorRef.current()
        }
      })

      const {editor: editorElement} = editorRef.current
      editorElement.addEventListener('compositionstart', onCompositionStart)
      editorElement.addEventListener('compositionend', onCompositionEnd)

      new CommonUtils.DetectTripleClick(editAreaDomRef.current, () => {
        wasTripleClickTriggeredRef.current = true
      })

      return () => {
        SegmentStore.removeListener(
          SegmentConstants.REPLACE_TRANSLATION,
          setNewTranslation,
        )
        SegmentStore.removeListener(
          EditAreaConstants.REPLACE_SEARCH_RESULTS,
          replaceCurrentSearch,
        )
        SegmentStore.removeListener(
          EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
          copyGlossaryToEditArea,
        )
        SegmentStore.removeListener(
          SegmentConstants.REFRESH_TAG_MAP,
          refreshTagMap,
        )
        SegmentStore.removeListener(
          SegmentConstants.CHANGE_CHARACTERS_COUNTER_RULES,
          refreshCharactersCounterRules,
        )

        // captured above, not re-read here: by the time this passive-effect
        // cleanup runs, React has already nulled editorRef.current
        editorElement.removeEventListener(
          'compositionstart',
          onCompositionStart,
        )
        editorElement.removeEventListener('compositionend', onCompositionEnd)
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    // componentDidUpdate equivalent
    useEffect(() => {
      if (isFirstRenderRef.current) {
        isFirstRenderRef.current = false
        prevPropsRef.current = currentProps
        prevStateRef.current = stateRef.current
        return
      }

      const prevProps = prevPropsRef.current
      const prevState = prevStateRef.current

      if (!prevProps.segment.opened && segment.opened) {
        const newEditorState = EditorState.moveFocusToEnd(editorState)
        setState({editorState: newEditorState})
      } else if (prevProps.segment.opened && !segment.opened) {
        const newEditorState = EditorState.moveSelectionToEnd(editorState)
        setState({editorState: newEditorState})
      }
      if (
        !editorState.isInCompositionMode() &&
        !editorSyncRef.current.onComposition
      ) {
        checkDecoratorsRef.current(prevProps)
      }

      // update editor state when receive prop of segment "sourceTagMap"
      if (
        segment.sourceTagMap?.length &&
        !isEqual(previousSourceTagMap, segment.sourceTagMap)
      ) {
        setState({
          previousSourceTagMap: segment.sourceTagMap,
        })
        setNewTranslationRef.current(segment.sid, translation)
      }

      // Adjust caret position and set focus to entity
      if (prevState.editorState !== editorState) {
        const entitiesSelected = getEntitiesSelected(editorState)
        SegmentActions.focusTags(
          editorSyncRef.current.editorFocused ? entitiesSelected : [],
        )

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

        const newEditorState = EditorState.forceSelection(
          editorState,
          selectAll,
        )
        setState({editorState: newEditorState})
      }

      wasTripleClickTriggeredRef.current = false

      prevPropsRef.current = currentProps
      prevStateRef.current = stateRef.current
    })

    // The component's public API: exactly the four members production reaches
    // through the ref, and nothing else. SegmentTarget calls
    // addMissingSourceTagsToTarget, SegmentTargetToolbar calls formatSelection,
    // and AiAlternatives reads state.editorState and editAreaRef. state and
    // editAreaRef are getters so callers keep seeing the live values rather than
    // a snapshot taken when the handle was built.
    // Refreshed every render so the handle, whose factory runs once, always
    // reaches the current closures rather than the first render's.
    const handleRef = useRef(null)
    handleRef.current = {addMissingSourceTagsToTarget, formatSelection}

    useImperativeHandle(
      ref,
      () => ({
        // Read through handleRef, not the locals: this factory runs once (the
        // dependency array is empty), so calling the locals directly would pin
        // the handle to whichever closures existed at the first render.
        addMissingSourceTagsToTarget: (...args) =>
          handleRef.current.addMissingSourceTagsToTarget(...args),
        formatSelection: (...args) =>
          handleRef.current.formatSelection(...args),
        get state() {
          return stateRef.current
        },
        get editAreaRef() {
          return editAreaDomRef.current
        },
      }),
      [],
    )

    let lang = ''
    let readonly = false

    if (segment) {
      lang = config.target_code
      readonly =
        context.readonly || context.locked || segment.muted || !segment.opened
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
        ref={setEditAreaDom}
        id={'segment-' + segment.sid + '-editarea'}
        data-sid={segment.sid}
        tabIndex="-1"
        onCopy={copyFragment}
        onCut={copyFragment}
        onMouseUp={onMouseUpEvent}
        onBlur={onBlurEvent}
        onDragStart={onDragEvent}
        onDragEnd={onDragEnd}
        onDrop={onDragEnd}
        onFocus={onFocus}
        onKeyUp={onKeyUpEvent}
        lang={config.target_code}
        spellCheck={true}
      >
        <Editor
          lang={lang}
          editorState={editorState}
          onChange={onChange}
          handlePastedText={pasteFragment}
          ref={editorRef}
          readOnly={readonly}
          handleKeyCommand={handleKeyCommand}
          keyBindingFn={myKeyBindingFn}
          handleDrop={handleDrop}
          spellCheck={true}
          textAlignment={config.isTargetRTL ? 'right' : 'left'}
          textDirectionality={config.isTargetRTL ? 'RTL' : 'LTR'}
        />
        <TagBox
          displayPopover={displayPopover}
          suggestions={autocompleteSuggestions}
          onTagClick={onTagClick}
          focusedTagIndex={focusedTagIndex}
          popoverPosition={popoverPosition}
        />
      </div>
    )
  },
)

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
