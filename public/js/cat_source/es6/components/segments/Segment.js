import {forEach, isUndefined} from 'lodash'
import Immutable from 'immutable'
import React from 'react'
import {union} from 'lodash/array'

import SegmentCommentsContainer from './SegmentCommentsContainer'
import SegmentsCommentsIcon from './SegmentsCommentsIcon'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentHeader from './SegmentHeader'
import SegmentFooter from './SegmentFooter'
import ReviewExtendedPanel from '../review_extended/ReviewExtendedPanel'
import SegmentUtils from '../../utils/segmentUtils'
import SegmentFilter from '../header/cattol/segment_filter/segment_filter'
import SegmentFilterUtils from '../header/cattol/segment_filter/segment_filter'
import Speech2Text from '../../utils/speech2text'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import SegmentBody from './SegmentBody'
import TranslationIssuesSideButton from '../review/TranslationIssuesSideButton'
import ModalsActions from '../../actions/ModalsActions'
import {SegmentContext} from '../segments/SegmentContext'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolStore from '../../stores/CatToolStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import CommentsStore from '../../stores/CommentsStore'
import {SEGMENTS_STATUS} from '../../constants/Constants'

class Segment extends React.Component {
  constructor(props) {
    super(props)

    this.createSegmentClasses = this.createSegmentClasses.bind(this)
    this.hightlightEditarea = this.hightlightEditarea.bind(this)
    this.addClass = this.addClass.bind(this)
    this.removeClass = this.removeClass.bind(this)
    this.setAsAutopropagated = this.setAsAutopropagated.bind(this)
    this.setSegmentStatus = this.setSegmentStatus.bind(this)
    this.handleChangeBulk = this.handleChangeBulk.bind(this)
    this.openSegment = this.openSegment.bind(this)
    this.openSegmentFromAction = this.openSegmentFromAction.bind(this)
    this.checkIfCanOpenSegment = this.checkIfCanOpenSegment.bind(this)
    this.handleKeyDown = this.handleKeyDown.bind(this)
    this.forceUpdateSegment = this.forceUpdateSegment.bind(this)
    this.clientReconnection = this.clientReconnection.bind(this)

    let readonly = UI.isReadonlySegment(this.props.segment)
    this.secondPassLocked =
      this.props.segment.status?.toUpperCase() === SEGMENTS_STATUS.APPROVED2 &&
      this.props.segment.revision_number === 2 &&
      config.revisionNumber !== 2
    this.state = {
      segment_classes: [],
      autopropagated: this.props.segment.autopropagated_from != 0,
      unlocked: SegmentUtils.isUnlockedSegment(this.props.segment),
      readonly: readonly,
      inBulk: false,
      tagProjectionEnabled:
        this.props.guessTagActive &&
        (this.props.segment.status.toLowerCase() === 'draft' ||
          this.props.segment.status.toLowerCase() === 'new') &&
        !DraftMatecatUtils.checkXliffTagsInText(
          this.props.segment.translation,
        ) &&
        DraftMatecatUtils.removeTagsFromText(this.props.segment.segment) !== '',
      selectedTextObj: null,
      showActions: false,
    }
    this.timeoutScroll
  }

  checkOpenSegmentComment() {
    if (
      CommentsStore.db.getCommentsCountBySegment &&
      UI.currentSegmentId === this.props.segment.sid
    ) {
      const comments_obj = CommentsStore.db.getCommentsCountBySegment(
        this.props.segment.sid,
      )
      const panelClosed =
        localStorage.getItem(SegmentActions.localStorageCommentsClosed) ===
        'true'
      if (comments_obj.active > 0 && !panelClosed) {
        SegmentActions.openSegmentComment(this.props.segment.sid)
        SegmentActions.scrollToSegment(this.props.segment.sid)
      }
    }
  }

