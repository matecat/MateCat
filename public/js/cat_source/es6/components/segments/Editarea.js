import React from 'react'
import _ from 'lodash'
import Immutable from 'immutable'
import {
  Modifier,
  Editor,
  EditorState,
  getDefaultKeyBinding,
  KeyBindingUtil,
  CompositeDecorator,
} from 'draft-js'

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
import updateEntityData from './utils/DraftMatecatUtils/updateEntityData'
import LexiqaUtils from '../../utils/lxq.main'
import updateLexiqaWarnings from './utils/DraftMatecatUtils/updateLexiqaWarnings'
import insertText from './utils/DraftMatecatUtils/insertText'
import {tagSignatures} from './utils/DraftMatecatUtils/tagModel'
import SegmentActions from '../../actions/SegmentActions'
import getFragmentFromSelection from './utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import TagUtils from '../../utils/tagUtils'
import matchTypingSequence from '../../utils/matchTypingSequence/matchTypingSequence'
import {SegmentContext} from './SegmentContext'
import CatToolStore from '../../stores/CatToolStore'

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

class Editarea extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    const {onEntityClick, updateTagsInEditor, getUpdatedSegmentInfo} = this

    this.decoratorsStructure = [
      {
        name: 'tags',
        strategy: getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
        props: {
          isTarget: true,
          onClick: onEntityClick,
          getUpdatedSegmentInfo: getUpdatedSegmentInfo,
          getSearchParams: this.getSearchParams, //TODO: Make it general ?
          isRTL: config.isTargetRTL,
          sid: this.props.segment.sid,
        },
      },
    ]
    const decorator = new CompositeDecorator(this.decoratorsStructure)
    //const decorator = new CompoundDecorator(this.decoratorsStructure);
    // Escape html
    const translation = DraftMatecatUtils.unescapeHTMLLeaveTags(
      this.props.translation,
    )
    // If GuessTag is Enabled, clean translation from tags
    const cleanTranslation = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
      ? DraftMatecatUtils.cleanSegmentString(translation)
      : translation
    // Inizializza Editor State con solo testo
    const plainEditorState = EditorState.createEmpty(decorator)
    const contentEncoded = DraftMatecatUtils.encodeContent(
      plainEditorState,
      cleanTranslation,
    )
    const {editorState, tagRange} = contentEncoded

    this.state = {
      editorState: editorState,
      editAreaClasses: ['targetarea'],
      tagRange: tagRange,
      // TagMenu
      autocompleteSuggestions: [],
      focusedTagIndex: 0,
      displayPopover: false,
      popoverPosition: {},
      editorFocused: true,
      clickedOnTag: false,
      triggerText: null,
      activeDecorators: {
        [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        [DraftMatecatConstants.QA_BLACKLIST_DECORATOR]: false,
        [DraftMatecatConstants.SEARCH_DECORATOR]: false,
      },
      previousSourceTagMap: null,
    }
    const cleanTagsTranslation = TagUtils.decodePlaceholdersToPlainText(
      DraftMatecatUtils.cleanSegmentString(translation),
    )
    this.props.updateCounter(
      DraftMatecatUtils.getCharactersCounter(cleanTagsTranslation),
    )

    this.updateTranslationDebounced = _.debounce(
      this.updateTranslationInStore,
      100,
    )
    this.updateTagsInEditorDebounced = _.debounce(updateTagsInEditor, 500)
    this.onCompositionStopDebounced = _.debounce(this.onCompositionStop, 1000)
    this.focusEditorDebounced = _.debounce(this.focusEditor, 500)
  }

  getSearchParams = () => {
    const {
      inSearch,
      currentInSearch,
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
    } = this.props.segment
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
  }

  addSearchDecorator = () => {
    let {tagRange} = this.state
    let {searchParams, occurrencesInSearch, currentInSearchIndex} =
      this.props.segment
    const textToSearch = searchParams.target ? searchParams.target : ''
    const newDecorator = DraftMatecatUtils.activateSearch(
      textToSearch,
      searchParams,
      occurrencesInSearch.occurrences,
      currentInSearchIndex,
      tagRange,
    )
    _.remove(
      this.decoratorsStructure,
      (decorator) => decorator.name === DraftMatecatConstants.SEARCH_DECORATOR,
    )
    this.decoratorsStructure.push(newDecorator)
  }

  addQaBlacklistGlossaryDecorator = () => {
    let {qaBlacklistGlossary, sid} = this.props.segment
    const newDecorator = DraftMatecatUtils.activateQaCheckBlacklist(
      qaBlacklistGlossary,
      sid,
    )
    _.remove(
      this.decoratorsStructure,
      (decorator) =>
        decorator.name === DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
    )
    this.decoratorsStructure.push(newDecorator)
  }

  addLexiqaDecorator = () => {
    let {editorState} = this.state
    let {lexiqa, sid, lxqDecodedTranslation} = this.props.segment
    // pass decoded translation with tags like <g id='1'>
    let ranges = LexiqaUtils.getRanges(
      _.cloneDeep(lexiqa.target),
      lxqDecodedTranslation,
      false,
    )
    const updatedLexiqaWarnings = updateLexiqaWarnings(editorState, ranges)
    if (updatedLexiqaWarnings.length > 0) {
      const newDecorator = DraftMatecatUtils.activateLexiqa(
        editorState,
        updatedLexiqaWarnings,
        sid,
        false,
        this.getUpdatedSegmentInfo,
      )
      _.remove(
        this.decoratorsStructure,
        (decorator) =>
          decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      this.decoratorsStructure.push(newDecorator)
    } else {
      this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
    }
  }

  //Receive the new translation and decode it for draftJS
  setNewTranslation = (sid, translation) => {
    if (sid === this.props.segment.sid) {
      const {editorState} = this.state
      const contentEncoded = DraftMatecatUtils.encodeContent(
        editorState,
        DraftMatecatUtils.unescapeHTMLLeaveTags(translation),
        this.props.segment.sourceTagMap,
      )
      // this must be done to make the Undo action possible, otherwise encodeContent will delete all editor history
      let {editorState: newEditorState} = contentEncoded
      const newContentState = newEditorState.getCurrentContent()
      newEditorState = EditorState.push(
        editorState,
        newContentState,
        'insert-fragment',
      )
      const cleanTagsTranslation = TagUtils.decodePlaceholdersToPlainText(
        DraftMatecatUtils.cleanSegmentString(translation),
      )
      this.props.updateCounter(
        DraftMatecatUtils.getCharactersCounter(cleanTagsTranslation),
      )
      this.setState(
        {
          editorState: newEditorState,
        },
        () => {
          this.updateTranslationDebounced()
        },
      )
    }
  }

  replaceCurrentSearch = (text) => {
    let {
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
      currentInSearch,
    } = this.props.segment
    if (currentInSearch && searchParams.target) {
      let index = _.findIndex(
        occurrencesInSearch.occurrences,
        (item) => item.searchProgressiveIndex === currentInSearchIndex,
      )
      const newEditorState = DraftMatecatUtils.replaceOccurrences(
        this.state.editorState,
        searchParams.target,
        text,
        index,
      )
      this.setState(
        {
          editorState: newEditorState,
        },
        () => {
          this.updateTranslationInStore()
        },
      )
    }
  }

  updateTranslationInStore = () => {
    const {editorState} = this.state
    const {
      segment,
      segment: {sourceTagMap},
    } = this.props
    const {decodedSegment, entitiesRange} =
      DraftMatecatUtils.decodeSegment(editorState)
    if (decodedSegment !== '') {
      let contentState = editorState.getCurrentContent()
      let plainText = contentState
        .getPlainText()
        .replace(
          new RegExp(String.fromCharCode(parseInt('200B', 16)), 'gi'),
          '',
        )
      // Match tag without compute tag id
      const currentTagRange = DraftMatecatUtils.matchTagInEditor(
        editorState,
        entitiesRange,
      )
      // Add missing tag to store for highlight warnings on tags
      const {missingTags} = checkForMissingTags(sourceTagMap, currentTagRange)
      const lxqDecodedTranslation =
        DraftMatecatUtils.prepareTextForLexiqa(editorState)
      //const currentTagRange = matchTag(decodedSegment); //deactivate if updateTagsInEditor is active
      SegmentActions.updateTranslation(
        segment.sid,
        decodedSegment,
        plainText,
        currentTagRange,
        missingTags,
        lxqDecodedTranslation,
      )
      const cleanTranslation = TagUtils.decodePlaceholdersToPlainText(
        DraftMatecatUtils.cleanSegmentString(decodedSegment),
      )
      this.props.updateCounter(
        DraftMatecatUtils.getCharactersCounter(cleanTranslation),
      )
      // console.log('updatingTranslationInStore');
      UI.registerQACheck()
    } else {
      this.props.updateCounter(0)
    }
  }

  checkDecorators = (prevProps) => {
    let changedDecorator = false
    const {inSearch} = this.props.segment
    const {activeDecorators: prevActiveDecorators, editorState} = this.state
    const activeDecorators = {...prevActiveDecorators}

    if (!inSearch) {
      //Qa Check Blacklist
      const {qaBlacklistGlossary} = this.props.segment
      const prevQaBlacklistGlossary = prevProps
        ? prevProps.segment.qaBlacklistGlossary
        : undefined
      if (
        qaBlacklistGlossary &&
        qaBlacklistGlossary.length > 0 &&
        !activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] /* &&
        (_.isUndefined(prevQaBlacklistGlossary) ||
          !Immutable.fromJS(prevQaBlacklistGlossary).equals(
            Immutable.fromJS(qaBlacklistGlossary),
          )) */
      ) {
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = true
        changedDecorator = true
        this.addQaBlacklistGlossaryDecorator()
      } else if (
        prevQaBlacklistGlossary &&
        prevQaBlacklistGlossary.length > 0 &&
        (!qaBlacklistGlossary || qaBlacklistGlossary.length === 0)
      ) {
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.QA_BLACKLIST_DECORATOR)
      }

      //Lexiqa
      const {lexiqa} = this.props.segment
      const prevLexiqa = prevProps ? prevProps.segment.lexiqa : undefined
      const currentLexiqaTarget =
        lexiqa && lexiqa.target && _.size(lexiqa.target)
      const prevLexiqaTarget =
        prevLexiqa && prevLexiqa.target && _.size(prevLexiqa.target)
      const lexiqaChanged =
        prevLexiqaTarget &&
        currentLexiqaTarget &&
        !Immutable.fromJS(prevLexiqa.target).equals(
          Immutable.fromJS(lexiqa.target),
        )

      if (
        //Condition to understand if the job has tm keys or if the check glossary request has been made (blacklist must take precedence over lexiqa)
        (CatToolStore.getHaveKeysGlossary() === false ||
          Array.isArray(qaBlacklistGlossary)) &&
        currentLexiqaTarget &&
        (!prevLexiqaTarget ||
          lexiqaChanged ||
          !prevActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
        changedDecorator = true
        this.addLexiqaDecorator()
      } else if (prevLexiqaTarget && !currentLexiqaTarget) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
      }
      //Search
      if (prevProps && prevProps.segment.inSearch) {
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.SEARCH_DECORATOR)
      }
    } else {
      //Search
      if (
        this.props.segment.searchParams.target &&
        (!prevProps ||
          !prevProps.segment.inSearch || //Before was not active
          (prevProps.segment.inSearch &&
            !Immutable.fromJS(prevProps.segment.searchParams).equals(
              Immutable.fromJS(this.props.segment.searchParams),
            )) || //Before was active but some params change
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearch !==
              this.props.segment.currentInSearch) || //Before was the current
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearchIndex !==
              this.props.segment.currentInSearchIndex))
      ) {
        //There are more occurrences and the current change
        // Cleanup all decorators
        this.removeDecorator()
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR] = false
        this.addSearchDecorator()
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
        changedDecorator = true
      }
    }

    if (changedDecorator) {
      const decorator = new CompositeDecorator(this.decoratorsStructure)
      setTimeout(() => {
        this.setState({
          editorState: EditorState.set(editorState, {decorator}),
          activeDecorators,
        })
      })
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.REPLACE_TRANSLATION,
      this.setNewTranslation,
    )
    SegmentStore.addListener(
      EditAreaConstants.REPLACE_SEARCH_RESULTS,
      this.replaceCurrentSearch,
    )
    SegmentStore.addListener(
      EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
      this.copyGlossaryToEditArea,
    )
    setTimeout(() => {
      this.checkDecorators()
      this.updateTranslationInStore()
      if (this.props.segment.opened) {
        this.focusEditor()
      }
    })
  }

  copyGlossaryToEditArea = (segment, glossaryTranslation) => {
    if (segment.sid === this.props.segment.sid) {
      const {editorState} = this.state
      const newEditorState = DraftMatecatUtils.insertText(
        editorState,
        glossaryTranslation,
      )
      this.setState(
        {
          editorState: newEditorState,
        },
        () => {
          this.updateTranslationDebounced()
        },
      )
    }
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.REPLACE_TRANSLATION,
      this.setNewTranslation,
    )
    SegmentStore.removeListener(
      EditAreaConstants.REPLACE_SEARCH_RESULTS,
      this.replaceCurrentSearch,
    )
    SegmentStore.removeListener(
      EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA,
      this.copyGlossaryToEditArea,
    )
  }

  componentDidUpdate(prevProps) {
    if (!prevProps.segment.opened && this.props.segment.opened) {
      const newEditorState = EditorState.moveFocusToEnd(this.state.editorState)
      this.setState({editorState: newEditorState})
    } else if (prevProps.segment.opened && !this.props.segment.opened) {
      const newEditorState = EditorState.moveSelectionToEnd(
        this.state.editorState,
      )
      this.setState({editorState: newEditorState})
    }
    if (
      !this.state.editorState.isInCompositionMode() &&
      !editorSync.onComposition
    ) {
      this.checkDecorators(prevProps)
    }

    // update editor state when receive prop of segment "sourceTagMap"
    if (
      this.props.segment.sourceTagMap?.length &&
      !_.isEqual(
        this.state.previousSourceTagMap,
        this.props.segment.sourceTagMap,
      )
    ) {
      this.setState({previousSourceTagMap: this.props.segment.sourceTagMap})
      this.setNewTranslation(this.props.segment.sid, this.props.translation)
    }
  }

  render() {
    const {
      editorState,
      displayPopover,
      autocompleteSuggestions,
      focusedTagIndex,
      popoverPosition,
    } = this.state

    const {
      onChange,
      copyFragment,
      pasteFragment,
      onTagClick,
      handleKeyCommand,
      myKeyBindingFn,
      onMouseUpEvent,
      onBlurEvent,
      onFocus,
      onDragEvent,
      onDragEnd,
      onKeyUpEvent,
    } = this

    let lang = ''
    let readonly = false

    if (this.props.segment) {
      lang = config.target_rfc.toLowerCase()
      readonly =
        this.context.readonly ||
        this.context.locked ||
        this.props.segment.muted ||
        !this.props.segment.opened
    }
    let classes = this.state.editAreaClasses.slice()
    if (this.context.locked || this.context.readonly) {
      classes.push('area')
    } else {
      classes.push('editarea')
    }

    return (
      <div
        className={classes.join(' ')}
        ref={(ref) => (this.editAreaRef = ref)}
        id={'segment-' + this.props.segment.sid + '-editarea'}
        data-sid={this.props.segment.sid}
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
      >
        <Editor
          lang={lang}
          editorState={editorState}
          onChange={onChange}
          handlePastedText={pasteFragment}
          ref={(el) => (this.editor = el)}
          readOnly={readonly}
          handleKeyCommand={handleKeyCommand}
          keyBindingFn={myKeyBindingFn}
          handleDrop={this.handleDrop}
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
  }

  focusEditor = () => {
    if (this.editor) this.editor.focus()
  }

  typeTextInEditor = (textToInsert) => {
    const {editorState} = this.state
    editorSync.onComposition = true
    let newEditorState = this.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    newEditorState = DraftMatecatUtils.insertText(newEditorState, textToInsert)
    this.setState(
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
        this.updateTranslationDebounced()
        // Reactivate decorators
        this.onCompositionStopDebounced()
      },
    )
  }

  myKeyBindingFn = (e) => {
    const {displayPopover} = this.state
    const {handleCursorMovement} = this
    const isChromeBook = navigator.userAgent.indexOf('CrOS') > -1
    if (
      (e.keyCode === 84 || e.key === 't' || e.key === '™') &&
      (isOptionKeyCommand(e) || e.altKey) &&
      !e.shiftKey
    ) {
      this.setState({triggerText: null})
      return 'toggle-tag-menu'
    } else if (e.key === '<' && !hasCommandModifier(e)) {
      this.typeTextInEditor('<')
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
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      ((isCtrlKeyCommand(e) && e.shiftKey) ||
        (CommonUtils.isMacOS() && isOptionKeyCommand(e) && !e.ctrlKey))
    ) {
      return 'insert-nbsp-tag' // Windows && Mac
    } else if (
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      !e.shiftKey &&
      e.altKey &&
      isChromeBook
    ) {
      return 'insert-nbsp-tag' // Chromebook
    } else if (e.key === 'ArrowLeft' && !e.altKey) {
      if (e.shiftKey) {
        if (handleCursorMovement(-1, true, config.isTargetRTL))
          return 'left-nav-shift'
      } else {
        if (handleCursorMovement(-1, false, config.isTargetRTL))
          return 'left-nav'
      }
    } else if (e.key === 'ArrowRight' && !e.altKey) {
      if (e.shiftKey) {
        if (handleCursorMovement(1, true, config.isTargetRTL))
          return 'right-nav-shift'
      } else {
        if (handleCursorMovement(1, false, config.isTargetRTL))
          return 'right-nav'
      }
    } else if (e.ctrlKey && e.key === 'k') {
      return 'tm-search'
    } else if (
      (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
      ((e.ctrlKey && e.altKey) || (CommonUtils.isMacOS() && e.shiftKey))
    ) {
      return 'insert-word-joiner-tag'
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
    }
    return getDefaultKeyBinding(e)
  }

  handleKeyCommand = (command) => {
    const {
      openPopover,
      closePopover,
      getEditorRelativeSelectionOffset,
      moveDownTagMenuSelection,
      moveUpTagMenuSelection,
      acceptTagMenuSelection,
      insertTagAtSelection,
    } = this
    const {
      segment: {sourceTagMap, missingTagsInTarget},
    } = this.props

    switch (command) {
      case 'toggle-tag-menu': {
        const tagSuggestions = {
          missingTags: missingTagsInTarget,
          sourceTags: sourceTagMap,
        }
        if (tagSuggestions.sourceTags && tagSuggestions.sourceTags.length > 0) {
          openPopover(tagSuggestions, getEditorRelativeSelectionOffset())
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
      case 'left-nav':
        return 'handled'
      case 'left-nav-shift':
        return 'handled'
      case 'right-nav':
        return 'handled'
      case 'right-nav-shift':
        return 'handled'
      case 'insert-tab-tag':
        insertTagAtSelection('tab')
        return 'handled'
      case 'insert-nbsp-tag':
        insertTagAtSelection('nbsp')
        return 'handled'
      case 'add-issue':
        return 'handled'
      case 'insert-word-joiner-tag':
        insertTagAtSelection('wordJoiner')
        return 'handled'
      case 'translate':
        return 'not-handled'
      case 'next-translate':
        return 'not-handled'
      default:
        return 'not-handled'
    }
  }

  insertTagAtSelection = (tagName) => {
    const {editorState} = this.state
    const customTag = DraftMatecatUtils.structFromName(tagName)
    // If tag creation has failed, return
    if (!customTag) return
    // Start composition mode and remove lexiqa
    editorSync.onComposition = true
    let newEditorState = this.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    newEditorState = DraftMatecatUtils.insertEntityAtSelection(
      newEditorState,
      customTag,
    )
    this.setState(
      (prevState) => ({
        activeDecorators: {
          ...prevState.activeDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        },
        editorState: newEditorState,
      }),
      () => {
        // Reactivate decorators
        this.updateTranslationDebounced()
        // Stop composition mode
        this.onCompositionStopDebounced()
      },
    )
  }

  handleCursorMovement = (step, shift = false, isRTL = false) => {
    const {editorState} = this.state
    step = isRTL ? step * -1 : step
    // When in composition mode avoid setState to let editor commit its "composition state"
    if (editorState.isInCompositionMode()) return true

    const newState = DraftMatecatUtils.moveCursorJumpEntity(
      editorState,
      step,
      shift,
      isRTL,
    )
    if (newState) {
      this.setState({editorState: newState})
      return true
    }
    return false
  }

  onMouseUpEvent = () => {
    const {toggleFormatMenu} = this.props
    toggleFormatMenu(
      !this.editor._latestEditorState.getSelection().isCollapsed(),
    )
  }

  onKeyUpEvent = (event) => {
    if (
      event.key === 'ArrowLeft' ||
      event.key === 'ArrowRight' ||
      event.key === 'ArrowUp' ||
      event.key === 'ArrowDown'
    ) {
      const {toggleFormatMenu} = this.props
      toggleFormatMenu(
        !this.editor._latestEditorState.getSelection().isCollapsed(),
      )
    }
  }

  onBlurEvent = () => {
    const {toggleFormatMenu} = this.props
    editorSync.editorFocused = false
    // Hide Edit Toolbar
    toggleFormatMenu(false)
  }

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

  onFocus = () => {
    editorSync.editorFocused = true
  }

  updateTagsInEditor = () => {
    const {editorState, tagRange} = this.state
    let newEditorState = editorState
    let newTagRange = tagRange
    // Cerco i tag attualmente presenti nell'editor
    // Todo: Se ci sono altre entità oltre i tag nell'editor, aggiungere l'entityName alla chiamata
    const entities = DraftMatecatUtils.getEntities(editorState)
    if (tagRange.length !== entities.length) {
      const lastSelection = editorState.getSelection()
      // Aggiorna i tag presenti
      const {decodedSegment} = DraftMatecatUtils.decodeSegment(editorState)
      newTagRange = DraftMatecatUtils.matchTag(decodedSegment) // range update
      // Aggiornamento live dei collegamenti tra i tag non self-closed
      newEditorState = updateEntityData(
        editorState,
        newTagRange,
        lastSelection,
        entities,
      )
    }
    this.setState({
      editorState: newEditorState,
      tagRange: newTagRange,
    })
  }

  onCompositionStop = () => {
    if (editorSync.onComposition) {
      editorSync.onComposition = false
      // Tell tags to update themself
      setTimeout(() => {
        SegmentActions.editAreaChanged(this.props.segment.sid, true)
      })
    }
  }

  removeDecorator = (decoratorName) => {
    if (!decoratorName) {
      _.remove(
        this.decoratorsStructure,
        (decorator) => decorator.name !== DraftMatecatConstants.TAGS_DECORATOR,
      )
    } else {
      _.remove(
        this.decoratorsStructure,
        (decorator) => decorator.name === decoratorName,
      )
    }
  }

  // has to be followed by a setState for editorState
  disableDecorator = (editorState, decoratorName) => {
    _.remove(
      this.decoratorsStructure,
      (decorator) => decorator.name === decoratorName,
    )
    //const decorator = new CompoundDecorator(this.decoratorsStructure);
    const decorator = new CompositeDecorator(this.decoratorsStructure)
    return EditorState.set(editorState, {decorator})
  }

  onChange = (editorState) => {
    //console.log('onChange')
    const {
      displayPopover,
      editorState: prevEditorState,
      activeDecorators,
    } = this.state
    const {closePopover} = this

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
      editorSync.onComposition = true
      // ...remove unwanted decorators like lexiqa and qa blacklist...
      if (activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR]) {
        editorState = this.disableDecorator(
          editorState,
          DraftMatecatConstants.LEXIQA_DECORATOR,
        )
        newActiveDecorators = {
          ...newActiveDecorators,
          [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        }
      }
      if (activeDecorators[DraftMatecatConstants.QA_BLACKLIST_DECORATOR]) {
        editorState = this.disableDecorator(
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
      this.setState(
        () => ({
          activeDecorators: newActiveDecorators,
          editorState: editorState,
        }),
        () => {
          // Reactivate decorators
          this.updateTranslationDebounced()
          this.onCompositionStopDebounced()
        },
      )
    } else {
      this.setState(
        () => ({
          editorState: editorState,
        }),
        () => {
          this.onCompositionStopDebounced()
        },
      )
    }
  }

  // fix cursor jump at the beginning
  forceSelectionFocus = (editorState) => {
    const currentSelection = editorState.getSelection()
    if (!currentSelection.getHasFocus()) {
      const selection = currentSelection.set('hasFocus', true)
      editorState = EditorState.acceptSelection(editorState, selection)
    }
    return editorState
  }

  // Methods for TagMenu ---- START
  moveUpTagMenuSelection = () => {
    const {displayPopover} = this.state
    if (!displayPopover) return
    const {
      focusedTagIndex,
      autocompleteSuggestions: {missingTags, sourceTags},
    } = this.state
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    const newFocusedTagIndex =
      focusedTagIndex - 1 < 0
        ? mergeAutocompleteSuggestions.length - 1
        : (focusedTagIndex - 1) % mergeAutocompleteSuggestions.length

    this.setState({
      focusedTagIndex: newFocusedTagIndex,
    })
  }

  moveDownTagMenuSelection = () => {
    const {displayPopover} = this.state
    if (!displayPopover) return
    const {
      focusedTagIndex,
      autocompleteSuggestions: {missingTags, sourceTags},
    } = this.state
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    this.setState({
      focusedTagIndex:
        (focusedTagIndex + 1) % mergeAutocompleteSuggestions.length,
    })
  }

  acceptTagMenuSelection = () => {
    const {
      focusedTagIndex,
      displayPopover,
      editorState,
      triggerText,
      autocompleteSuggestions: {missingTags = [], sourceTags},
    } = this.state
    if (!displayPopover) return
    const mergeAutocompleteSuggestions = [...missingTags, ...sourceTags]
    const selectedTag = mergeAutocompleteSuggestions[focusedTagIndex]
    // Start typing
    editorSync.onComposition = true
    // Remove lexiqa while typing
    let newEditorState = this.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    const editorStateWithSuggestedTag = insertTag(
      selectedTag,
      newEditorState,
      triggerText,
    )
    this.setState(
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
        this.updateTranslationDebounced()
        // Stop typing
        this.onCompositionStopDebounced()
      },
    )
  }

  openPopover = (suggestions, position) => {
    // Posizione da salvare e passare al compoennte
    const popoverPosition = {
      top: position.top,
      left: position.left,
    }

    this.setState({
      displayPopover: true,
      autocompleteSuggestions: suggestions,
      focusedTagIndex: 0,
      popoverPosition: popoverPosition,
    })
  }

  closePopover = () => {
    this.setState({
      displayPopover: false,
      triggerText: null,
    })
  }

  onTagClick = (suggestionTag) => {
    const {editorState, triggerText} = this.state
    // Start typing...
    editorSync.onComposition = true
    // Disable lexiqa while typing
    let newEditorState = this.disableDecorator(
      editorState,
      DraftMatecatConstants.LEXIQA_DECORATOR,
    )
    let editorStateWithSuggestedTag = insertTag(
      suggestionTag,
      newEditorState,
      triggerText,
    )
    this.setState(
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
        this.updateTranslationDebounced()
        // Stop typing
        this.onCompositionStopDebounced()
      },
    )
  }

  // Methods for TagMenu ---- END

  onPaste = () => {
    const {editorState} = this.state
    const internalClipboard = this.editor.getClipboard()
    if (internalClipboard) {
      const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
        internalClipboard,
        editorState,
      )
      this.onChange(clipboardEditorPasted)
      this.setState({
        editorState: clipboardEditorPasted,
      })
      return true
    } else {
      return false
    }
  }

  pasteFragment = (text) => {
    const {editorState} = this.state
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
        let fragment = DraftMatecatUtils.buildFragmentFromJson(
          fragmentContent.orderedMap,
        )
        const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
          fragment,
          editorState,
          fragmentContent.entitiesMap,
        )
        this.setState(
          {
            editorState: clipboardEditorPasted,
          },
          () => {
            this.updateTranslationDebounced()
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
      let cleanText = DraftMatecatUtils.cleanSegmentString(
        DraftMatecatUtils.unescapeHTML(text),
      )
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
      this.setState(
        {
          editorState: clipboardEditorPasted,
        },
        () => {
          this.updateTranslationDebounced()
        },
      )
      // Paste fragment
      return true
    }
    // Paste plain standard clipboard
    return false
  }

  copyFragment = (e) => {
    const internalClipboard = this.editor.getClipboard()
    const {editorState} = this.state
    if (internalClipboard) {
      e.preventDefault()
      // Get plain text form internalClipboard fragment
      const plainText = internalClipboard
        .map((block) => block.getText())
        .join('\n')
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

  onDragEvent = () => {
    editorSync.draggingFromEditArea = true
  }

  onDragEnd = () => {
    editorSync.draggingFromEditArea = false
  }

  handleDrop = (selection, dataTransfer) => {
    let {editorState} = this.state
    const text = dataTransfer.getText()

    // get selection of dragged text
    const dragSelection = editorState.getSelection()
    const dragSelectionLength =
      dragSelection.focusOffset - dragSelection.anchorOffset
    // get the fragment from current selection in editor (the highlighted tag)
    let fragmentFromSelection = getFragmentFromSelection(editorState)
    // Il fragment di draft NON FUNZIONA quindi lo ricostruisco
    let tempFrag = DraftMatecatUtils.buildFragmentFromJson(
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
        let fragment = DraftMatecatUtils.buildFragmentFromJson(
          fragmentContent.orderedMap,
        )
        const editorStateWithFragment = DraftMatecatUtils.duplicateFragment(
          fragment,
          editorState,
          fragmentContent.entitiesMap,
        )
        this.setState(
          {
            editorState: editorStateWithFragment,
          },
          () => {
            this.updateTranslationDebounced()
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

        this.setState(
          {
            editorState: editorState,
          },
          () => {
            this.updateTranslationDebounced()
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
  }

  onEntityClick = (start, end) => {
    const {editorState} = this.state
    // Use _latestEditorState
    try {
      // Selection
      const latestEditorState = this.editor._latestEditorState
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

      let newSelection = selectionState.merge({
        anchorOffset: start - addZwspExtraStepBefore, // -1 is to catch the zero-width space char placed before every entity
        focusOffset: end + addZwspExtraStepAfter, // +1 is to catch the zero-width space char placed after every entity
      })
      const newEditorState = EditorState.forceSelection(
        editorState,
        newSelection,
      )
      this.setState({editorState: newEditorState})
      // Highlight
    } catch (e) {
      console.log('Invalid selection')
    }
  }

  /**
   *
   * @param minWidth - min length of element to show
   * @returns {{top: number, left: number}}
   */
  getEditorRelativeSelectionOffset = (minWidth = 300) => {
    const editorBoundingRect = this.editor.editor.getBoundingClientRect()
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
  }

  getUpdatedSegmentInfo = () => {
    const {
      segment: {
        sid,
        warnings,
        tagMismatch,
        opened,
        missingTagsInTarget,
        openSplit,
      },
    } = this.props
    const {tagRange, editorState} = this.state
    return {
      sid,
      warnings,
      tagMismatch,
      tagRange,
      segmentOpened: opened,
      missingTagsInTarget,
      currentSelection: this.editor
        ? this.editor._latestEditorState.getSelection()
        : editorState.getSelection(),
      openSplit,
    }
  }

  formatSelection = (format) => {
    const {editorState} = this.state
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

    this.setState(
      {
        editorState: newEditorState,
      },
      () => {
        this.updateTranslationDebounced()
      },
    )
  }

  addMissingSourceTagsToTarget = () => {
    const {segment} = this.props
    const {editorState} = this.state
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
    let segmentTargetTagMap = [
      ...segment.targetTagMap,
      ...segment.missingTagsInTarget,
    ]
    // Insert tag entity in current editor without recompute tags associations
    this.setState({
      editorState: newEditorState,
    })
    //lock tags and run again getWarnings
    setTimeout(() => {
      SegmentActions.updateTranslation(
        segment.sid,
        newTranslation,
        newDecodedTranslation,
        segmentTargetTagMap,
        [],
      )
      SegmentActions.getSegmentsQa(this.props.segment)
    }, 100)
  }
}

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

export default Editarea
