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

// Pure — reads only its arguments, so it's a plain module-level function
// rather than a component method, and testable directly with no rendering.
export const isValidPhraseToAiAssistant = ({
  phrase,
  sourceLanguageCode = config.source_code,
}) => {
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
}

// Pure — reads only its argument, testable directly with no rendering.
export const getSearchParams = (segment) => {
  const {
    inSearch,
    currentInSearch,
    searchParams,
    occurrencesInSearch,
    currentInSearchIndex,
  } = segment
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
}

const SegmentSource = forwardRef(({segment}, ref) => {
  const context = useContext(SegmentContext)
  const {segment: contextSegment, userInfo: contextUserInfo} = context

  // Holds this render's methods, reassigned in full every render (not a hook) so the three
  // permanently-stable store listeners below (endSplitMode, setTaggedSource, refreshTagMap —
  // registered once with SegmentStore, which matches handlers by reference) can call the
  // current render's other methods instead of the ones that existed when they were created.
  const methodsRef = useRef({})
  const stableMethodsAssignedRef = useRef(false)

  // Same idea as methodsRef, but for the handful of segment/context/state values those three
  // listeners read directly rather than through another method.
  const latestRef = useRef({})

  // Refs replacing plain class-instance fields that are mutated outside the render/state cycle.
  const splitPointRef = useRef(
    segment.split_group ? segment.split_group.length - 1 : 0,
  )
  const delayAiAssistantRef = useRef()
  const firstIcuCheckRef = useRef(false)
  const wasTripleClickTriggeredRef = useRef(false)
  const editorRef = useRef(null)
  const sourceRef = useRef(null)

  // Computed ONCE, matching the constructor's `this.originalSource`/`this.icuEnabled`, which
  // are never recomputed even if props change later.
  const [originalSource] = useState(() => segment.segment)
  const [icuEnabled] = useState(() => segment.icu)

  const openConcordance = (e) => {
    e.preventDefault()
    var selection = window.getSelection()
    if (selection.type === 'Range') {
      // something is selected
      var str = selection.toString().trim()
      if (str.length) {
        // the trimmed string is not empty
        SegmentActions.openConcordance(segment.sid, str, false)
      }
    }
  }

  const removeDecorator = (decoratorName) => {
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
  }

  const disableDecorator = (editorState, decoratorName) => {
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === decoratorName,
    )
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    return EditorState.set(editorState, {decorator})
  }

  const addSearchDecorator = () => {
    let {searchParams, occurrencesInSearch, currentInSearchIndex} = segment
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
  }

  const addGlossaryDecorator = () => {
    let {glossary, sid} = segment
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
  }

  const addQaCheckGlossaryDecorator = () => {
    let {glossary, segment: segmentText, sid} = segment
    const missingGossaryItems = glossary.filter((item) => item.missingTerm)
    const newDecorator = DraftMatecatUtils.activateQaCheckGlossary(
      missingGossaryItems,
      segmentText,
      sid,
      SegmentActions.activateTab,
    )
    remove(
      decoratorsStructureRef.current,
      (decorator) =>
        decorator.name === DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  }

  const getUpdatedSegmentInfo = () => {
    const {sid, warnings, tagMismatch, opened, missingTagsInTarget, openSplit} =
      contextSegment
    return {
      sid,
      warnings,
      tagMismatch,
      tagRange,
      segmentOpened: opened,
      missingTagsInTarget,
      currentSelection: editorState.getSelection(),
      openSplit,
    }
  }

  const addLexiqaDecorator = () => {
    let {lexiqa, sid, lxqDecodedSource} = segment
    let ranges = LexiqaUtils.getRanges(
      cloneDeep(lexiqa.source),
      lxqDecodedSource,
      true,
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
      methodsRef.current.removeDecorator(
        DraftMatecatConstants.LEXIQA_DECORATOR,
      )
    }
  }

  const addIcuDecorator = () => {
    const contentState = editorState.getCurrentContent()
    const plainText = textUtils.removeWhitespacePlaceholders(
      contentState.getPlainText(),
    )
    const tokens = createIcuTokens(plainText, editorState, config.source_code)
    const newDecorator = createICUDecorator(tokens, false)
    remove(
      decoratorsStructureRef.current,
      (decorator) => decorator.name === DraftMatecatConstants.ICU_DECORATOR,
    )
    decoratorsStructureRef.current.push(newDecorator)
  }

  const updateSourceInStore = () => {
    if (source !== '') {
      let contentState = editorState.getCurrentContent()
      let plainText = contentState.getPlainText()
      plainText = removeZeroWidthSpace(plainText)
      const {decodedSegment} = DraftMatecatUtils.decodeSegment(editorState)
      const lxqDecodedSource =
        DraftMatecatUtils.prepareTextForLexiqa(decodedSegment)
      SegmentActions.updateSource(
        segment.sid,
        decodedSegment,
        plainText,
        tagRange,
        lxqDecodedSource,
      )
    }
  }

  // Called three ways, exactly like the original: `checkDecorators({segment: prevSegment})`
  // from the componentDidUpdate-equivalent effect, and `checkDecorators()` with no argument
  // from both the mount effect and refreshTagMap's delayed setTimeout.
  const checkDecorators = (prevProps) => {
    let changedDecorator = false
    const {inSearch, searchParams, currentInSearch, currentInSearchIndex} =
      segment
    const prevActiveDecorators = activeDecorators
    const nextActiveDecorators = {...prevActiveDecorators}

    if (!inSearch) {
      //Glossary
      const {glossary} = segment
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
        methodsRef.current.addQaCheckGlossaryDecorator()
        changedDecorator = true
        nextActiveDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] =
          true
      } else if (
        prevMissingGlossaryItems &&
        prevMissingGlossaryItems.length > 0 &&
        (!missingGlossaryItems || missingGlossaryItems.length === 0)
      ) {
        changedDecorator = true
        methodsRef.current.removeDecorator(
          DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
        )
        nextActiveDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] =
          false
      }

      if (
        glossary &&
        size(glossary) > 0 &&
        (isUndefined(prevGlossary) ||
          !fromJS(prevGlossary).equals(fromJS(glossary)) ||
          !prevActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR])
      ) {
        nextActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = true
        changedDecorator = true
        methodsRef.current.addGlossaryDecorator()
      } else if (
        size(prevGlossary) > 0 &&
        (!glossary || size(glossary) === 0)
      ) {
        nextActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false
        changedDecorator = true
        methodsRef.current.removeDecorator(
          DraftMatecatConstants.GLOSSARY_DECORATOR,
        )
      }
      //Lexiqa
      const {lexiqa} = segment
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
        nextActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
        changedDecorator = true
        methodsRef.current.addLexiqaDecorator()
      } else if (prevLexiqaSource && !currentLexiqaSource) {
        nextActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        changedDecorator = true
        methodsRef.current.removeDecorator(
          DraftMatecatConstants.LEXIQA_DECORATOR,
        )
      }

      // Search
      if (prevProps && prevProps.segment.inSearch) {
        nextActiveDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
        changedDecorator = true
        methodsRef.current.removeDecorator(
          DraftMatecatConstants.SEARCH_DECORATOR,
        )
      }
      if (!firstIcuCheckRef.current && icuEnabled) {
        firstIcuCheckRef.current = true
        changedDecorator = true
        methodsRef.current.addIcuDecorator()
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
        methodsRef.current.removeDecorator()
        nextActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        nextActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false
        nextActiveDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] =
          false
        methodsRef.current.addSearchDecorator()
        nextActiveDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
        changedDecorator = true
      }
    }

    if (changedDecorator) {
      const decorator = new CompositeDecorator(decoratorsStructureRef.current)
      setEditorState(EditorState.set(editorState, {decorator}))
      setActiveDecorators(nextActiveDecorators)
    }
  }

  const updateSplitNumberNew = (step) => {
    if (segment.splitted) return
    splitPointRef.current += step
  }

  const insertTagAtSelection = (tagName) => {
    const customTag = DraftMatecatUtils.structFromName(tagName)
    // If tag creation has failed, return
    if (!customTag) return
    // remove lexiqa to avoid insertion error
    methodsRef.current.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
    methodsRef.current.removeDecorator(DraftMatecatConstants.SPLIT_DECORATOR)
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    let newEditorState = EditorState.set(editorState, {
      decorator,
    })
    newEditorState = DraftMatecatUtils.insertEntityAtSelection(
      newEditorState,
      customTag,
    )
    setEditorState(newEditorState)
  }

  const addSplitTag = () => {
    // Check chars are selected
    const selection = window.getSelection()
    if (selection.anchorNode) {
      const {startOffset = 0, endOffset = 0} = selection?.getRangeAt(0)
      if (endOffset - startOffset > 0) {
        selection?.removeAllRanges()
        return
      }
    }

    methodsRef.current.insertTagAtSelection('splitPoint')
    methodsRef.current.updateSplitNumberNew(1)
  }

  const splitSegmentNew = (split) => {
    let {decodedSegment: text} = DraftMatecatUtils.decodeSegment(editorState)
    // Prepare text for backend
    text = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    SegmentActions.splitSegment(segment.original_sid, text, split)
  }

  const onBlurEvent = () => {
    setTimeout(() => {
      SegmentActions.highlightTags()
      SegmentActions.focusTags([])
    })

    setIsShowingOptionsToolbar(false)
  }

  const onEntityClick = (start, end, entityName) => {
    const segment = contextSegment
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
        editorState,
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
        methodsRef.current.updateSplitNumberNew(-1)
        newEditorState = EditorState.set(newEditorState, {
          currentContent: contentStateWithoutSplitPoint,
        })
      }
      // update editorState
      setEditorState(newEditorState)
    } catch (e) {
      console.log(e)
    }
  }

  const copyFragment = (e) => {
    const internalClipboard = editorRef.current.getClipboard()
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
  }

  const dragFragment = (e) => {
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
  }

  const allowHTML = (string) => ({__html: string})

  const onChange = (editorState) => {
    const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
    if (!entityKey) {
      setTimeout(() => {
        SegmentActions.highlightTags()
      })
    }
    setEditorState(editorState)
  }

  const preventEdit = () => 'handled'

  // Assigned once (like the three store listeners below), not recreated
  // every render: tests spy on these via the exposed ref
  // (jest.spyOn(ref.current, 'getSelectedWords')), and a spy installed on a
  // freshly-recreated-every-render function would be silently discarded the
  // next time this component re-renders. Reads latestRef instead of closing
  // over editorState/contextUserInfo directly for that same reason.
  const getSelectedWordsRef = useRef(() =>
    DraftMatecatUtils.getSelectedTextWithoutEntities(
      latestRef.current.editorState,
    ).reduce((acc, {value}) => `${acc}${value}`, ''),
  )
  const getSelectedWords = getSelectedWordsRef.current

  const helpAiAssistantRef = useRef(() => {
    if (delayAiAssistantRef.current) clearTimeout(delayAiAssistantRef.current)

    const isOpenAiEnabled =
      Boolean(config.isOpenAiEnabled) &&
      latestRef.current.contextUserInfo?.metadata.ai_assistant === 1

    if (isOpenAiEnabled) {
      // Reads via latestRef, not the segment/getSelectedWords/
      // isValidPhraseToAiAssistant closed over above: this fires 200ms
      // later, potentially after other renders, and must see fresh values.
      delayAiAssistantRef.current = setTimeout(() => {
        const segment = latestRef.current.contextSegment
        const value = methodsRef.current.getSelectedWords()

        const isValid = isValidPhraseToAiAssistant({
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
    const {editorStateBeforeSplit, contextSegment: segment} = latestRef.current
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
    const segment = latestRef.current.segment
    if (sid === segment.sid) {
      // Escape html
      const translation = segment.segment

      // If GuessTag enabled, clean string from tag
      const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled()
        ? DraftMatecatUtils.removeTagsFromText(translation)
        : translation
      // TODO: get taggedSource from store
      const contentEncoded = DraftMatecatUtils.encodeContent(
        latestRef.current.editorState,
        cleanSource,
      )
      const {editorState: newEditorState, tagRange: newTagRange} =
        contentEncoded
      setEditorState(newEditorState)
      setTagRange(newTagRange)
      setTimeout(() => methodsRef.current.updateSourceInStore())
    }
  })
  const setTaggedSource = setTaggedSourceRef.current

  const refreshTagMapRef = useRef(() => {
    const segment = latestRef.current.segment
    const translation = segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(segment)
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    // New EditorState with translation
    const contentEncoded = DraftMatecatUtils.encodeContent(
      latestRef.current.editorState,
      cleanSource,
    )
    const {editorState: newEditorState, tagRange: newTagRange} = contentEncoded

    flushSync(() => {
      setEditorState(newEditorState)
      setTagRange(newTagRange)
    })

    methodsRef.current.updateSourceInStore()

    setTimeout(() => methodsRef.current.checkDecorators(), 100)
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
        getSearchParams: () => getSearchParams(segment),
        isRTL: config.isSourceRTL,
        sid: segment.sid,
      },
    },
  ])

  // Computed ONCE (lazily cached via a ref), matching the constructor building a single
  // CompositeDecorator/EditorState and deriving `source`/`editorState`/`tagRange` from it.
  const initialContentRef = useRef(null)
  if (initialContentRef.current === null) {
    const decorator = new CompositeDecorator(decoratorsStructureRef.current)
    const plainEditorState = EditorState.createEmpty(decorator)
    const translation = segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(segment)
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
  // Dead state, preserved verbatim from the class component: `this.state.editAreaClasses`
  // was never read anywhere, including in the original render().
  // eslint-disable-next-line no-unused-vars
  const [editAreaClasses, setEditAreaClasses] = useState(['targetarea'])
  const [tagRange, setTagRange] = useState(
    () => initialContentRef.current.tagRange,
  )
  // Dead state, preserved verbatim from the class component: `this.state.unlockedForCopy`
  // was never read anywhere, including in the original render().
  // eslint-disable-next-line no-unused-vars
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
  const [isShowingOptionsToolbar, setIsShowingOptionsToolbar] = useState(false)

  // Only what the three permanently-stable listeners (and helpAiAssistant's
  // delayed setTimeout) read, refreshed every render.
  latestRef.current = {
    segment,
    contextSegment,
    contextUserInfo,
    editorState,
    editorStateBeforeSplit,
  }

  const isFirstRenderRef = useRef(true)
  const prevSegmentRef = useRef(segment)
  const prevEditorStateRef = useRef(editorState)

  const [, bumpForceRender] = useReducer((x) => x + 1, 0)

  useEffect(() => {
    SegmentStore.addListener(SegmentConstants.CLOSE_SPLIT_SEGMENT, endSplitMode)
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_TAGGED,
      setTaggedSource,
    )
    SegmentStore.addListener(SegmentConstants.REFRESH_TAG_MAP, refreshTagMap)
    setTimeout(() => {
      methodsRef.current.checkDecorators()
      methodsRef.current.updateSourceInStore()
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
      prevSegmentRef.current = segment
      prevEditorStateRef.current = editorState
      return
    }

    const prevSegment = prevSegmentRef.current

    methodsRef.current.checkDecorators({segment: prevSegment})

    // Check if splitMode
    if (!prevSegment.openSplit && segment.openSplit) {
      // if segment splitted, rebuild its original content
      if (segment.splitted) {
        let segmentsSplit = segment.split_group
        let sourceHtml = ''
        // join splitted segment content
        segmentsSplit.forEach((sid, index) => {
          let segment = SegmentStore.getSegmentByIdToJS(sid)
          if (sid === segment.sid) {
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

    prevSegmentRef.current = segment
    prevEditorStateRef.current = editorState
  })

  // Lets the three permanently-stable listeners below (registered once with
  // SegmentStore, which matches handlers by reference) call this render's
  // methods without going stale. Reassigned in full every render — unlike
  // the old instanceRef, nothing needs its identity to stay stable.
  // Single stable object, mutated in place every render (never recreated),
  // used BOTH for internal cross-method calls (the three permanently-stable
  // store listeners reach other current-render methods through it) AND as
  // the ref exposed to tests below — so a jest.spyOn(ref.current, 'x')
  // affects the same property internal code actually calls through, and
  // survives subsequent re-renders instead of being silently replaced.
  // Assigned exactly once: these five have their own stable identity
  // (created via useRef above, never recreated) precisely so a
  // jest.spyOn(ref.current, 'x') survives later re-renders — reassigning
  // them here every render, even to the same underlying function, would
  // blow away a spy sitting on the property. Everything else below is
  // fine to refresh every render since nothing depends on its identity.
  if (!stableMethodsAssignedRef.current) {
    stableMethodsAssignedRef.current = true
    Object.assign(methodsRef.current, {
      endSplitMode,
      setTaggedSource,
      refreshTagMap,
      getSelectedWords,
      helpAiAssistant,
    })
    // Test-only accessor: lets a test null out the editor DOM ref to
    // simulate it going missing (e.g. mid-unmount) without needing its own
    // exported setter method.
    Object.defineProperty(methodsRef.current, 'editor', {
      get: () => editorRef.current,
      set: (v) => {
        editorRef.current = v
      },
      configurable: true,
    })
  }

  Object.assign(methodsRef.current, {
    setState: (partial) => {
      if ('source' in partial) setSource(partial.source)
      if ('editorState' in partial) setEditorState(partial.editorState)
      if ('editAreaClasses' in partial)
        setEditAreaClasses(partial.editAreaClasses)
      if ('tagRange' in partial) setTagRange(partial.tagRange)
      if ('unlockedForCopy' in partial)
        setUnlockedForCopy(partial.unlockedForCopy)
      if ('editorStateBeforeSplit' in partial)
        setEditorStateBeforeSplit(partial.editorStateBeforeSplit)
      if ('activeDecorators' in partial)
        setActiveDecorators(partial.activeDecorators)
      if ('isShowingOptionsToolbar' in partial)
        setIsShowingOptionsToolbar(partial.isShowingOptionsToolbar)
    },
    forceUpdate: () => bumpForceRender(),
    openConcordance,
    onEntityClick,
    addSplitTag,
    removeDecorator,
    dragFragment,
    copyFragment,
    updateSplitNumberNew,
    updateSourceInStore,
    preventEdit,
    onChange,
    onBlurEvent,
    insertTagAtSelection,
    getUpdatedSegmentInfo,
    disableDecorator,
    allowHTML,
    checkDecorators,
    splitSegmentNew,
    addSearchDecorator,
    addGlossaryDecorator,
    addQaCheckGlossaryDecorator,
    addLexiqaDecorator,
    addIcuDecorator,
    getEditorState: () => editorState,
    getEditorStateBeforeSplit: () => editorStateBeforeSplit,
    getIsShowingOptionsToolbar: () => isShowingOptionsToolbar,
    getSplitPoint: () => splitPointRef.current,
    getActiveDecorators: () => activeDecorators,
    getFirstIcuCheck: () => firstIcuCheckRef.current,
    getDecoratorsStructure: () => decoratorsStructureRef.current,
    wasTripleClickTriggered: wasTripleClickTriggeredRef,
  })

  // Test-only: jsdom has no real contentEditable/Selection implementation,
  // so a few behaviors that only fire from genuine rich-text editor
  // interactions (triple-click selection, keystroke/paste handlers, and the
  // selection-dependent tag/split operations) can't be driven through
  // simulated DOM events. Exposed here so tests can invoke them directly,
  // matching how the previous class-based tests already had to work around
  // the same jsdom limitation.
  useImperativeHandle(ref, () => methodsRef.current, [])

  const updateOptionsToolbarVisibility = () => {
    if (!editorRef.current) return

    setIsShowingOptionsToolbar(
      !editorRef.current._latestEditorState.getSelection().isCollapsed(),
    )

    methodsRef.current.helpAiAssistant()
  }

  // Set correct handlers
  const handlers = !contextSegment.openSplit
    ? {
        onCut: (e) => {
          e.preventDefault()
        },
        onCopy: copyFragment,
        onBlur: onBlurEvent,
        onDragStart: dragFragment,
        onMouseUp: () => {
          setTimeout(() => {
            updateOptionsToolbarVisibility()
          })
        },
        onKeyUp: (event) => {
          if (
            event.key === 'ArrowLeft' ||
            event.key === 'ArrowRight' ||
            event.key === 'ArrowUp' ||
            event.key === 'ArrowDown'
          ) {
            updateOptionsToolbarVisibility()
          }
        },
      }
    : {
        onClick: () => addSplitTag(),
        onBlur: onBlurEvent,
      }

  const isEnabledAiAssistantButton = isValidPhraseToAiAssistant({
    phrase: methodsRef.current.getSelectedWords(),
  })

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
                  sid: contextSegment.sid,
                  value: methodsRef.current.getSelectedWords(),
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
            [TERM_FORM_FIELDS.ORIGINAL_TERM]:
              methodsRef.current.getSelectedWords(),
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
  return contextSegment.openSplit ? (
    <div className="splitContainer">
      {editorHtml}
      <div className="splitBar">
        {!!splitPointRef.current && (
          <div className="splitNum">
            Split in <span className="num">{splitPointRef.current}</span>{' '}
            segment
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