  openSegment(wasOriginatedFromBrowserHistory) {
    if (!this.$section.length) return
    if (!this.checkIfCanOpenSegment()) {
      const progress = CatToolStore.getProgress()
      if (progress && progress.raw.translated === 0) {
        this.alertNoTranslatedSegments()
      } else {
        this.alertNotTranslatedYet(this.props.segment.sid)
      }
    } else {
      if (this.props.segment.translation.length !== 0) {
        SegmentActions.getSegmentsQa(this.props.segment)
      }

      // TODO Remove this block
      /**************************/
      //From EditAreaClick
      if (UI.warningStopped) {
        UI.warningStopped = false
        UI.checkWarnings(false)
      }
      // start old cache
      UI.cacheObjects(this.$section)
      //end old cache

      UI.evalNextSegment()

      $('html').trigger('open') // used by ui.review to open tab Revise in the footer next-unapproved

      //Used by Segment Filter, Comments, Footer, Review extended
      setTimeout(() => {
        const segmentId = this.props.segment.original_sid
        //Segment Filter
        if (SegmentFilterUtils.enabled() && SegmentFilterUtils.filtering()) {
          SegmentFilterUtils.setStoredState({
            lastSegmentId: segmentId,
          })
        }
        //Review
        if (config.isReview) {
          const panelClosed =
            localStorage.getItem(
              SegmentActions.localStorageReviewPanelClosed,
            ) === 'true'
          if (!panelClosed) {
            SegmentActions.openIssuesPanel({sid: segmentId}, false)
          }
          SegmentActions.getSegmentVersionsIssues(segmentId)
        }
      })
      this.checkOpenSegmentComment()

      /************/
      UI.editStart = new Date()
      if (this.props.clientConnected) {
        SegmentActions.getGlossaryForSegment({
          sid: this.props.segment.sid,
          fid: this.props.fid,
          text: this.props.segment.segment,
        })
      }

      const hashUrl = document.location.pathname + '#' + this.props.segment.sid
      if (wasOriginatedFromBrowserHistory) {
        history.replaceState(null, null, hashUrl)
      } else {
        history.pushState(null, null, hashUrl)
      }
      var historyChangeStateEvent = new Event('historyChangeState')
      window.dispatchEvent(historyChangeStateEvent)

      // Update document title with hash segment id
      document.title = `${document.title?.split('#')[0]} #${
        this.props.segment.sid
      }`
    }
  }

  alertNotTranslatedYet = (sid) => {
    setTimeout(() =>
      ModalsActions.showModalComponent(ConfirmMessageModal, {
        cancelText: 'Close',
        successCallback: () => SegmentActions.gotoNextTranslatedSegment(sid),
        successText: 'Open next translated segment',
        text: 'This segment is not translated yet.<br /> Only translated segments can be revised.',
      }),
    )
  }

  alertNoTranslatedSegments = () => {
    var props = {
      text: 'There are no translated segments to revise in this job.',
      successText: 'Ok',
      successCallback: function () {
        ModalsActions.onCloseModal()
      },
    }
    setTimeout(() =>
      ModalsActions.showModalComponent(ConfirmMessageModal, props, 'Warning'),
    )
  }

  openSegmentFromAction(sid, wasOriginatedFromBrowserHistory) {
    sid = sid + ''
    if (
      (sid === this.props.segment.sid ||
        (this.props.segment.original_sid === sid &&
          this.props.segment.firstOfSplit)) &&
      !this.props.segment.opened
    ) {
      this.openSegment(wasOriginatedFromBrowserHistory)
    }
  }

