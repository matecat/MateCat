import React from 'react'
import {isUndefined, isNull} from 'lodash'

import SegmentStore from '../../stores/SegmentStore'
import CatToolStore from '../../stores/CatToolStore'
import SegmentFilter from '../header/cattol/segment_filter/segment_filter'
import SegmentUtils from '../../utils/segmentUtils'
import CattoolConstants from '../../constants/CatToolConstants'
import CommonUtils from '../../utils/commonUtils'
import {SEGMENTS_STATUS} from '../../constants/Constants'
import {
  decodePlaceholdersToPlainText,
  removeTagsFromText,
} from './utils/DraftMatecatUtils/tagUtils'
import SegmentActions from '../../actions/SegmentActions'

class SegmentButton extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      progress: undefined,
    }
    this.updateProgress = this.updateProgress.bind(this)
  }

  updateProgress(stats) {
    this.setState({
      progress: stats,
    })
  }

  trackTranslatedClick() {
    //Track first translate event in the session
    const idProject = config.id_project
    const key = 'first_segment_confirm' + idProject
    if (!sessionStorage.getItem(key)) {
      const event = {
        event: 'first_segment_confirm',
        userStatus: APP.USER.isUserLogged() ? 'loggedUser' : 'notLoggedUser',
        userId:
          APP.USER.isUserLogged() && APP.USER.STORE.user
            ? APP.USER.STORE.user.uid
            : null,
        idProject: parseInt(idProject),
      }
      CommonUtils.dispatchAnalyticsEvents(event)
      sessionStorage.setItem(key, 'true')
    }
  }

  clickOnTranslatedButton(event, gotoUntranslated) {
    this.trackTranslatedClick()
    setTimeout(() =>
      SegmentActions.clickOnTranslatedButton(
        this.props.segment,
        gotoUntranslated,
      ),
    )
  }

  clickOnApprovedButton(event, gotoNexUnapproved) {
    setTimeout(() =>
      SegmentActions.clickOnApprovedButton(
        this.props.segment,
        gotoNexUnapproved,
      ),
    )
  }

  goToNextRepetition(event, status) {
    let target = event.currentTarget
    setTimeout(() => SegmentFilter.goToNextRepetition(target, status))
  }

  goToNextRepetitionGroup(event, status) {
    let target = event.currentTarget
    setTimeout(() => SegmentFilter.goToNextRepetitionGroup(target, status))
  }

  clickOnGuessTags(e) {
    const {segment} = this.props
    const contribution = segment.contributions?.matches
      ? this.props.segment.contributions.matches[0]
      : undefined
    if (contribution && contribution.match === 'MT') {
      const currentTranslation = segment.decodedTranslation
      const contributionText = removeTagsFromText(
        decodePlaceholdersToPlainText(contribution.translation),
      )
      if (currentTranslation === contributionText) {
        SegmentActions.replaceEditAreaTextContent(
          segment.sid,
          contribution.translation,
        )
        SegmentActions.setSegmentAsTagged(segment.sid)
        return
      }
    }
    e.preventDefault()
    $(e.target).addClass('disabled')
    setTimeout(() => UI.startSegmentTagProjection(this.props.segment.sid))
    return false
  }

  getButtons() {
    let html
    if (this.props.isReview) {
      //Revise Default, Extended
      html = this.getReviewButtons()
    } else {
      //Translate
      html = this.getTranslateButtons()
    }
    return html
  }

  getReviewButtons() {
    const classDisable = this.props.disabled ? 'disabled' : ''
    let nextButton, currentButton
    let nextSegment = SegmentStore.getNextSegment(
      this.props.segment.sid,
      this.props.segment.fid,
    )

    let revisionCompleted = false
    if (config.isReview && this.state.progress) {
      revisionCompleted =
        config.revisionNumber === 1
          ? this.state.progress.revision1Completed
          : this.state.progress.revision2Completed
    } else if (this.state.progress) {
      revisionCompleted = this.state.progress.revisionCompleted
    }
    let enableGoToNext =
      !isUndefined(nextSegment) &&
      !revisionCompleted &&
      (([SEGMENTS_STATUS.APPROVED2, SEGMENTS_STATUS.APPROVED].includes(
        nextSegment.status,
      ) &&
        nextSegment.autopropagated_from == 0) || //Approved and propagation confirmed
        (SegmentUtils.isIceSegment(nextSegment) && !nextSegment.unlocked) || //Ice
        nextSegment.status === 'NEW' ||
        nextSegment.status === 'DRAFT')
    const filtering =
      SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open
    const className = config.isReview
      ? 'revise-button-' + config.revisionNumber
      : ''
    enableGoToNext =
      enableGoToNext &&
      (isNull(nextSegment.revision_number) ||
        (!isNull(nextSegment.revision_number) &&
          (nextSegment.revision_number === config.revisionNumber ||
            (nextSegment.revision_number === 2 &&
              config.revisionNumber === 1))) || //Not Same Rev
        (SegmentUtils.isIceSegment(nextSegment) && !nextSegment.unlocked)) // Ice Locked

    nextButton = enableGoToNext ? (
      <li>
        <a
          id={'segment-' + this.props.segment.sid + '-nexttranslated'}
          onClick={(event) => this.clickOnApprovedButton(event, true)}
          className={'btn next-unapproved ' + classDisable + ' ' + className}
          data-segmentid={'segment-' + this.props.segment.sid}
          title="Revise and go to next translated"
        >
          {' '}
          A+>>
        </a>
        <p>
          {UI.isMac ? 'CMD' : 'CTRL'}
          +SHIFT+ENTER
        </p>
      </li>
    ) : null
    currentButton = this.getReviewButton()

    if (filtering) {
      nextButton = null
      var data = SegmentFilter.getStoredState()
      var filterinRepetitions =
        data.reactState && data.reactState.samplingType === 'repetitions'
      if (filterinRepetitions) {
        nextButton = (
          <React.Fragment>
            <li>
              <a
                id={'segment-' + this.props.segment.sid + '-nextrepetition'}
                onClick={(e) => this.goToNextRepetition(e, 'approved')}
                className={
                  'next-review-repetition ui green button ' + className
                }
                data-segmentid={'segment-' + this.props.segment.sid}
                title="Revise and go to next repetition"
              >
                REP &lt;
              </a>
            </li>
            <li>
              <a
                id={
                  'segment-' + this.props.segment.sid + '-nextgrouprepetition'
                }
                onClick={(e) => this.goToNextRepetitionGroup(e, 'approved')}
                className={
                  'next-review-repetition-group ui green button ' + className
                }
                data-segmentid={'segment-' + this.props.segment.sid}
                title="Revise and go to next repetition group"
              >
                REP &lt;&lt;
              </a>
            </li>
          </React.Fragment>
        )
      }
    }
    return (
      <ul
        className="buttons"
        data-mount="main-buttons"
        id={'segment-' + this.props.segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </ul>
    )
  }

  getTranslateButtons() {
    const classDisable = this.props.disabled ? 'disabled' : ''

    let nextButton, currentButton
    const filtering =
      SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open
    let nextSegment = SegmentStore.getNextSegment(
      this.props.segment.sid,
      this.props.segment.fid,
    )
    let translationCompleted =
      this.state.progress && this.state.progress.translationCompleted
    let enableGoToNext =
      !isUndefined(nextSegment) &&
      !translationCompleted &&
      ((nextSegment.status !== 'NEW' &&
        nextSegment.status !== 'DRAFT' &&
        nextSegment.autopropagated_from == 0) ||
        (SegmentUtils.isIceSegment(nextSegment) && !nextSegment.unlocked))
    //TODO Store TP Information in the SegmentsStore
    this.currentSegmentTPEnabled = SegmentUtils.checkCurrentSegmentTPEnabled(
      this.props.segment,
    )
    if (this.currentSegmentTPEnabled) {
      nextButton = ''
      currentButton = (
        <li>
          <a
            id={'segment-' + this.props.segment.sid + '-button-guesstags'}
            onClick={(e) => this.clickOnGuessTags(e)}
            data-segmentid={'segment-' + this.props.segment.sid}
            className={'guesstags ' + classDisable}
          >
            {' '}
            GUESS TAGS
          </a>
          <p>
            {UI.isMac ? 'CMD' : 'CTRL'}
            ENTER
          </p>
        </li>
      )
    } else {
      nextButton = enableGoToNext ? (
        <li>
          <a
            id={'segment-' + this.props.segment.sid + '-nextuntranslated'}
            onClick={(e) => this.clickOnTranslatedButton(e, true)}
            className={'btn next-untranslated ' + classDisable}
            data-segmentid={'segment-' + this.props.segment.sid}
            title="Translate and go to next untranslated"
          >
            {' '}
            T+>>
          </a>
          <p>
            {UI.isMac ? 'CMD' : 'CTRL'}
            +SHIFT+ENTER
          </p>
        </li>
      ) : null
      currentButton = this.getTranslateButton()
    }
    if (filtering) {
      nextButton = null
      var data = SegmentFilter.getStoredState()
      var filterinRepetitions =
        data.reactState && data.reactState.samplingType === 'repetitions'
      if (filterinRepetitions) {
        nextButton = (
          <React.Fragment>
            <li>
              <a
                id={'segment-' + this.currentSegmentId + '-nextrepetition'}
                onClick={(e) => this.goToNextRepetition(e, 'translated')}
                className="next-repetition ui primary button"
                data-segmentid={'segment-' + this.currentSegmentId}
                title="Translate and go to next repetition"
              >
                REP >
              </a>
            </li>
            <li>
              <a
                id={'segment-' + this.currentSegmentId + '-nextgrouprepetition'}
                onClick={(e) => this.goToNextRepetitionGroup(e, 'translated')}
                className="next-repetition-group ui primary button"
                data-segmentid={'segment-' + this.currentSegmentId}
                title="Translate and go to next repetition group"
              >
                REP >>
              </a>
            </li>
          </React.Fragment>
        )
      }
    }

    return (
      <ul
        className="buttons"
        data-mount="main-buttons"
        id={'segment-' + this.props.segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </ul>
    )
  }

  getTranslateButton() {
    const classDisable = this.props.disabled ? 'disabled' : ''
    return (
      <li>
        <a
          id={'segment-' + this.props.segment.sid + '-button-translated'}
          onClick={(e) => this.clickOnTranslatedButton(e, false)}
          data-segmentid={'segment-' + this.props.segment.sid}
          className={'translated ' + classDisable}
        >
          {' '}
          {config.status_labels.TRANSLATED}{' '}
        </a>
        <p>{UI.isMac ? 'CMD' : 'CTRL'} ENTER</p>
      </li>
    )
  }

  getReviewButton() {
    const classDisable = this.props.disabled ? 'disabled' : ''
    const className = config.isReview
      ? 'revise-button-' + config.revisionNumber
      : ''

    return (
      <li>
        <a
          id={'segment-' + this.props.segment.sid + '-button-translated'}
          data-segmentid={'segment-' + this.props.segment.sid}
          onClick={(event) => this.clickOnApprovedButton(event, false)}
          className={'approved ' + classDisable + ' ' + className}
        >
          {' '}
          {config.status_labels.APPROVED}{' '}
        </a>
        <p>{UI.isMac ? 'CMD' : 'CTRL'} ENTER</p>
      </li>
    )
  }

  componentDidMount() {
    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, this.updateProgress)
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      CattoolConstants.SET_PROGRESS,
      this.updateProgress,
    )
  }

  render() {
    if (this.props.segment.muted) return ''
    return this.getButtons()
  }
}

export default SegmentButton
