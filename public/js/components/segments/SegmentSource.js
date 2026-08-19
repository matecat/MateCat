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
import {remove, cloneDeep, size, isUndefined} from 'lodash'
import {CompositeDecorator, Editor, EditorState, Modifier} from 'draft-js'

import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import {Shortcuts} from '../../utils/shortcuts'
import TagEntity from './TagEntity/TagEntity.component'
import SegmentUtils from '../../utils/segmentUtils'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import * as DraftMatecatConstants from './utils/DraftMatecatUtils/editorConstants'
import SegmentConstants from '../../constants/SegmentConstants'
import LexiqaUtils from '../../utils/lxq.main'
import updateOffsetBasedOnEditorState from './utils/DraftMatecatUtils/updateOffsetBasedOnEditorState'
import getFragmentFromSelection from './utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import {tagSignatures} from './utils/DraftMatecatUtils/tagModel'
import {SegmentContext} from './SegmentContext'
import Assistant from '../../../img/icons/Assistant'
import Education from '../../../img/icons/Education'
import {TERM_FORM_FIELDS} from './SegmentFooterTabGlossary/GlossaryConstants'
import {getEntitiesSelected} from './utils/DraftMatecatUtils/manageCaretPositionNearEntity'
import {
  createICUDecorator,
  createIcuTokens,
} from './utils/DraftMatecatUtils/createICUDecorator'
import {UseHotKeysComponent} from '../../hooks/UseHotKeysComponent'
import {flushSync} from 'react-dom'
import {removeZeroWidthSpace} from './utils/DraftMatecatUtils/tagUtils'
import CommonUtils from '../../utils/commonUtils'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import textUtils from '../../utils/textUtils'