  createSegmentClasses() {
    let classes = []
    let splitGroup = this.props.segment.split_group || []
    let readonly = this.state.readonly
    if (readonly) {
      classes.push('readonly')
    }

    if (
      (SegmentUtils.isIceSegment(this.props.segment) && !readonly) ||
      this.secondPassLocked
    ) {
      if (this.props.segment.unlocked) {
        classes.push('ice-unlocked')
      } else {
        classes.push('readonly')
        classes.push('ice-locked')
      }
    }

    if (this.props.segment.status) {
      classes.push('status-' + this.props.segment.status.toLowerCase())
    } else {
      classes.push('status-new')
    }

    if (this.props.segment.sid == splitGroup[0]) {
      classes.push('splitStart')
    } else if (this.props.segment.sid == splitGroup[splitGroup.length - 1]) {
      classes.push('splitEnd')
    } else if (splitGroup.length) {
      classes.push('splitInner')
    }
    if (this.state.tagProjectionEnabled && !this.props.segment.tagged) {
      classes.push('enableTP')
      this.dataAttrTagged = 'nottagged'
    } else {
      this.dataAttrTagged = 'tagged'
    }
    if (this.props.segment.edit_area_locked) {
      classes.push('editAreaLocked')
    }
    if (this.props.segment.inBulk) {
      classes.push('segment-selected-inBulk')
    }
    if (this.props.segment.muted) {
      classes.push('muted')
    }
    if (this.props.segment.opened && this.checkIfCanOpenSegment()) {
      classes.push('editor')
      classes.push('opened')
      classes.push('shadow-1')
    }
    if (
      this.props.segment.modified ||
      this.props.segment.autopropagated_from !== '0'
    ) {
      classes.push('modified')
    }
    if (this.props.sideOpen) {
      classes.push('slide-right')
    }
    if (this.props.segment.openSplit) {
      classes.push('split-action')
    }

    if (this.props.segment.selected) {
      classes.push('segment-selected')
    }
    return classes
  }

  hightlightEditarea(sid) {
    if (this.props.segment.sid == sid) {
      /*  TODO REMOVE THIS CODE
       *  The segment must know about his classes
       */
      let classes = this.state.segment_classes.slice()
      if (classes.indexOf('modified')) {
        classes.push('modified')
        this.setState({
          segment_classes: classes,
        })
      }
    }
  }

  addClass(sid, newClass) {
    if (
      this.props.segment.sid == sid ||
      sid === -1 ||
      sid.split('-')[0] == this.props.segment.sid
    ) {
      let classes = this.state.segment_classes.slice()
      if (newClass.indexOf(' ') > 0) {
        let classesSplit = newClass.split(' ')
        forEach(classesSplit, function (item) {
          if (classes.indexOf(item) < 0) {
            classes.push(item)
          }
        })
      } else {
        if (classes.indexOf(newClass) < 0) {
          classes.push(newClass)
        }
      }
      this.setState({
        segment_classes: classes,
      })
    }
  }

  removeClass(sid, className) {
    if (
      this.props.segment.sid == sid ||
      sid === -1 ||
      sid.indexOf(this.props.segment.sid) !== -1
    ) {
      let classes = this.state.segment_classes.slice()
      let removeFn = function (item) {
        let index = classes.indexOf(item)
        if (index > -1) {
          classes.splice(index, 1)
        }
      }
      if (className.indexOf(' ') > 0) {
        let classesSplit = className.split(' ')
        forEach(classesSplit, function (item) {
          removeFn(item)
        })
      } else {
        removeFn(className)
      }
      this.setState({
        segment_classes: classes,
      })
    }
  }

  setAsAutopropagated(sid, propagation) {
    if (this.props.segment.sid == sid) {
      this.setState({
        autopropagated: propagation,
      })
    }
  }
  setSegmentStatus(sid, status) {
    if (this.props.segment.sid == sid) {
      let classes = this.state.segment_classes.slice(0)
      let index = classes.findIndex(function (item) {
        return item.indexOf('status-') > -1
      })

      if (index >= 0) {
        classes.splice(index, 1)
      }

      this.setState({
        segment_classes: classes,
        status: status,
      })
    }
  }
  checkSegmentStatus(classes) {
    if (classes.length === 0) return classes
    // TODO: remove this
    //To fix a problem: sometimes the section segment has two different status
    let statusMatches = classes.join(' ').match(/status-/g)
    if (statusMatches && statusMatches.length > 1) {
      let index = classes.findIndex(function (item) {
        return item.indexOf('status-new') > -1
      })

      if (index >= 0) {
        classes.splice(index, 1)
      }
    }
    return classes
  }
  isSplitted() {
    return !isUndefined(this.props.segment.split_group)
  }

