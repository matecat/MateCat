import React from 'react'
import Immutable from 'immutable'
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
import updateLexiqaWarnings from './utils/DraftMatecatUtils/updateLexiqaWarnings'
import getFragmentFromSelection from './utils/DraftMatecatUtils/DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import {getSplitPointTag} from './utils/DraftMatecatUtils/tagModel'
import {SegmentContext} from './SegmentContext'
import Assistant from '../icons/Assistant'
import Education from '../icons/Education'
import {TERM_FORM_FIELDS} from './SegmentFooterTabGlossary/SegmentFooterTabGlossary'
import {getEntitiesSelected} from './utils/DraftMatecatUtils/manageCaretPositionNearEntity'
import {UseHotKeysComponent} from '../../hooks/UseHotKeysComponent'
import {flushSync} from 'react-dom'

class SegmentSource extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    const {onEntityClick, getUpdatedSegmentInfo} = this
    this.originalSource = this.props.segment.segment
    this.openConcordance = this.openConcordance.bind(this)
    this.decoratorsStructure = [
      {
        name: 'tags',
        strategy: getEntityStrategy('IMMUTABLE'),
        component: TagEntity,
        props: {
          onClick: onEntityClick,
          getUpdatedSegmentInfo: getUpdatedSegmentInfo,
          isTarget: false,
          getSearchParams: this.getSearchParams,
          isRTL: config.isSourceRTL,
          sid: this.props.segment.sid,
        },
      },
    ]
    //const decorator = new CompoundDecorator(this.decoratorsStructure);
    const decorator = new CompositeDecorator(this.decoratorsStructure)
    // Initialise EditorState
    const plainEditorState = EditorState.createEmpty(decorator)
    const translation = this.props.segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    // New EditorState with translation
    const contentEncoded = DraftMatecatUtils.encodeContent(
      plainEditorState,
      cleanSource,
    )
    const {editorState, tagRange} = contentEncoded
    this.state = {
      source: cleanSource,
      editorState: editorState,
      editAreaClasses: ['targetarea'],
      tagRange: tagRange,
      unlockedForCopy: false,
      editorStateBeforeSplit: editorState,
      activeDecorators: {
        [DraftMatecatConstants.LEXIQA_DECORATOR]: false,
        [DraftMatecatConstants.GLOSSARY_DECORATOR]: false,
        [DraftMatecatConstants.QA_GLOSSARY_DECORATOR]: false,
        [DraftMatecatConstants.SEARCH_DECORATOR]: false,
      },
      isShowingOptionsToolbar: false,
    }
    this.splitPoint = this.props.segment.split_group
      ? this.props.segment.split_group.length - 1
      : 0

    this.delayAiAssistant
  }

  getSearchParams = () => {
    const {
      inSearch,
      currentInSearch,
      searchParams,
      occurrencesInSearch,
      currentInSearchIndex,
    } = this.props.segment
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

  // Restore tagged source in draftJS after GuessTag
  setTaggedSource = (sid) => {
    if (sid === this.props.segment.sid) {
      // Escape html

      const translation = this.props.segment.segment

      // If GuessTag enabled, clean string from tag
      const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled()
        ? DraftMatecatUtils.removeTagsFromText(translation)
        : translation
      // TODO: get taggedSource from store
      const contentEncoded = DraftMatecatUtils.encodeContent(
        this.state.editorState,
        cleanSource,
      )
      const {editorState, tagRange} = contentEncoded
      this.setState({
        editorState: editorState,
        tagRange: tagRange,
      })
      setTimeout(() => this.updateSourceInStore())
    }
  }

  openConcordance(e) {
    e.preventDefault()
    var selection = window.getSelection()
    if (selection.type === 'Range') {
      // something is selected
      var str = selection.toString().trim()
      if (str.length) {
        // the trimmed string is not empty
        SegmentActions.openConcordance(this.props.segment.sid, str, false)
      }
    }
  }

  addSearchDecorator = () => {
    let {tagRange} = this.state
    let {searchParams, occurrencesInSearch, currentInSearchIndex} =
      this.props.segment
    const textToSearch = searchParams.source ? searchParams.source : ''
    const newDecorator = DraftMatecatUtils.activateSearch(
      textToSearch,
      searchParams,
      occurrencesInSearch.occurrences,
      currentInSearchIndex,
      tagRange,
    )
    remove(
      this.decoratorsStructure,
      (decorator) => decorator.name === DraftMatecatConstants.SEARCH_DECORATOR,
    )
    this.decoratorsStructure.push(newDecorator)
  }

  addGlossaryDecorator = () => {
    let {glossary, sid} = this.props.segment
    const newDecorator = DraftMatecatUtils.activateGlossary(
      glossary.filter(({isBlacklist}) => !isBlacklist),
      sid,
    )
    remove(
      this.decoratorsStructure,
      (decorator) =>
        decorator.name === DraftMatecatConstants.GLOSSARY_DECORATOR,
    )
    this.decoratorsStructure.push(newDecorator)
  }

  addQaCheckGlossaryDecorator = () => {
    let {glossary, segment, sid} = this.props.segment
    const missingGossaryItems = glossary.filter((item) => item.missingTerm)
    const newDecorator = DraftMatecatUtils.activateQaCheckGlossary(
      missingGossaryItems,
      segment,
      sid,
      SegmentActions.activateTab,
    )
    remove(
      this.decoratorsStructure,
      (decorator) =>
        decorator.name === DraftMatecatConstants.QA_GLOSSARY_DECORATOR,
    )
    this.decoratorsStructure.push(newDecorator)
  }

  addLexiqaDecorator = () => {
    let {editorState} = this.state
    let {lexiqa, sid, lxqDecodedSource} = this.props.segment
    let ranges = LexiqaUtils.getRanges(
      cloneDeep(lexiqa.source),
      lxqDecodedSource,
      true,
    )
    const updatedLexiqaWarnings = updateLexiqaWarnings(editorState, ranges)
    if (updatedLexiqaWarnings.length > 0) {
      const newDecorator = DraftMatecatUtils.activateLexiqa(
        editorState,
        updatedLexiqaWarnings,
        sid,
        true,
        this.getUpdatedSegmentInfo,
      )
      remove(
        this.decoratorsStructure,
        (decorator) =>
          decorator.name === DraftMatecatConstants.LEXIQA_DECORATOR,
      )
      this.decoratorsStructure.push(newDecorator)
    } else {
      this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
    }
  }

  updateSourceInStore = () => {
    if (this.state.source !== '') {
      const {editorState, tagRange} = this.state
      let contentState = editorState.getCurrentContent()
      let plainText = contentState.getPlainText()
      const {decodedSegment} = DraftMatecatUtils.decodeSegment(editorState)
      const lxqDecodedSource =
        DraftMatecatUtils.prepareTextForLexiqa(decodedSegment)
      SegmentActions.updateSource(
        this.props.segment.sid,
        decodedSegment,
        plainText,
        tagRange,
        lxqDecodedSource,
      )
    }
  }

  checkDecorators = (prevProps) => {
    let changedDecorator = false
    const {inSearch, searchParams, currentInSearch, currentInSearchIndex} =
      this.props.segment
    const {activeDecorators: prevActiveDecorators, editorState} = this.state
    const activeDecorators = {...prevActiveDecorators}

    if (!inSearch) {
      //Glossary
      const {glossary} = this.props.segment
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
          !Immutable.fromJS(prevMissingGlossaryItems).equals(
            Immutable.fromJS(missingGlossaryItems),
          ))
      ) {
        this.addQaCheckGlossaryDecorator()
        changedDecorator = true
        activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] = true
      } else if (
        prevMissingGlossaryItems &&
        prevMissingGlossaryItems.length > 0 &&
        (!missingGlossaryItems || missingGlossaryItems.length === 0)
      ) {
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.QA_GLOSSARY_DECORATOR)
        activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] = false
      }

      if (
        glossary &&
        size(glossary) > 0 &&
        (isUndefined(prevGlossary) ||
          !Immutable.fromJS(prevGlossary).equals(Immutable.fromJS(glossary)) ||
          !prevActiveDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = true
        changedDecorator = true
        this.addGlossaryDecorator()
      } else if (
        size(prevGlossary) > 0 &&
        (!glossary || size(glossary) === 0)
      ) {
        activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.GLOSSARY_DECORATOR)
      }
      //Lexiqa
      const {lexiqa} = this.props.segment
      const prevLexiqa = prevProps ? prevProps.segment.lexiqa : undefined
      const currentLexiqaSource = lexiqa && lexiqa.source && size(lexiqa.source)
      const prevLexiqaSource =
        prevLexiqa && prevLexiqa.source && size(prevLexiqa.source)
      const lexiqaChanged =
        prevLexiqaSource &&
        currentLexiqaSource &&
        !Immutable.fromJS(prevLexiqa.source).equals(
          Immutable.fromJS(lexiqa.source),
        )

      if (
        currentLexiqaSource &&
        (!prevLexiqaSource ||
          lexiqaChanged ||
          !prevActiveDecorators[DraftMatecatConstants.LEXIQA_DECORATOR])
      ) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = true
        changedDecorator = true
        this.addLexiqaDecorator()
      } else if (prevLexiqaSource && !currentLexiqaSource) {
        activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
      }

      // Search
      if (prevProps && prevProps.segment.inSearch) {
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = false
        changedDecorator = true
        this.removeDecorator(DraftMatecatConstants.SEARCH_DECORATOR)
      }
    } else {
      //Search
      if (
        searchParams.source &&
        (!prevProps || // was not mounted
          !prevProps.segment.inSearch || //Before was not active
          (prevProps.segment.inSearch &&
            !Immutable.fromJS(prevProps.segment.searchParams).equals(
              Immutable.fromJS(searchParams),
            )) || //Before was active but some params change
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearch !== currentInSearch) || //Before was the current
          (prevProps.segment.inSearch &&
            prevProps.segment.currentInSearchIndex !== currentInSearchIndex))
      ) {
        //There are more occurrences and the current change
        // Cleanup all decorators
        this.removeDecorator()
        ;(activeDecorators[DraftMatecatConstants.LEXIQA_DECORATOR] = false),
          (activeDecorators[DraftMatecatConstants.GLOSSARY_DECORATOR] = false),
          (activeDecorators[DraftMatecatConstants.QA_GLOSSARY_DECORATOR] =
            false),
          this.addSearchDecorator()
        activeDecorators[DraftMatecatConstants.SEARCH_DECORATOR] = true
        changedDecorator = true
      }
    }

    if (changedDecorator) {
      const decorator = new CompositeDecorator(this.decoratorsStructure)
      this.setState({
        editorState: EditorState.set(editorState, {decorator}),
        activeDecorators,
      })
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.CLOSE_SPLIT_SEGMENT,
      this.endSplitMode,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_TAGGED,
      this.setTaggedSource,
    )
    SegmentStore.addListener(
      SegmentConstants.REFRESH_TAG_MAP,
      this.refreshTagMap,
    )
    this.$source = $(this.source)
    setTimeout(() => {
      this.checkDecorators()
      this.updateSourceInStore()
    })
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.CLOSE_SPLIT_SEGMENT,
      this.endSplitMode,
    )
    SegmentStore.removeListener(
      SegmentConstants.REFRESH_TAG_MAP,
      this.refreshTagMap,
    )
  }

  componentDidUpdate(prevProps, prevState) {
    this.checkDecorators(prevProps)
    // Check if splitMode
    if (!prevProps.segment.openSplit && this.props.segment.openSplit) {
      // if segment splitted, rebuild its original content
      if (this.props.segment.splitted) {
        let segmentsSplit = this.props.segment.split_group
        let sourceHtml = ''
        // join splitted segment content
        segmentsSplit.forEach((sid, index) => {
          let segment = SegmentStore.getSegmentByIdToJS(sid)
          if (sid === this.props.segment.sid) {
            // if splitted wrap inside highlight span
            //sourceHtml += `##$_SPLITSTART$##${segment.segment}##$_SPLITEND$##`
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
        //const decorator = new CompoundDecorator(this.decoratorsStructure);
        const decorator = new CompositeDecorator(this.decoratorsStructure)
        const plainEditorState = EditorState.createEmpty(decorator)
        // add the content
        const contentEncoded = DraftMatecatUtils.encodeContent(
          plainEditorState,
          sourceHtml,
        )
        const {editorState: editorStateSplitGroup} = contentEncoded
        // update current editorState
        this.setState({editorState: editorStateSplitGroup})
      }
    }

    if (prevState.editorState !== this.state.editorState) {
      const {editorState} = this.state

      const entitiesSelected = getEntitiesSelected(editorState)
      SegmentActions.focusTags(entitiesSelected)
    }
  }

  refreshTagMap = () => {
    const translation = this.props.segment.segment

    // If GuessTag enabled, clean string from tag
    const cleanSource = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
      ? DraftMatecatUtils.removeTagsFromText(translation)
      : translation
    // New EditorState with translation
    const contentEncoded = DraftMatecatUtils.encodeContent(
      this.state.editorState,
      cleanSource,
    )
    const {editorState, tagRange} = contentEncoded

    flushSync(() => this.setState({editorState, tagRange}))

    this.updateSourceInStore()
  }

  allowHTML(string) {
    return {__html: string}
  }

  onChange = (editorState) => {
    const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
    if (!entityKey) {
      setTimeout(() => {
        SegmentActions.highlightTags()
      })
    }
    this.setState({
      editorState,
    })
  }

  preventEdit = () => 'handled'

  getSelectedWords = () =>
    DraftMatecatUtils.getSelectedTextWithoutEntities(
      this.state.editorState,
    ).reduce((acc, {value}) => `${acc}${value}`, '')

  isValidPhraseToAiAssistant = ({
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

  helpAiAssistant = () => {
    if (this.delayAiAssistant) clearTimeout(this.delayAiAssistant)

    const isOpenAiEnabled =
      Boolean(config.isOpenAiEnabled) && SegmentUtils.isAiAssistantAuto()

    if (isOpenAiEnabled) {
      this.delayAiAssistant = setTimeout(() => {
        const {segment} = this.context
        const value = this.getSelectedWords()

        const isValid = this.isValidPhraseToAiAssistant({phrase: value})

        if (isValid) {
          SegmentActions.helpAiAssistant({
            sid: segment.sid,
            value,
          })
        }
      }, 200)
    }
  }

  render() {
    const {segment} = this.context
    const {editorState} = this.state
    const {
      onChange,
      copyFragment,
      onBlurEvent,
      dragFragment,
      addSplitTag,
      splitSegmentNew,
      preventEdit,
      getSelectedWords,
      isValidPhraseToAiAssistant,
    } = this
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
            this.setState({
              isShowingOptionsToolbar: !this.editor._latestEditorState
                .getSelection()
                .isCollapsed(),
            })

            this.helpAiAssistant()
          },
          onKeyUp: (event) => {
            if (
              event.key === 'ArrowLeft' ||
              event.key === 'ArrowRight' ||
              event.key === 'ArrowUp' ||
              event.key === 'ArrowDown'
            ) {
              this.setState({
                isShowingOptionsToolbar: !this.editor._latestEditorState
                  .getSelection()
                  .isCollapsed(),
              })

              this.helpAiAssistant()
            }
          },
        }
      : {
          onClick: () => addSplitTag(),
          onBlur: onBlurEvent,
        }

    const isEnabledAiAssistantButton = isValidPhraseToAiAssistant({
      phrase: getSelectedWords(),
    })

    const optionsToolbar = this.state.isShowingOptionsToolbar && (
      <ul className="optionsToolbar">
        {Boolean(config.isOpenAiEnabled) &&
          !SegmentUtils.isAiAssistantAuto() && (
            <li
              title={
                isEnabledAiAssistantButton
                  ? 'See the meaning of the highlighted text in this context'
                  : "Your selection is over the AI assistant's limit of 3 words, 6 Chinese characters or 10 Japanese characters, please reduce it."
              }
              className={!isEnabledAiAssistantButton ? 'disabled' : ''}
              onMouseDown={() => {
                if (isEnabledAiAssistantButton) {
                  SegmentActions.helpAiAssistant({
                    sid: segment.sid,
                    value: getSelectedWords(),
                  })
                }
              }}
            >
              <Assistant />
            </li>
          )}
        <li
          title="Click to add the highlighted text to the glossary"
          onMouseDown={() => {
            SegmentActions.openGlossaryFormPrefill({
              sid: segment.sid,
              [TERM_FORM_FIELDS.ORIGINAL_TERM]: getSelectedWords(),
            })
          }}
        >
          <Education />
        </li>
      </ul>
    )

    // Standard editor
    const editorHtml = (
      <div
        ref={(source) => (this.source = source)}
        className={`source item`}
        tabIndex={0}
        id={'segment-' + segment.sid + '-source'}
        data-original={this.originalSource}
        {...handlers}
      >
        <UseHotKeysComponent
          shortcut={
            Shortcuts.cattol.events.searchInConcordance.keystrokes[
              Shortcuts.shortCutsKeyType
            ]
          }
          callback={this.openConcordance}
        />
        <Editor
          editorState={editorState}
          onChange={onChange}
          onCut={preventEdit}
          ref={(el) => (this.editor = el)}
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
      <div
        className="splitContainer"
        ref={(splitContainer) => (this.splitContainer = splitContainer)}
      >
        {editorHtml}
        <div className="splitBar">
          <div className="buttons">
            <a
              className="ui button cancel-button cancel btn-cancel"
              onClick={() => SegmentActions.closeSplitSegment()}
            >
              Cancel
            </a>
            <a
              className={`ui primary button done btn-ok pull-right ${
                this.splitPoint ? '' : 'disabled'
              }`}
              onClick={() => splitSegmentNew()}
            >
              {' '}
              Confirm{' '}
            </a>
          </div>
          {!!this.splitPoint && (
            <div className="splitNum pull-right">
              Split in <span className="num">{this.splitPoint}</span> segment
              <span className="plural" />
            </div>
          )}
        </div>
      </div>
    ) : (
      editorHtml
    )
  }

  disableDecorator = (editorState, decoratorName) => {
    remove(
      this.decoratorsStructure,
      (decorator) => decorator.name === decoratorName,
    )
    //const decorator = new CompoundDecorator(this.decoratorsStructure);
    const decorator = new CompositeDecorator(this.decoratorsStructure)
    return EditorState.set(editorState, {decorator})
  }

  removeDecorator = (decoratorName) => {
    if (!decoratorName) {
      // All decorators except tags
      remove(
        this.decoratorsStructure,
        (decorator) => decorator.name !== DraftMatecatConstants.TAGS_DECORATOR,
      )
    } else {
      remove(
        this.decoratorsStructure,
        (decorator) => decorator.name === decoratorName,
      )
    }
  }

  insertTagAtSelection = (tagName) => {
    const {editorState} = this.state
    const customTag = DraftMatecatUtils.structFromName(tagName)
    // If tag creation has failed, return
    if (!customTag) return
    // remove lexiqa to avoid insertion error
    this.removeDecorator(DraftMatecatConstants.LEXIQA_DECORATOR)
    this.removeDecorator(DraftMatecatConstants.SPLIT_DECORATOR)
    const decorator = new CompositeDecorator(this.decoratorsStructure)
    let newEditorState = EditorState.set(editorState, {decorator})
    newEditorState = DraftMatecatUtils.insertEntityAtSelection(
      newEditorState,
      customTag,
    )
    this.setState({editorState: newEditorState})
  }

  addSplitTag = () => {
    // Check chars are selected
    const selection = window.getSelection()
    if (selection.anchorNode) {
      const {startOffset = 0, endOffset = 0} = selection?.getRangeAt(0)
      if (endOffset - startOffset > 0) {
        selection?.removeAllRanges()
        return
      }
    }

    this.insertTagAtSelection('splitPoint')
    this.updateSplitNumberNew(1)
  }

  updateSplitNumberNew = (step) => {
    if (this.props.segment.splitted) return
    this.splitPoint += step
  }

  splitSegmentNew = (split) => {
    const {editorState} = this.state
    let {decodedSegment: text} = DraftMatecatUtils.decodeSegment(editorState)
    // Prepare text for backend
    text = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    SegmentActions.splitSegment(this.props.segment.original_sid, text, split)
  }

  endSplitMode = () => {
    const {editorStateBeforeSplit} = this.state
    const {segment} = this.context
    this.splitPoint = segment.split_group ? segment.split_group.length - 1 : 0
    // TODO: why so much calls endSplitMode??
    if (segment.openSplit) {
      this.setState({
        editorState: editorStateBeforeSplit,
      })
    }
  }

  onBlurEvent = () => {
    setTimeout(() => {
      SegmentActions.highlightTags()
      SegmentActions.focusTags([])
    })

    this.setState({
      isShowingOptionsToolbar: false,
    })
  }

  onEntityClick = (start, end) => {
    const {editorState} = this.state
    const {segment} = this.context
    const {isSplitPoint} = this
    try {
      // Get latest selection
      let newSelection = this.editor._latestEditorState.getSelection()

      const currentBlockText = this.editor._latestEditorState
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
      let newEditorState = EditorState.forceSelection(editorState, newSelection)
      const contentState = newEditorState.getCurrentContent()
      // remove split tag
      if (segment.openSplit && isSplitPoint(contentState, newSelection)) {
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
        this.updateSplitNumberNew(-1)
        newEditorState = EditorState.set(newEditorState, {
          currentContent: contentStateWithoutSplitPoint,
        })
      }
      // update editorState
      this.setState({editorState: newEditorState})
    } catch (e) {
      console.log(e)
    }
  }

  isSplitPoint = (contentState, selection) => {
    const anchorKey = selection.getAnchorKey()
    const anchorBlock = contentState.getBlockForKey(anchorKey)
    const anchorOffset = selection.getAnchorOffset() + 1
    const anchorEntityKey = anchorBlock.getEntityAt(anchorOffset)
    const entityInstance = contentState.getEntity(anchorEntityKey)
    const entityData = entityInstance.getData()
    const tagName = entityData ? entityData.name : ''
    return getSplitPointTag().includes(tagName)
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
        .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')

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

  dragFragment = (e) => {
    const {editorState} = this.state
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
    } = this.context
    const {tagRange, editorState} = this.state
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

export default SegmentSource