const SegmentSource = forwardRef((props, ref) => {
  const context = useContext(SegmentContext)

  // Bridges the imperative ref API back onto a stable object so that internal calls made
  // through it (e.g. render -> helpAiAssistant, onKeyUp -> helpAiAssistant) are visible to
  // jest.spyOn exactly like a class instance's `this.method()` performing a property lookup
  // at call time. Every method below is assigned onto it exactly ONCE (see methodsAssignedRef
  // below) rather than every render, so a spy installed on `ref.current.someMethod` survives
  // any number of subsequent re-renders (setState/forceUpdate) — matching how a real class
  // instance field, set once in the constructor, is never reassigned by React re-rendering.
  const instanceRef = useRef({})
  const methodsAssignedRef = useRef(false)

  // Tracks the latest props/context/state for the stable (assigned-once) closures below, so
  // they never see stale values despite being created only on the first render — exactly like
  // a class instance method reading `this.props`/`this.context`/`this.state` live.
  const liveRef = useRef({})

  // Refs replacing plain class-instance fields that are mutated outside the render/state cycle.
  const splitPointRef = useRef(
    props.segment.split_group ? props.segment.split_group.length - 1 : 0,
  )
  const delayAiAssistantRef = useRef()
  const firstIcuCheckRef = useRef(false)
  const wasTripleClickTriggeredRef = useRef(false)
  const editorRef = useRef(null)
  const sourceRef = useRef(null)

  // Computed ONCE, matching the constructor's `this.originalSource`/`this.icuEnabled`, which
  // are never recomputed even if props change later.
  const [originalSource] = useState(() => props.segment.segment)
  const [icuEnabled] = useState(() => props.segment.icu)

  const getSearchParamsRef = useRef(() => {
    const {
      inSearch,
      currentInSearch,
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
    } = liveRef.current.segment
    if (inSearch && searchParams.source) {
      return {
        active: inSearch,
        currentActive: currentInSearch,
        textToReplace: searchParams.source,
        params: searchParams,
        occurrences: occurrencesInSearch.occurrences,
        currentInSearchIndex,
        isTarget: false,
      }
    } else {
      return {
        active: false,
      }
    }
  })
  const getSearchParams = getSearchParamsRef.current

  const openConcordanceRef = useRef((e) => {
    e.preventDefault()
    var selection = window.getSelection()
    if (selection.type === 'Range') {
      // something is selected
      var str = selection.toString().trim()
      if (str.length) {
        // the trimmed string is not empty
        SegmentActions.openConcordance(liveRef.current.segment.sid, str, false)
      }
    }
  })
  const openConcordance = openConcordanceRef.current

  const removeDecoratorRef = useRef((decoratorName) => {
    if (!decoratorName) {
      // All decorators except tags
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
  const removeDecorator = removeDecoratorRef.current

  const disableDecoratorRef = useRef((editorState, decoratorName) => {
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === decoratorName,
    )
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    return EditorState.set(editorState, {decorator})
  })
  const disableDecorator = disableDecoratorRef.current

  const addSearchDecoratorRef = useRef(() => {
    let {tagRange} = liveRef.current
    let {searchParams, occurrencesInSearch, currentInSearchIndex} =
      liveRef.current.segment
    const textToSearch = searchParams.source ? searchParams.source : ''
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
  const addSearchDecorator = addSearchDecoratorRef.current

  const addGlossaryDecoratorRef = useRef(() => {
    let {glossary, sid} = liveRef.current.segment
    const newDecorator = DraftMatecatUtils.activateGlossary(
      glossary.filter(({isBlacklist}) => !isBlacklist),
      sid,
    )
    remove(
      decoratorsStructureRef.current,
      (decorator) =>
        decorator.name === DraftMatecatConstants.GLOSSARY_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })
  const addGlossaryDecorator = addGlossaryDecoratorRef.current

  const addQaCheckGlossaryDecoratorRef = useRef(() => {
    let {glossary, segment, sid} = liveRef.current.segment
    const missingGossaryItems = glossary.filter((item) => item.missingTerm)
    const newDecorator = DraftMatecatUtils.activateQaCheckGlossary(
      missingGossaryItems,
      segment,
      sid,
      SegmentActions.activateTab,
    )
    remove(
      decoratorsStructureRef.current,
      (decorator) =>
        decorator.name === DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })
  const addQaCheckGlossaryDecorator = addQaCheckGlossaryDecoratorRef.current

  const getUpdatedSegmentInfoRef = useRef(() => {
    const {sid, warnings, tagMismatch, opened, missingTagsInTarget, openSplit} =
      liveRef.current.contextSegment
    return {
      sid,
      warnings,
      tagMismatch,
      tagRange: liveRef.current.tagRange,
      segmentOpened: opened,
      missingTagsInTarget,
      currentSelection: liveRef.current.editorState.getSelection(),
      openSplit,
    }
  })
  const getUpdatedSegmentInfo = getUpdatedSegmentInfoRef.current

  const addLexiqaDecoratorRef = useRef(() => {
    let {lexiqa, sid, lxqDecodedSource} = liveRef.current.segment
    let ranges = LexiqaUtils.getRanges(
      cloneDeep(lexiqa.source),
      lxqDecodedSource,
      true,
    )
    const updatedLexiqaWarnings = updateOffsetBasedOnEditorState(
      liveRef.current.editorState,
      ranges,
    )
    if (updatedLexiqaWarnings.length > 0) {
      const newDecorator = DraftMatecatUtils.activateLexiqa(
        liveRef.current.editorState,
        updatedLexiqaWarnings,
        sid,
        true,
        getUpdatedSegmentInfo,
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
  })
  const addLexiqaDecorator = addLexiqaDecoratorRef.current

  const addIcuDecoratorRef = useRef(() => {
    const contentState = liveRef.current.editorState.getCurrentContent()
    const plainText = textUtils.removeWhitespacePlaceholders(
      contentState.getPlainText(),
    )
    const tokens = createIcuTokens(
      plainText,
      liveRef.current.editorState,
      config.source_rfc,
    )
    const newDecorator = createICUDecorator(tokens, false)
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === DraftMatecatConstants.ICU_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  })
  const addIcuDecorator = addIcuDecoratorRef.current

  const updateSourceInStoreRef = useRef(() => {
    if (liveRef.current.source !== '') {
      const {editorState, tagRange} = liveRef.current
      let contentState = editorState.getCurrentContent()
      let plainText = contentState.getPlainText()
      plainText = removeZeroWidthSpace(plainText)
      const {decodedSegment} = DraftMatecatUtils.decodeSegment(editorState)
      const lxqDecodedSource =
        DraftMatecatUtils.prepareTextForLexiqa(decodedSegment)
      SegmentActions.updateSource(
        liveRef.current.segment.sid,
        decodedSegment,
        plainText,
        tagRange,
        lxqDecodedSource,
      )
    }
  })
  const updateSourceInStore = updateSourceInStoreRef.current

  // Called three ways, exactly like the original: `checkDecorators({segment: prevSegment})`
  // from the componentDidUpdate-equivalent effect, and `checkDecorators()` with no argument
  // from both the mount effect and refreshTagMap's delayed setTimeout.
  const checkDecoratorsRef = useRef((prevProps) => {
    let changedDecorator = false
    const {inSearch, searchParams, currentInSearch, currentInSearchIndex} =
      liveRef.current.segment
    const prevActiveDecorators = liveRef.current.activeDecorators
    const activeDecorators = {...prevActiveDecorators}

    if (!inSearch) {
      //Glossary
      const {glossary} = liveRef.current.segment
      const prevGlossary = prevProps ? prevProps.segment.glossary : undefined

      //Qa Check Glossary
      const missingGlossaryItems =
        glossary && glossary.filter((item) => item.missingTerm)
      const prevMissingGlossaryItems =
        prevGlossary && prevGlossary.filter((item) => item.missingTerm)
      if (
        missingGlossaryItems &&
        missingGlossaryItems.length > 0 &&
        (isUndefined(prevMissingGlossaryItems) ||
          !fromJS(prevMissingGlossaryItems).equals(
            fromJS(missingGlossaryItems),
          ))
      ) {
        addQaCheckGlossaryDecorator()
        changedDecorator = true
        activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] = true
      } else if (
        prevMissingGlossaryItems &&
        prevMissingGlossaryItems.length > 0 &&
        (!missingGlossaryItems || missingGlossaryItems.length === 0)
      ) {
        changedDecorator = true
        removeDecorator(DraftMatecatConstants.QA_GLOSSARY_DECORATOR)
        activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] = false
      }

      if (
        glossary &&
        size(glossary) > 0 &&
        (isUndefined(prevGlossary) ||
          !fromJS(prevGlossary).equals(fromJS(glossary)) ||
          !prevActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = true
        changedDecorator = true
        addGlossaryDecorator()
      } else if (
        size(prevGlossary) > 0 &&
        (!glossary || size(glossary) === 0)
      ) {
        activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false
        changedDecorator = true
        removeDecorator(DraftMatecatConstants.GLOSSARY_DECORATOR)
      }
      //Lexiqa
      const {lexiqa} = liveRef.current.segment
      const prevLexiqa = prevProps ? prevProps.segment.lexiqa : undefined
      const currentLexiqaSource = lexiqa && lexiqa.source && size(lexiqa.source)
      const prevLexiqaSource =
        prevLexiqa && prevLexiqa.source && size(prevLexiqa.source)
      const lexiqaChanged =
        prevLexiqaSource &&
        currentLexiqaSource &&
        !fromJS(prevLexiqa.source).equals(fromJS(lexiqa.source))

      if (
        currentLexiqaSource &&
        (!prevLexiqaSource ||
          lexiqaChanged ||
          !prevActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
        changedDecorator = true
        addLexiqaDecorator()
      } else if (prevLexiqaSource && !currentLexiqaSource) {
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
      if (!firstIcuCheckRef.current && icuEnabled) {
        firstIcuCheckRef.current = true
        changedDecorator = true
        addIcuDecorator()
      }
    } else {
      //Search
      if (
        searchParams.source &&
        (!prevProps || // was not mounted
          !prevProps.segment.inSearch || //Before was not active
          (prevProps.segment.inSearch &&
            !fromJS(prevProps.segment.searchParams).equals(
              fromJS(searchParams),
            )) || //Before was active but some params change
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearch !== currentInSearch) || //Before was the current
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearchIndex !== currentInSearchIndex))
      ) {
        //There are more occurrences and the current change
        // Cleanup all decorators
        removeDecorator()
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false
        activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] = false
        addSearchDecorator()
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
        changedDecorator = true
      }
    }

    if (changedDecorator) {
      const decorator = new CompositeDecorator(decoratorsStructureRef.current)
      setEditorState(EditorState.set(liveRef.current.editorState, {decorator}))
      setActiveDecorators(activeDecorators)
    }
  })
  const checkDecorators = checkDecoratorsRef.current

  const updateSplitNumberNewRef = useRef((step) => {
    if (liveRef.current.segment.splitted) return
    splitPointRef.current += step
  })
  const updateSplitNumberNew = updateSplitNumberNewRef.current

  const insertTagAtSelectionRef = useRef((tagName) => {
    const customTag = DraftMatecatUtils.structFromName(tagName)
    // If tag creation has failed, return
    if (!customTag) return
    // remove lexiqa to avoid insertion error
    removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
    removeDecorator(DraftMatecatConstants.SPLIT_DECORATOR)
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    let newEditorState = EditorState.set(liveRef.current.editorState, {
      decorator,
    })
    newEditorState = DraftMatecatUtils.insertEntityAtSelection(
      newEditorState,
      customTag,
    )
    setEditorState(newEditorState)
  })
  const insertTagAtSelection = insertTagAtSelectionRef.current

  const addSplitTagRef = useRef(() => {
    // Check chars are selected
    const selection = window.getSelection()
    if (selection.anchorNode) {
      const {startOffset = 0, endOffset = 0} = selection?.getRangeAt(0)
      if (endOffset - startOffset > 0) {
        selection?.removeAllRanges()
        return
      }
    }

    insertTagAtSelection('splitPoint')
    updateSplitNumberNew(1)
  })
  const addSplitTag = addSplitTagRef.current

  const splitSegmentNewRef = useRef((split) => {
    let {decodedSegment: text} = DraftMatecatUtils.decodeSegment(
      liveRef.current.editorState,
    )
    // Prepare text for backend
    text = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    SegmentActions.splitSegment(
      liveRef.current.segment.original_sid,
      text,
      split,
    )
  })
  const splitSegmentNew = splitSegmentNewRef.current

  const onBlurEventRef = useRef(() => {
    setTimeout(() => {
      SegmentActions.highlightTags()
      SegmentActions.focusTags([])
    })

    setIsShowingOptionsToolbar(false)
  })
  const onBlurEvent = onBlurEventRef.current

  const onEntityClickRef = useRef((start, end, entityName) => {
    const segment = liveRef.current.contextSegment
    try {
      // Get latest selection
      let newSelection = editorRef.current._latestEditorState.getSelection()

      const currentBlockText = editorRef.current._latestEditorState
        .getCurrentContent()
        .getBlockForKey(newSelection.getFocusKey())
        .getText()
      const zwsp = String.fromCharCode(parseInt('200B', 16))
      const selectedTextAfter = currentBlockText.slice(end, end + 1)
      const selectedTextBefore = currentBlockText.slice(start - 1, start)
      const addZwspExtraStepBefore = zwsp === selectedTextBefore ? 1 : 0
      const addZwspExtraStepAfter = zwsp === selectedTextAfter ? 1 : 0

      // force selection on entity
      newSelection = newSelection.merge({
        anchorOffset: start - addZwspExtraStepBefore,
        focusOffset: end + addZwspExtraStepAfter,
      })
      let newEditorState = EditorState.forceSelection(
        liveRef.current.editorState,
        newSelection,
      )
      const contentState = newEditorState.getCurrentContent()
      // remove split tag
      if (segment.openSplit && entityName === tagSignatures.splitPoint.type) {
        const contentStateWithoutSplitPoint = Modifier.removeRange(
          contentState,
          newSelection,
          'forward',
        )
        // set selection before entity
        newSelection = newSelection.merge({
          focusOffset: start,
        })
        newEditorState = EditorState.forceSelection(
          newEditorState,
          newSelection,
        )
        updateSplitNumberNew(-1)
        newEditorState = EditorState.set(newEditorState, {
          currentContent: contentStateWithoutSplitPoint,
        })
      }
      // update editorState
      setEditorState(newEditorState)
    } catch (e) {
      console.log(e)
    }
  })
  const onEntityClick = onEntityClickRef.current

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
  const copyFragment = copyFragmentRef.current

  const dragFragmentRef = useRef((e) => {
    const {editorState} = liveRef.current
    let fragment = getFragmentFromSelection(editorState)
    if (fragment) {
      const entitiesMap = DraftMatecatUtils.getEntitiesInFragment(
        fragment,
        editorState,
      )
      fragment = JSON.stringify({
        orderedMap: fragment,
        entitiesMap: entitiesMap,
      })
      e.dataTransfer.clearData()
      e.dataTransfer.setData('text/plain', fragment)
      e.dataTransfer.setData('text/html', fragment)
    }
  })
  const dragFragment = dragFragmentRef.current

  const allowHTMLRef = useRef((string) => ({__html: string}))
  const allowHTML = allowHTMLRef.current

  const onChangeRef = useRef((editorState) => {
    const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
    if (!entityKey) {
      setTimeout(() => {
        SegmentActions.highlightTags()
      })
    }
    setEditorState(editorState)
  })
  const onChange = onChangeRef.current

  const preventEditRef = useRef(() => 'handled')
  const preventEdit = preventEditRef.current

  const getSelectedWordsRef = useRef(() =>
    DraftMatecatUtils.getSelectedTextWithoutEntities(
      liveRef.current.editorState,
    ).reduce((acc, {value}) => `${acc}${value}`, ''),
  )
  const getSelectedWords = getSelectedWordsRef.current

  const isValidPhraseToAiAssistantRef = useRef(
    ({phrase, sourceLanguageCode = config.source_code}) => {
      if (!phrase) return false

      const phraseValidator = {
        'zh-CN': (value) => value.split('').length <= 6,
        'zh-TW': (value) => value.split('').length <= 6,
        'zh-HK': (value) => value.split('').length <= 6,
        'zh-MO': (value) => value.split('').length <= 6,
        'ja-JP': (value) => value.split('').length <= 10,
        default: (value) => value.split(' ').length <= 3,
      }

      const handler = {
        get: function (target, prop) {
          const counter = target[prop] ? target[prop] : target.default
          return counter(phrase)
        },
      }

      const proxy = new Proxy(phraseValidator, handler)
      return proxy[sourceLanguageCode]
    },
  )
  const isValidPhraseToAiAssistant = isValidPhraseToAiAssistantRef.current

  const helpAiAssistantRef = useRef(() => {
    if (delayAiAssistantRef.current) clearTimeout(delayAiAssistantRef.current)

    const isOpenAiEnabled =
      Boolean(config.isOpenAiEnabled) &&
      liveRef.current.contextUserInfo?.metadata.ai_assistant === 1

    if (isOpenAiEnabled) {
      delayAiAssistantRef.current = setTimeout(() => {
        const segment = liveRef.current.contextSegment
        const value = instanceRef.current.getSelectedWords()

        const isValid = instanceRef.current.isValidPhraseToAiAssistant({
          phrase: value,
        })

        if (isValid) {
          SegmentActions.helpAiAssistant({
            sid: segment.sid,
            value,
          })
        }
      }, 200)
    }
  })
  const helpAiAssistant = helpAiAssistantRef.current

  const endSplitModeRef = useRef(() => {
    const {editorStateBeforeSplit, contextSegment: segment} = liveRef.current
    splitPointRef.current = segment.split_group
      ? segment.split_group.length - 1
      : 0
    // TODO: why so much calls endSplitMode??
    if (segment.openSplit) {
      setEditorState(editorStateBeforeSplit)
    }
  })
  const endSplitMode = endSplitModeRef.current

  // Restore tagged source in draftJS after GuessTag
  const setTaggedSourceRef = useRef((sid) => {
    const segment = liveRef.current.segment
    if (sid === segment.sid) {
      // Escape html
      const translation = segment.segment

      // If GuessTag enabled, clean string from tag
      const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled()
        ? DraftMatecatUtils.removeTagsFromText(translation)
        : translation
      // TODO: get taggedSource from store
      const contentEncoded = DraftMatecatUtils.encodeContent(
        liveRef.current.editorState,
        cleanSource,
      )
      const {editorState: newEditorState, tagRange: newTagRange} =
        contentEncoded
      setEditorState(newEditorState)
      setTagRange(newTagRange)
      setTimeout(() => updateSourceInStore())
    }
  })
  const setTaggedSource = setTaggedSourceRef.current

  const refreshTagMapRef = useRef(() => {
    const segment = liveRef.current.segment
    const translation = segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(segment)
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    // New EditorState with translation
    const contentEncoded = DraftMatecatUtils.encodeContent(
      liveRef.current.editorState,
      cleanSource,
    )
    const {editorState: newEditorState, tagRange: newTagRange} = contentEncoded

    flushSync(() => {
      setEditorState(newEditorState)
      setTagRange(newTagRange)
    })

    updateSourceInStore()

    setTimeout(() => checkDecorators(), 100)
  })
  const refreshTagMap = refreshTagMapRef.current

  // Seeded once with the same logic as the constructor, mutated in place by the add*/remove
  // decorator helpers above via lodash `remove()`/`.push()` — a working buffer, not reactive
  // state, matching class-instance-field semantics precisely.
  const decoratorsStructureRef = useRef([
    {
      name: 'tags',
      strategy: getEntityStrategy('IMMUTABLE'),
      component: TagEntity,
      props: {
        onClick: onEntityClick,
        getUpdatedSegmentInfo: getUpdatedSegmentInfo,
        isTarget: false,
        getSearchParams: getSearchParams,
        isRTL: config.isSourceRTL,
        sid: props.segment.sid,
      },
    },
  ])

  // Computed ONCE (lazily cached via a ref), matching the constructor building a single
  // CompositeDecorator/EditorState and deriving `source`/`editorState`/`tagRange` from it.
  const initialContentRef = useRef(null)
  if (initialContentRef.current === null) {
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    const plainEditorState = EditorState.createEmpty(decorator)
    const translation = props.segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(
      props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    // New EditorState with translation
    const contentEncoded = DraftMatecatUtils.encodeContent(
      plainEditorState,
      cleanSource,
    )
    initialContentRef.current = {
      source: cleanSource,
      editorState: contentEncoded.editorState,
      tagRange: contentEncoded.tagRange,
    }
  }

  const [source, setSource] = useState(() => initialContentRef.current.source)
  const [editorState, setEditorState] = useState(
    () => initialContentRef.current.editorState,
  )
  const [editAreaClasses, setEditAreaClasses] = useState(['targetarea'])
  const [tagRange, setTagRange] = useState(
    () => initialContentRef.current.tagRange,
  )
  const [unlockedForCopy, setUnlockedForCopy] = useState(false)
  const [editorStateBeforeSplit, setEditorStateBeforeSplit] = useState(
    () => initialContentRef.current.editorState,
  )
  const [activeDecorators, setActiveDecorators] = useState(() => ({
    [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
    [DraftMatecatConstants.GLOSSARY_DECORATOR]: false,
    [DraftMatecatConstants.QA_GLOSSARY_DECORATOR]: false,
    [DraftMatecatConstants.SEARCH_DECORATOR]: false,
    [DraftMatecatConstants.ICU_DECORATOR]: icuEnabled,
  }))
  const [isShowingOptionsToolbar, setIsShowingOptionsToolbar] =
    useState(false)

  liveRef.current.segment = props.segment
  liveRef.current.contextSegment = context.segment
  liveRef.current.contextUserInfo = context.userInfo
  liveRef.current.source = source
  liveRef.current.editorState = editorState
  liveRef.current.tagRange = tagRange
  liveRef.current.editorStateBeforeSplit = editorStateBeforeSplit
  liveRef.current.activeDecorators = activeDecorators

  const isFirstRenderRef = useRef(true)
  const prevSegmentRef = useRef(props.segment)
  const prevEditorStateRef = useRef(editorState)

  const [, bumpForceRender] = useReducer((x) => x + 1, 0)

  useEffect(() => {
    SegmentStore.addListener(
      SegmentConstants.CLOSE_SPLIT_SEGMENT,
      endSplitMode,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_TAGGED,
      setTaggedSource,
    )
    SegmentStore.addListener(
      SegmentConstants.REFRESH_TAG_MAP,
      refreshTagMap,
    )
    setTimeout(() => {
      checkDecorators()
      updateSourceInStore()
    })

    new CommonUtils.DetectTripleClick(
      sourceRef.current,
      () => (wasTripleClickTriggeredRef.current = true),
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.CLOSE_SPLIT_SEGMENT,
        endSplitMode,
      )
      SegmentStore.removeListener(
        SegmentConstants.REFRESH_TAG_MAP,
        refreshTagMap,
      )
      // NOTE: SET_SEGMENT_TAGGED/setTaggedSource is intentionally NOT removed here — this
      // mirrors a pre-existing bug in the original componentWillUnmount, preserved verbatim.
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // componentDidUpdate-equivalent: runs after every render (including forceUpdate-only
  // renders) but not on the initial mount. No dependency array — matches componentDidUpdate's
  // unconditional per-update re-sync.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false
      prevSegmentRef.current = props.segment
      prevEditorStateRef.current = editorState
      return
    }

    const prevSegment = prevSegmentRef.current

    checkDecorators({segment: prevSegment})

    // Check if splitMode
    if (!prevSegment.openSplit && props.segment.openSplit) {
      // if segment splitted, rebuild its original content
      if (props.segment.splitted) {
        let segmentsSplit = props.segment.split_group
        let sourceHtml = ''
        // join splitted segment content
        segmentsSplit.forEach((sid, index) => {
          let segment = SegmentStore.getSegmentByIdToJS(sid)
          if (sid === props.segment.sid) {
            // if splitted wrap inside highlight span
            sourceHtml += segment.segment
          } else {
            // if not splitted, add only content
            sourceHtml += segment.segment
          }
          // add splitPoint after every segment content except for last one
          if (index !== segmentsSplit.length - 1) {
            sourceHtml += '##$_SPLIT$##'
          }
        })
        // create a new editorState
        const decorator = new CompositeDecorator(decoratorsStructureRef.current)
        const plainEditorState = EditorState.createEmpty(decorator)
        // add the content
        const contentEncoded = DraftMatecatUtils.encodeContent(
          plainEditorState,
          sourceHtml,
        )
        const {editorState: editorStateSplitGroup} = contentEncoded
        // update current editorState
        setEditorState(editorStateSplitGroup)
      }
    }

    if (prevEditorStateRef.current !== editorState) {
      const entitiesSelected = getEntitiesSelected(editorState)
      SegmentActions.focusTags(entitiesSelected)
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
      setEditorState(newEditorState)
    }

    wasTripleClickTriggeredRef.current = false

    prevSegmentRef.current = props.segment
    prevEditorStateRef.current = editorState
  })

  instanceRef.current.state = {
    source,
    editorState,
    editAreaClasses,
    tagRange,
    unlockedForCopy,
    editorStateBeforeSplit,
    activeDecorators,
    isShowingOptionsToolbar,
  }

  if (!methodsAssignedRef.current) {
    methodsAssignedRef.current = true

    instanceRef.current.setState = (partial) => {
      if ('source' in partial) setSource(partial.source)
      if ('editorState' in partial) setEditorState(partial.editorState)
      if ('editAreaClasses' in partial) setEditAreaClasses(partial.editAreaClasses)
      if ('tagRange' in partial) setTagRange(partial.tagRange)
      if ('unlockedForCopy' in partial) setUnlockedForCopy(partial.unlockedForCopy)
      if ('editorStateBeforeSplit' in partial)
        setEditorStateBeforeSplit(partial.editorStateBeforeSplit)
      if ('activeDecorators' in partial)
        setActiveDecorators(partial.activeDecorators)
      if ('isShowingOptionsToolbar' in partial)
        setIsShowingOptionsToolbar(partial.isShowingOptionsToolbar)
    }

    instanceRef.current.forceUpdate = () => bumpForceRender()

    instanceRef.current.helpAiAssistant = helpAiAssistant
    instanceRef.current.setTaggedSource = setTaggedSource
    instanceRef.current.openConcordance = openConcordance
    instanceRef.current.onEntityClick = onEntityClick
    instanceRef.current.addSplitTag = addSplitTag
    instanceRef.current.removeDecorator = removeDecorator
    instanceRef.current.getSearchParams = getSearchParams
    instanceRef.current.endSplitMode = endSplitMode
    instanceRef.current.dragFragment = dragFragment
    instanceRef.current.copyFragment = copyFragment
    instanceRef.current.updateSplitNumberNew = updateSplitNumberNew
    instanceRef.current.updateSourceInStore = updateSourceInStore
    instanceRef.current.refreshTagMap = refreshTagMap
    instanceRef.current.preventEdit = preventEdit
    instanceRef.current.onChange = onChange
    instanceRef.current.onBlurEvent = onBlurEvent
    instanceRef.current.insertTagAtSelection = insertTagAtSelection
    instanceRef.current.getUpdatedSegmentInfo = getUpdatedSegmentInfo
    instanceRef.current.getSelectedWords = getSelectedWords
    instanceRef.current.disableDecorator = disableDecorator
    instanceRef.current.allowHTML = allowHTML
    instanceRef.current.checkDecorators = checkDecorators
    instanceRef.current.isValidPhraseToAiAssistant = isValidPhraseToAiAssistant
    instanceRef.current.splitSegmentNew = splitSegmentNew

    Object.defineProperties(instanceRef.current, {
      splitPoint: {get: () => splitPointRef.current, configurable: true},
      decoratorsStructure: {
        get: () => decoratorsStructureRef.current,
        configurable: true,
      },
      firstIcuCheck: {get: () => firstIcuCheckRef.current, configurable: true},
      editor: {
        get: () => editorRef.current,
        set: (v) => {
          editorRef.current = v
        },
        configurable: true,
      },
    })

    instanceRef.current.wasTripleClickTriggered = wasTripleClickTriggeredRef
  }

  useImperativeHandle(ref, () => instanceRef.current)

  const {segment} = context
  // Set correct handlers
  const handlers = !segment.openSplit
    ? {
        onCut: (e) => {
          e.preventDefault()
        },
        onCopy: copyFragment,
        onBlur: onBlurEvent,
        onDragStart: dragFragment,
        onMouseUp: () => {
          setTimeout(() => {
            setIsShowingOptionsToolbar(
              !editorRef.current._latestEditorState.getSelection().isCollapsed(),
            )

            instanceRef.current.helpAiAssistant()
          })
        },
        onKeyUp: (event) => {
          if (
            event.key === 'ArrowLeft' ||
            event.key === 'ArrowRight' ||
            event.key === 'ArrowUp' ||
            event.key === 'ArrowDown'
          ) {
            setIsShowingOptionsToolbar(
              !editorRef.current._latestEditorState.getSelection().isCollapsed(),
            )

            instanceRef.current.helpAiAssistant()
          }
        },
      }
    : {
        onClick: () => addSplitTag(),
        onBlur: onBlurEvent,
      }

  const isEnabledAiAssistantButton = instanceRef.current.isValidPhraseToAiAssistant(
    {phrase: instanceRef.current.getSelectedWords()},
  )

  const optionsToolbar = isShowingOptionsToolbar && (
    <div className="optionsToolbar">
      {Boolean(config.isOpenAiEnabled) &&
        context.userInfo?.metadata.ai_assistant === 0 && (
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
                  sid: segment.sid,
                  value: instanceRef.current.getSelectedWords(),
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
            sid: segment.sid,
            [TERM_FORM_FIELDS.ORIGINAL_TERM]:
              instanceRef.current.getSelectedWords(),
          })
        }}
      >
        <Education />
      </Button>
    </div>
  )

  // Standard editor
  const editorHtml = (
    <div
      ref={sourceRef}
      className={`source item`}
      tabIndex={0}
      id={'segment-' + segment.sid + '-source'}
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
      <Editor
        editorState={editorState}
        onChange={onChange}
        onCut={preventEdit}
        ref={editorRef}
        readOnly={false}
        handleBeforeInput={preventEdit}
        handlePastedText={preventEdit}
        handleDrop={preventEdit}
        handleReturn={preventEdit}
        handleKeyCommand={preventEdit}
        handleDroppedFiles={preventEdit}
        handlePastedFiles={preventEdit}
        textAlignment={config.isSourceRTL ? 'right' : 'left'}
        textDirectionality={config.isSourceRTL ? 'RTL' : 'LTR'}
      />
      {optionsToolbar}
    </div>
  )

  // Wrap editor in splitContainer
  return segment.openSplit ? (
    <div className="splitContainer">
      {editorHtml}
      <div className="splitBar">
        {!!splitPointRef.current && (
          <div className="splitNum">
            Split in <span className="num">{splitPointRef.current}</span> segment
            <span className="plural" />
          </div>
        )}
        <div className="buttons">
          <Button
            mode={BUTTON_MODE.OUTLINE}
            onClick={() => SegmentActions.closeSplitSegment()}
          >
            Cancel
          </Button>
          <Button
            type={BUTTON_TYPE.PRIMARY}
            disabled={!splitPointRef.current}
            onClick={() => splitSegmentNew()}
          >
            {' '}
            Confirm{' '}
          </Button>
        </div>
      </div>
    </div>
  ) : (
    editorHtml
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

SegmentSource.displayName = 'SegmentSource'

export default SegmentSource