  isFirstOfSplit() {
    return (
      !isUndefined(this.props.segment.split_group) &&
      this.props.segment.split_group.indexOf(this.props.segment.sid) === 0
    )
  }

  getTranslationIssues() {
    if (
      ((this.props.sideOpen &&
        (!this.props.segment.opened || !this.props.segment.openIssues)) ||
        !this.props.sideOpen) &&
      !(this.props.segment.readonly === 'true') &&
      (!this.isSplitted() || (this.isSplitted() && this.isFirstOfSplit()))
    ) {
      return (
        <TranslationIssuesSideButton
          sid={this.props.segment.sid.split('-')[0]}
          segment={this.props.segment}
          open={this.props.segment.openIssues}
        />
      )
    }
    return null
  }

  lockUnlockSegment(event) {
    event.preventDefault()
    event.stopPropagation()
    if (
      !this.props.segment.unlocked &&
      config.revisionNumber !== 2 &&
      this.props.segment.revision_number === 2
    ) {
      var props = {
        text: 'You are about to edit a segment that has been approved in the 2nd pass review. The project owner and 2nd pass reviser will be notified.',
        successText: 'Ok',
        successCallback: function () {
          ModalsActions.onCloseModal()
        },
      }
      ModalsActions.showModalComponent(
        ConfirmMessageModal,
        props,
        'Modify locked and approved segment ',
      )
    }
    SegmentActions.setSegmentLocked(
      this.props.segment,
      this.props.fid,
      !this.props.segment.unlocked,
    )
  }

  checkSegmentClasses() {
    let classes = this.state.segment_classes.slice()
    classes = union(classes, this.createSegmentClasses())
    classes = this.checkSegmentStatus(classes)
    if (classes.indexOf('muted') > -1 && classes.indexOf('editor') > -1) {
      let indexEditor = classes.indexOf('editor')
      classes.splice(indexEditor, 1)
      let indexOpened = classes.indexOf('opened')
      classes.splice(indexOpened, 1)
    }
    return classes
  }

  handleChangeBulk(event) {
    event.stopPropagation()
    if (event.shiftKey) {
      this.props.setBulkSelection(this.props.segment.sid, this.props.fid)
    } else {
      SegmentActions.toggleSegmentOnBulk(this.props.segment.sid, this.props.fid)
      this.props.setLastSelectedSegment(this.props.segment.sid, this.props.fid)
    }
  }

  openRevisionPanel = (data) => {
    if (
      parseInt(data.sid) === parseInt(this.props.segment.sid) &&
      (!SegmentUtils.isIceSegment(this.props.segment) ||
        (SegmentUtils.isIceSegment(this.props.segment) &&
          this.props.segment.unlocked))
    ) {
      this.setState({
        selectedTextObj: data.selection,
      })
    } else {
      this.setState({
        selectedTextObj: null,
      })
    }
  }
  removeSelection = () => {
    var selection = document.getSelection()
    if (this.section.contains(selection.anchorNode)) {
      selection.removeAllRanges()
    }
    this.setState({
      selectedTextObj: null,
    })
  }

  checkIfCanOpenSegment() {
    return (
      (this.props.isReview &&
        !(this.props.segment.status.toUpperCase() == SEGMENTS_STATUS.NEW) &&
        !(this.props.segment.status.toUpperCase() == SEGMENTS_STATUS.DRAFT)) ||
      !this.props.isReview
    )
  }
  onClickEvent = () => {
    if (
      this.state.readonly ||
      (!this.props.segment.unlocked &&
        SegmentUtils.isIceSegment(this.props.segment))
    ) {
      UI.handleClickOnReadOnly($(this.section).closest('section'))
    } else if (this.props.segment.muted) {
      return
    } else if (!this.props.segment.opened) {
      this.openSegment()
      if (this.checkIfCanOpenSegment())
        SegmentActions.setOpenSegment(this.props.segment.sid, this.props.fid)
    }
  }

