import React from 'react'
import $ from 'jquery'
import {isEmpty, isUndefined} from 'lodash'

import EditArea from './Editarea'
import CursorUtils from '../../utils/cursorUtils'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import SegmentButtons from './SegmentButtons'
import SegmentWarnings from './SegmentWarnings'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {
  removeTagsFromText,
  textHasTags,
} from './utils/DraftMatecatUtils/tagUtils'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import RemoveTagsIcon from '../../../img/icons/RemoveTagsIcon'
import AddTagsIcon from '../../../img/icons/AddTagsIcon'
import UpperCaseIcon from '../../../img/icons/UpperCaseIcon'
import LowerCaseIcon from '../../../img/icons/LowerCaseIcon'
import CapitalizeIcon from '../../../img/icons/CapitalizeIcon'
import QualityReportIcon from '../../../img/icons/QualityReportIcon'
import ReviseLockIcon from '../../../img/icons/ReviseLockIcon'
import OfflineUtils from '../../utils/offlineUtils'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolStore from '../../stores/CatToolStore'
import {Shortcuts} from '../../utils/shortcuts'
import {UseHotKeysComponent} from '../../hooks/UseHotKeysComponent'

class SegmentTarget extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    this.state = {
      showFormatMenu: false,
      charactersCounter: 0,
      segmentCharacters: 0,
      charactersCounterLimit: undefined,
    }
    this.autoFillTagsInTarget = this.autoFillTagsInTarget.bind(this)
  }

  selectIssueText(event) {
    var selection = document.getSelection()
    var container = $(this.issuesHighlightArea).find('.errorTaggingArea')
    if (this.textSelectedInsideSelectionArea(selection, container)) {
      event.preventDefault()
      event.stopPropagation()
      selection = CursorUtils.getSelectionData(selection, container)
      SegmentActions.openIssuesPanel(
        {sid: this.props.segment.sid, selection: selection},
        true,
      )
      setTimeout(() => {
        SegmentActions.showIssuesMessage(this.props.segment.sid, 2)
      })
    } else {
      this.context.removeSelection()
      setTimeout(() => {
        SegmentActions.showIssuesMessage(this.props.segment.sid, 0)
      })
    }
  }

  textSelectedInsideSelectionArea(selection, container) {
    return (
      container.contents().text().indexOf(selection.focusNode.textContent) >=
        0 &&
      container.contents().text().indexOf(selection.anchorNode.textContent) >=
        0 &&
      selection.toString().length > 0
    )
  }

  lockEditArea(event) {
    event.preventDefault()
    if (!this.props.segment.edit_area_locked) {
      SegmentActions.showIssuesMessage(this.props.segment.sid, 0)
    }
    SegmentActions.lockEditArea(this.props.segment.sid, this.props.segment.fid)
  }

  allowHTML(string) {
    return {__html: string}
  }

  getAllIssues() {
    let issues = []
    if (this.props.segment.versions) {
      this.props.segment.versions.forEach(function (version) {
        if (!isEmpty(version.issues)) {
          issues = issues.concat(version.issues)
        }
      })
    }
    return issues
  }

  removeTagsFromText() {
    const cleanText = removeTagsFromText(this.props.segment.translation)
    SegmentActions.replaceEditAreaTextContent(this.props.segment.sid, cleanText)
  }

  getTargetArea(translation) {
    const {segment} = this.context
    const {showFormatMenu} = this.state
    const {toggleFormatMenu, updateCounter} = this

    var textAreaContainer = ''
    let issues = this.getAllIssues()
    if (this.props.segment.edit_area_locked) {
      const text =
        this.props.segment.versions &&
        this.props.segment.versions[0].translation
          ? this.props.segment.versions[0].translation
          : translation
      let currentTranslationVersion = DraftMatecatUtils.transformTagsToHtml(
        text,
        config.isTargetRTL,
      )
      textAreaContainer = (
        <div
          className="segment-text-area-container"
          data-mount="segment_text_area_container"
        >
          <div
            className="textarea-container"
            onMouseUp={this.selectIssueText.bind(this)}
            ref={(div) => (this.issuesHighlightArea = div)}
          >
            <div
              className="targetarea issuesHighlightArea errorTaggingArea"
              dangerouslySetInnerHTML={this.allowHTML(
                currentTranslationVersion,
              )}
            />
          </div>
          <div className="toolbar">
            {config.isReview ? (
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                onClick={this.lockEditArea.bind(this)}
                title="Highlight text and assign an issue to the selected text."
                className="revise-lock-editArea-active"
              >
                <ReviseLockIcon />
              </Button>
            ) : null}
          </div>
        </div>
      )
    } else {
      let tagCopyButton,
        removeTagsButton,
        s2tMicro = ''

      //Speeche2Text
      var s2t_enabled = this.context.speech2textEnabledFn()
      if (s2t_enabled) {
        s2tMicro = (
          <div
            className="micSpeech"
            title="Activate voice input"
            data-segment-id="{{originalId}}"
          >
            <div className="micBg"></div>
            <div className="micBg2">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                version="1.1"
                width="20"
                height="20"
                viewBox="0 0 20 20"
              >
                <g
                  className="svgMic"
                  transform="matrix(0.05555509,0,0,0.05555509,-3.1790007,-3.1109739)"
                  fill="#737373"
                >
                  <path d="m 290.991,240.991 c 0,26.392 -21.602,47.999 -48.002,47.999 l -11.529,0 c -26.4,0 -48.002,-21.607 -48.002,-47.999 l 0,-136.989 c 0,-26.4 21.602,-48.004 48.002,-48.004 l 11.529,0 c 26.4,0 48.002,21.604 48.002,48.004 l 0,136.989 z" />
                  <path d="m 342.381,209.85 -8.961,0 c -4.932,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,50.26 -37.109,91.001 -87.361,91.001 -50.26,0 -87.109,-40.741 -87.109,-91.001 l 0,-8.008 c 0,-4.927 -4.029,-8.961 -8.961,-8.961 l -8.961,0 c -4.924,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,58.862 40.229,107.625 96.07,116.362 l 0,36.966 -34.412,0 c -4.932,0 -8.961,4.039 -8.961,8.971 l 0,17.922 c 0,4.923 4.029,8.961 8.961,8.961 l 104.688,0 c 4.926,0 8.961,-4.038 8.961,-8.961 l 0,-17.922 c 0,-4.932 -4.035,-8.971 -8.961,-8.971 l -34.43,0 0,-36.966 c 55.889,-8.729 96.32,-57.5 96.32,-116.362 l 0,-8.008 c 0,-4.927 -4.039,-8.961 -8.961,-8.961 z" />
                </g>
              </svg>
            </div>
          </div>
        )
      }
      if (textHasTags(translation)) {
        removeTagsButton = (
          <>
            <UseHotKeysComponent
              shortcut={
                Shortcuts.cattol.events.removeTags.keystrokes[
                  Shortcuts.shortCutsKeyType
                ]
              }
              callback={this.removeTagsFromText.bind(this)}
            />
            <Button
              className="removeAllTags"
              size={BUTTON_SIZE.ICON_SMALL}
              mode={BUTTON_MODE.OUTLINE}
              alt={`Remove all tags (${Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`}
              title={`Remove all tags (${Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`}
              onClick={this.removeTagsFromText.bind(this)}
            >
              <RemoveTagsIcon />
            </Button>
          </>
        )
      }
      if (
        segment.missingTagsInTarget &&
        segment.missingTagsInTarget.length > 0 &&
        this.editArea
      ) {
        tagCopyButton = (
          <>
            <UseHotKeysComponent
              shortcut={
                Shortcuts.cattol.events.addTags.keystrokes[
                  Shortcuts.shortCutsKeyType
                ]
              }
              callback={this.editArea.addMissingSourceTagsToTarget}
            />
            <Button
              size={BUTTON_SIZE.ICON_SMALL}
              mode={BUTTON_MODE.OUTLINE}
              alt={`Copy missing tags from source to target (${Shortcuts.cattol.events.addTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`}
              title={`Copy missing tags from source to target (${Shortcuts.cattol.events.addTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`}
              onClick={this.editArea.addMissingSourceTagsToTarget}
            >
              <AddTagsIcon />
            </Button>
          </>
        )
      }

      const qrLink =
        '/revise-summary/' +
        config.id_job +
        '-' +
        config.password +
        '?revision_type=' +
        (config.revisionNumber ? config.revisionNumber : 1) +
        '&id_segment=' +
        this.props.segment.sid

      //Text Area
      textAreaContainer = (
        <div className="textarea-container">
          <EditArea
            ref={(ref) => (this.editArea = ref)}
            segment={this.props.segment}
            translation={translation}
            toggleFormatMenu={toggleFormatMenu}
            updateCounter={updateCounter}
          />
          {s2tMicro}
          <div className="toolbar">
            {config.isReview ? (
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                onClick={this.lockEditArea.bind(this)}
                title="Highlight text and assign an issue to the selected text."
              >
                <ReviseLockIcon />
              </Button>
            ) : null}
            {issues.length > 0 || config.isReview ? (
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                title="Segment Quality Report."
                target="_blank"
                onClick={() => window.open(qrLink, '_blank')}
              >
                <QualityReportIcon size={16} />
              </Button>
            ) : null}
            {removeTagsButton}
            {tagCopyButton}
            <div
              className="editToolbar"
              style={
                showFormatMenu
                  ? {visibility: 'visible'}
                  : {visibility: 'hidden'}
              }
            >
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                onMouseDown={() => this.editArea.formatSelection('uppercase')}
                title="Uppercase"
              >
                <UpperCaseIcon />
              </Button>
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                onMouseDown={() => this.editArea.formatSelection('lowercase')}
                title="Lowercase"
              >
                <LowerCaseIcon />
              </Button>
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.OUTLINE}
                onMouseDown={() => this.editArea.formatSelection('capitalize')}
                title="Capitalize"
              >
                <CapitalizeIcon />
              </Button>
            </div>
          </div>
        </div>
      )
    }
    return textAreaContainer
  }

  autoFillTagsInTarget(sid) {
    if (isUndefined(sid) || sid === this.props.segment.sid) {
      let newTranslation = DraftMatecatUtils.autoFillTagsInTarget(
        this.props.segment,
      )
      //lock tags and run again getWarnings
      setTimeout(() => {
        SegmentActions.replaceEditAreaTextContent(
          this.props.segment.sid,
          newTranslation,
        )
        SegmentActions.getSegmentsQa(this.props.segment)
      }, 100)
      // TODO: Change code with this (?)
      // this.editArea.addMissingSourceTagsToTarget()
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.FILL_TAGS_IN_TARGET,
      this.autoFillTagsInTarget,
    )
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.FILL_TAGS_IN_TARGET,
      this.autoFillTagsInTarget,
    )
  }

  componentDidUpdate(prevProps, prevState) {
    const charactersCounterLimit = this.props.segment.metadata.find(
      (meta) =>
        meta.id_segment === this.props.segment.sid &&
        meta.meta_key === 'sizeRestriction',
    )?.meta_value

    if (
      charactersCounterLimit &&
      charactersCounterLimit !== prevState.charactersCounterLimit
    ) {
      this.setState({
        charactersCounterLimit,
      })
    }

    // dispatch characterCounter action
    if (
      this.state.charactersCounterLimit !== prevState.charactersCounterLimit ||
      this.state.charactersCounter !== prevState.charactersCounter ||
      this.state.segmentCharacters !== prevState.segmentCharacters
    ) {
      setTimeout(() => {
        SegmentActions.characterCounter({
          sid: this.props.segment.sid,
          counter: this.state.charactersCounter,
          segmentCharacters: this.state.segmentCharacters,
          limit: this.state.charactersCounterLimit,
        })
      })
    }
  }

  render() {
    let buttonsDisabled = false
    let translation = this.props.segment.translation

    if (
      !translation ||
      translation.trim().length === 0 ||
      OfflineUtils.offlineCacheRemaining <= 0
    ) {
      buttonsDisabled = true
    }

    return (
      <div
        className={`target item target-${config.target_code}`}
        id={'segment-' + this.props.segment.sid + '-target'}
        ref={(target) => (this.target = target)}
      >
        {this.getTargetArea(translation)}
        <p className="warnings" />

        <SegmentButtons disabled={buttonsDisabled} {...this.context} />
        {this.props.segment.warnings ? (
          <SegmentWarnings warnings={this.props.segment.warnings} />
        ) : null}
      </div>
    )
  }
  updateCounter = (value) => {
    const {segmentCharacters, unitCharacters} =
      SegmentUtils.getRelativeTransUnitCharactersCounter({
        sid: this.props.segment.sid,
        charactersCounter: value,
        shouldCountTagsAsChars:
          CatToolStore.getCurrentProjectTemplate().characterCounterCountTags,
      })

    this.setState({
      charactersCounter: unitCharacters,
      segmentCharacters,
    })
  }
  toggleFormatMenu = (show) => {
    // Show/Hide Edit Toolbar
    this.setState({
      showFormatMenu: show,
    })
  }
}

export default SegmentTarget
