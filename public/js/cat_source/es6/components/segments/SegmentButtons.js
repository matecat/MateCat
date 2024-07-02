import React, {useEffect, useState} from 'react'
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
import {isMacOS} from '../../utils/Utils'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../utils/shortcuts'

export const SegmentButton = ({segment, disabled, isReview}) => {
  useHotkeys(
    Shortcuts.cattol.events.translate_nextUntranslated.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) =>
      config.isReview
        ? clickOnApprovedButton(e, true)
        : clickOnTranslatedButton(e, true),
    {enableOnContentEditable: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.translate.keystrokes[Shortcuts.shortCutsKeyType],
    (e) =>
      config.isReview
        ? clickOnApprovedButton(e, false)
        : clickOnTranslatedButton(e, false),
    {enableOnContentEditable: true},
  )

  const [progress, setProgress] = useState()
  const isMac = isMacOS()

  const updateProgress = (stats) => {
    setProgress(stats)
  }

  const trackTranslatedClick = () => {
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

  const clickOnTranslatedButton = (event, gotoUntranslated) => {
    const currentSegmentTPEnabled =
      SegmentUtils.checkCurrentSegmentTPEnabled(segment)
    if (currentSegmentTPEnabled) {
      clickOnGuessTags(event)
    } else {
      trackTranslatedClick()
      setTimeout(() =>
        SegmentActions.clickOnTranslatedButton(segment, gotoUntranslated),
      )
    }
  }

  const clickOnApprovedButton = (event, gotoNexUnapproved) => {
    setTimeout(() =>
      SegmentActions.clickOnApprovedButton(segment, gotoNexUnapproved),
    )
  }

  const goToNextRepetition = (event, status) => {
    let target = event.currentTarget
    setTimeout(() => SegmentFilter.goToNextRepetition(target, status))
  }

  const goToNextRepetitionGroup = (event, status) => {
    let target = event.currentTarget
    setTimeout(() => SegmentFilter.goToNextRepetitionGroup(target, status))
  }

  const clickOnGuessTags = (e) => {
    const contribution = segment.contributions?.matches
      ? segment.contributions.matches[0]
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
    setTimeout(() => UI.startSegmentTagProjection(segment.sid))
    return false
  }

  const getButtons = () => {
    let html
    if (isReview) {
      //Revise Default, Extended
      html = getReviewButtons()
    } else {
      //Translate
      html = getTranslateButtons()
    }
    return html
  }

  const getReviewButtons = () => {
    const classDisable = disabled ? 'disabled' : ''
    let nextButton, currentButton
    let nextSegment = SegmentStore.getNextSegment({
      current_sid: segment.sid,
    })

    let revisionCompleted = false
    if (config.isReview && progress) {
      revisionCompleted =
        config.revisionNumber === 1
          ? progress.revision1Completed
          : progress.revision2Completed
    } else if (progress) {
      revisionCompleted = progress.revisionCompleted
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
          id={'segment-' + segment.sid + '-nexttranslated'}
          onClick={(event) => clickOnApprovedButton(event, true)}
          className={'btn next-unapproved ' + classDisable + ' ' + className}
          data-segmentid={'segment-' + segment.sid}
          title="Revise and go to next translated"
        >
          {' '}
          A+>>
        </a>
        <p>
          {isMac ? 'CMD' : 'CTRL'}
          +SHIFT+ENTER
        </p>
      </li>
    ) : null
    currentButton = getReviewButton()

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
                id={'segment-' + segment.sid + '-nextrepetition'}
                onClick={(e) => goToNextRepetition(e, 'approved')}
                className={
                  'next-review-repetition ui green button ' + className
                }
                data-segmentid={'segment-' + segment.sid}
                title="Revise and go to next repetition"
              >
                REP &lt;
              </a>
            </li>
            <li>
              <a
                id={'segment-' + segment.sid + '-nextgrouprepetition'}
                onClick={(e) => goToNextRepetitionGroup(e, 'approved')}
                className={
                  'next-review-repetition-group ui green button ' + className
                }
                data-segmentid={'segment-' + segment.sid}
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
        id={'segment-' + segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </ul>
    )
  }

  const getTranslateButtons = () => {
    const classDisable = disabled ? 'disabled' : ''

    let nextButton, currentButton
    const filtering =
      SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open
    let nextSegment = SegmentStore.getNextSegment({
      current_sid: segment.sid,
    })
    let translationCompleted = progress && progress.translationCompleted
    let enableGoToNext =
      !isUndefined(nextSegment) &&
      !translationCompleted &&
      ((nextSegment.status !== 'NEW' &&
        nextSegment.status !== 'DRAFT' &&
        nextSegment.autopropagated_from == 0) ||
        (SegmentUtils.isIceSegment(nextSegment) && !nextSegment.unlocked))
    //TODO Store TP Information in the SegmentsStore
    const currentSegmentTPEnabled =
      SegmentUtils.checkCurrentSegmentTPEnabled(segment)
    if (currentSegmentTPEnabled) {
      nextButton = ''
      currentButton = (
        <li>
          <a
            id={'segment-' + segment.sid + '-button-guesstags'}
            onClick={(e) => clickOnGuessTags(e)}
            data-segmentid={'segment-' + segment.sid}
            className={'guesstags ' + classDisable}
          >
            {' '}
            GUESS TAGS
          </a>
          <p>
            {isMac ? 'CMD' : 'CTRL'}
            ENTER
          </p>
        </li>
      )
    } else {
      nextButton = enableGoToNext ? (
        <li>
          <a
            id={'segment-' + segment.sid + '-nextuntranslated'}
            onClick={(e) => clickOnTranslatedButton(e, true)}
            className={'btn next-untranslated ' + classDisable}
            data-segmentid={'segment-' + segment.sid}
            title="Translate and go to next untranslated"
          >
            {' '}
            T+>>
          </a>
          <p>
            {isMac ? 'CMD' : 'CTRL'}
            +SHIFT+ENTER
          </p>
        </li>
      ) : null
      currentButton = getTranslateButton()
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
                id={'segment-' + segment.sid + '-nextrepetition'}
                onClick={(e) => goToNextRepetition(e, 'translated')}
                className="next-repetition ui primary button"
                data-segmentid={'segment-' + segment.sid}
                title="Translate and go to next repetition"
              >
                REP >
              </a>
            </li>
            <li>
              <a
                id={'segment-' + segment.sid + '-nextgrouprepetition'}
                onClick={(e) => goToNextRepetitionGroup(e, 'translated')}
                className="next-repetition-group ui primary button"
                data-segmentid={'segment-' + segment.sid}
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
        id={'segment-' + segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </ul>
    )
  }

  const getTranslateButton = () => {
    const classDisable = disabled ? 'disabled' : ''
    return (
      <li>
        <a
          id={'segment-' + segment.sid + '-button-translated'}
          onClick={(e) => clickOnTranslatedButton(e, false)}
          data-segmentid={'segment-' + segment.sid}
          className={'translated ' + classDisable}
        >
          {' '}
          {config.status_labels.TRANSLATED}{' '}
        </a>
        <p>{isMac ? 'CMD' : 'CTRL'} ENTER</p>
      </li>
    )
  }

  const getReviewButton = () => {
    const classDisable = disabled ? 'disabled' : ''
    const className = config.isReview
      ? 'revise-button-' + config.revisionNumber
      : ''

    return (
      <li>
        <a
          id={'segment-' + segment.sid + '-button-translated'}
          data-segmentid={'segment-' + segment.sid}
          onClick={(event) => clickOnApprovedButton(event, false)}
          className={'approved ' + classDisable + ' ' + className}
        >
          {' '}
          {config.status_labels.APPROVED}{' '}
        </a>
        <p>{isMac ? 'CMD' : 'CTRL'} ENTER</p>
      </li>
    )
  }

  useEffect(() => {
    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, updateProgress)
    return () => {
      CatToolStore.removeListener(CattoolConstants.SET_PROGRESS, updateProgress)
    }
  }, [])

  if (segment.muted) return null
  return getButtons()
}

export default SegmentButton