  handleKeyDown(event) {
    if (event.code === 'Escape' && !config.targetIsCJK) {
      if (
        this.props.segment.opened &&
        !this.props.segment.openComments &&
        !this.props.segment.openIssues &&
        !UI.body.hasClass('search-open')
      ) {
        if (!this.props.segment.openSplit) {
          SegmentActions.closeSegment(this.props.segment.sid)
        } else {
          SegmentActions.closeSplitSegment()
        }
      } else if (this.props.segment.openComments) {
        SegmentActions.closeSegmentComment()
      } else if (this.props.segment.openIssues) {
        SegmentActions.closeIssuesPanel()
      }
    }
  }

  clientReconnection() {
    if (this.props.segment.opened) {
      SegmentActions.getGlossaryForSegment({
        sid: this.props.segment.sid,
        fid: this.props.fid,
        text: this.props.segment.segment,
      })
      SegmentActions.getContributions(
        this.props.segment.sid,
        this.props.multiMatchLangs,
      )
    }
  }

  forceUpdateSegment(sid) {
    if (this.props.segment.sid === sid) {
      this.forceUpdate()
    }
  }

  allowHTML(string) {
    return {__html: string}
  }

  componentDidMount() {
    this.$section = $(this.section)
    document.addEventListener('keydown', this.handleKeyDown)
    SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass)
    SegmentStore.addListener(
      SegmentConstants.REMOVE_SEGMENT_CLASS,
      this.removeClass,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_PROPAGATION,
      this.setAsAutopropagated,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_STATUS,
      this.setSegmentStatus,
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_SEGMENT,
      this.openSegmentFromAction,
    )
    SegmentStore.addListener(
      SegmentConstants.FORCE_UPDATE_SEGMENT,
      this.forceUpdateSegment,
    )
    CatToolStore.addListener(
      CatToolConstants.CLIENT_RECONNECTION,
      this.clientReconnection,
    )

    //Review
    SegmentStore.addListener(
      SegmentConstants.OPEN_ISSUES_PANEL,
      this.openRevisionPanel,
    )
    if (this.props.segment.opened) {
      setTimeout(() => {
        this.openSegment()
      })
      setTimeout(() => {
        UI.setCurrentSegment()
      }, 0)
    }
  }

  componentWillUnmount() {
    document.removeEventListener('keydown', this.handleKeyDown)
    SegmentStore.removeListener(
      SegmentConstants.ADD_SEGMENT_CLASS,
      this.addClass,
    )
    SegmentStore.removeListener(
      SegmentConstants.REMOVE_SEGMENT_CLASS,
      this.removeClass,
    )
    SegmentStore.removeListener(
      SegmentConstants.SET_SEGMENT_PROPAGATION,
      this.setAsAutopropagated,
    )
    SegmentStore.removeListener(
      SegmentConstants.SET_SEGMENT_STATUS,
      this.setSegmentStatus,
    )
    SegmentStore.removeListener(
      SegmentConstants.OPEN_SEGMENT,
      this.openSegmentFromAction,
    )
    SegmentStore.removeListener(
      SegmentConstants.FORCE_UPDATE_SEGMENT,
      this.forceUpdateSegment,
    )

    CatToolStore.removeListener(
      CatToolConstants.CLIENT_RECONNECTION,
      this.clientReconnection,
    )

    //Review
    SegmentStore.removeListener(
      SegmentConstants.OPEN_ISSUES_PANEL,
      this.openRevisionPanel,
    )
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      !nextProps.segImmutable.equals(this.props.segImmutable) ||
      !Immutable.fromJS(nextState.segment_classes).equals(
        Immutable.fromJS(this.state.segment_classes),
      ) ||
      nextState.autopropagated !== this.state.autopropagated ||
      nextState.readonly !== this.state.readonly ||
      nextState.selectedTextObj !== this.state.selectedTextObj ||
      nextProps.sideOpen !== this.props.sideOpen ||
      nextState.showActions !== this.state.showActions ||
      nextProps.clientConnected !== this.props.clientConnected ||
      nextProps.speechToTextActive !== this.props.speechToTextActive
    )
  }

  getSnapshotBeforeUpdate(prevProps) {
    if (!prevProps.segment.opened && this.props.segment.opened) {
      this.timeoutScroll = setTimeout(() => {
        SegmentActions.scrollToSegment(this.props.segment.sid)
      }, 200)
      setTimeout(() => {
        UI.setCurrentSegment()
      }, 0)
      setTimeout(() => {
        if (
          this.props.segment.opened &&
          !config.isReview &&
          !SegmentStore.segmentHasIssues(this.props.segment)
        ) {
          SegmentActions.closeSegmentIssuePanel(this.props.segment.sid)
        }
        if (this.props.segment.opened && !this.props.segment.openComments) {
          SegmentActions.closeSegmentComment(this.props.segment.sid)
        }
      })
    } else if (prevProps.segment.opened && !this.props.segment.opened) {
      clearTimeout(this.timeoutScroll)
      setTimeout(() => {
        SegmentActions.saveSegmentBeforeClose(this.props.segment)
      })
    }
    if (
      Speech2Text.enabled() &&
      ((!prevProps.speechToTextActive && this.props.speechToTextActive) ||
        (!prevProps.segment.opened && this.props.segment.opened))
    ) {
      setTimeout(() => Speech2Text.enableMicrophone(this.$section))
    }
    return null
  }
  componentDidUpdate() {}

  render() {
    let job_marker = ''
    let timeToEdit = ''

    let readonly = this.state.readonly
    let showLockIcon =
      SegmentUtils.isIceSegment(this.props.segment) || this.secondPassLocked
    let segment_classes = this.checkSegmentClasses()

    let split_group = this.props.segment.split_group || []
    let autoPropagable = this.props.segment.repetitions_in_chunk !== '1'
    let originalId = this.props.segment.original_sid

    if (this.props.timeToEdit) {
      this.segment_edit_min = this.props.segment.parsed_time_to_edit[1]
      this.segment_edit_sec = this.props.segment.parsed_time_to_edit[2]
    }

    if (this.props.timeToEdit) {
      timeToEdit =
        <span className="edit-min">{this.segment_edit_min}</span> +
        'm' +
        <span className="edit-sec">{this.segment_edit_sec}</span> +
        's'
    }

    let translationIssues = this.getTranslationIssues()
    let locked =
      !this.props.segment.unlocked &&
      (SegmentUtils.isIceSegment(this.props.segment) || this.secondPassLocked)
    const segmentHasIssues = SegmentStore.segmentHasIssues(this.props.segment)

    const getContextProps = () => {
      const {
        guessTagActive,
        isReview,
        segImmutable,
        segment,
        files,
        speech2textEnabledFn,
        multiMatchLangs,
      } = this.props
      return {
        enableTagProjection: guessTagActive && !this.props.segment.tagged,
        isReview,
        segImmutable,
        segment,
        files,
        speech2textEnabledFn,
        readonly: this.state.readonly,
        locked,
        removeSelection: this.removeSelection.bind(this),
        openSegment: this.openSegment,
        clientConnected: this.props.clientConnected,
        clientId: this.props.clientId,
        multiMatchLangs,
      }
    }

    return (
      <SegmentContext.Provider value={{...getContextProps()}}>
        <section
          ref={(section) => (this.section = section)}
          id={'segment-' + this.props.segment.sid}
          className={
            segment_classes.join(' ') +
            ` source-${config.source_code} target-${config.target_code}`
          }
          data-hash={this.props.segment.segment_hash}
          data-autopropagated={this.state.autopropagated}
          data-propagable={autoPropagable}
          data-split-group={split_group}
          data-split-original-id={originalId}
          data-tagmode="crunched"
          data-tagprojection={this.dataAttrTagged}
          data-fid={this.props.segment.id_file}
          data-modified={this.props.segment.modified}
        >
          <div className="sid" title={this.props.segment.sid}>
            <div className="txt">{this.props.segment.sid}</div>

            {showLockIcon ? (
              !readonly ? (
                this.props.segment.unlocked ? (
                  <div
                    className="ice-locked-icon"
                    onClick={this.lockUnlockSegment.bind(this)}
                  >
                    <button className="unlock-button unlocked icon-unlocked3" />
                  </div>
                ) : (
                  <div
                    className="ice-locked-icon"
                    onClick={this.lockUnlockSegment.bind(this)}
                  >
                    <button className="icon-lock unlock-button locked" />
                  </div>
                )
              ) : null
            ) : null}

            <div className="txt segment-add-inBulk">
              <input
                type="checkbox"
                ref={(node) => (this.bulk = node)}
                checked={this.props.segment.inBulk}
                onClick={this.handleChangeBulk}
              />
            </div>

            {this.props.segment.ice_locked !== '1' &&
            config.splitSegmentEnabled &&
            this.props.segment.opened ? (
              !this.props.segment.openSplit ? (
                <div className="actions">
                  <button
                    className="split"
                    title="Click to split segment"
                    onClick={() =>
                      SegmentActions.openSplitSegment(this.props.segment.sid)
                    }
                  >
                    <i className="icon-split" />
                  </button>
                  <p className="split-shortcut">CTRL + S</p>
                </div>
              ) : (
                <div className="actions">
                  <button
                    className="split cancel"
                    title="Click to close split segment"
                    onClick={() => SegmentActions.closeSplitSegment()}
                  >
                    <i className="icon-split" />
                  </button>
                  {/*<p className="split-shortcut">CTRL + W</p>*/}
                </div>
              )
            ) : null}
          </div>
          {job_marker}

          <div className="body">
            <SegmentHeader
              sid={this.props.segment.sid}
              autopropagated={this.state.autopropagated}
              segmentOpened={this.props.segment.opened}
              repetition={autoPropagable}
              splitted={this.props.segment.splitted}
              saving={this.props.segment.saving}
            />
            <SegmentBody onClick={this.onClickEvent} />
            <div
              className="timetoedit"
              data-raw-time-to-edit={this.props.segment.time_to_edit}
            >
              {timeToEdit}
            </div>
            {SegmentFilter && SegmentFilter.enabled() ? (
              <div className="edit-distance">
                Edit Distance: {this.props.segment.edit_distance}
              </div>
            ) : null}

            {this.props.segment.opened ? <SegmentFooter /> : null}
          </div>

          {/*//!-- TODO: place this element here only if it's not a split --*/}
          <div className="segment-side-buttons">
            {config.comments_enabled &&
            (!this.props.segment.openComments || !this.props.segment.opened) ? (
              <SegmentsCommentsIcon />
            ) : null}

            {this.props.isReview && (
              <div
                data-mount="translation-issues-button"
                className="translation-issues-button"
                data-sid={this.props.segment.sid}
              >
                {translationIssues}
              </div>
            )}
          </div>
          <div className="segment-side-container">
            {config.comments_enabled && this.props.segment.openComments ? (
              <SegmentCommentsContainer />
            ) : null}
            {config.isReview &&
            this.props.segment.openIssues &&
            this.props.segment.opened &&
            (config.isReview || (!config.isReview && segmentHasIssues)) ? (
              <div className="review-balloon-container">
                {!this.props.segment.versions ? null : (
                  <ReviewExtendedPanel
                    segment={this.props.segment}
                    isReview={config.isReview}
                    selectionObj={this.state.selectedTextObj}
                  />
                )}
              </div>
            ) : null}
          </div>
        </section>
      </SegmentContext.Provider>
    )
  }
}

export default Segment
