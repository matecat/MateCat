import React, {useEffect, useState} from 'react'
import $ from 'jquery'
import {isUndefined, isNull} from 'lodash'

import SegmentStore from '../../stores/SegmentStore'
import CatToolStore from '../../stores/CatToolStore'
import SegmentFilter from '../header/cattol/segment_filter/segment_filter'
import SegmentUtils from '../../utils/segmentUtils'
import CattoolConstants from '../../constants/CatToolConstants'
import CommonUtils from '../../utils/commonUtils'
import {REVISE_STEP_NUMBER, SEGMENTS_STATUS} from '../../constants/Constants'
import {
  decodePlaceholdersToPlainText,
  removeTagsFromText,
} from './utils/DraftMatecatUtils/tagUtils'
import SegmentActions from '../../actions/SegmentActions'
import UserStore from '../../stores/UserStore'
import {isMacOS} from '../../utils/Utils'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../utils/shortcuts'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

export const SegmentButton = ({segment, disabled, isReview}) => {
  useHotkeys(
    Shortcuts.cattol.events.translate_nextUntranslated.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => {
      if (!disabled) {
        setTimeout(() => {
          config.isReview
            ? clickOnApprovedButton(e, true)
            : clickOnTranslatedButton(e, true)
        }, 150)
      }
    },
    {enableOnContentEditable: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.translate.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      if (!disabled) {
        setTimeout(() => {
          config.isReview
            ? clickOnApprovedButton(e, false)
            : clickOnTranslatedButton(e, false)
        }, 150)
      }
    },
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
      const userInfo = UserStore.getUser()
      const event = {
        event: 'first_segment_confirm',
        userStatus: userInfo ? 'loggedUser' : 'notLoggedUser',
        userId: userInfo ? userInfo.user.uid : undefined,
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
    setTimeout(() => SegmentFilter.goToNextRepetition(status))
  }

  const goToNextRepetitionGroup = (event, status) => {
    setTimeout(() => SegmentFilter.goToNextRepetitionGroup(status))
  }

  const clickOnGuessTags = (e) => {
    const contribution = segment.contributions?.matches
      ? segment.contributions.matches[0]
      : undefined
    if (
      contribution &&
      contribution.translation &&
      contribution.match === 'MT'
    ) {
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
    setTimeout(() => SegmentActions.startSegmentTagProjection(segment.sid))
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
    const filtering = SegmentFilter.enabled() && SegmentFilter.filtering()
    const type =
      config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
        ? BUTTON_TYPE.SUCCESS
        : BUTTON_TYPE.PURPLE
    const status =
      config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
        ? SEGMENTS_STATUS.APPROVED
        : SEGMENTS_STATUS.APPROVED2
    enableGoToNext =
      enableGoToNext &&
      (isNull(nextSegment.revision_number) ||
        (!isNull(nextSegment.revision_number) &&
          (nextSegment.revision_number === config.revisionNumber ||
            (nextSegment.revision_number === 2 &&
              config.revisionNumber === 1))) || //Not Same Rev
        (SegmentUtils.isIceSegment(nextSegment) && !nextSegment.unlocked)) // Ice Locked

    nextButton = enableGoToNext ? (
      <Button
        type={type}
        onClick={(event) => clickOnApprovedButton(event, true)}
        disabled={disabled}
        title="Revise and go to next translated"
        tooltip={`${isMac ? 'CMD' : 'CTRL'}+SHIFT+ENTER`}
      >
        A+&gt;&gt;
      </Button>
    ) : null
    currentButton = getReviewButton()

    if (filtering) {
      nextButton = null
      const data = SegmentFilter.getStoredState()
      const filterinRepetitions =
        data.reactState && data.reactState.samplingType === 'repetitions'
      if (filterinRepetitions) {
        nextButton = []
        nextButton.push(
          <Button
            type={type}
            onClick={(e) => goToNextRepetition(e, status)}
            disabled={disabled}
            title="Revise and go to next repetition"
          >
            REP &gt;
          </Button>,
        )
        nextButton.push(
          <Button
            type={type}
            onClick={(e) => goToNextRepetitionGroup(e, status)}
            disabled={disabled}
            title="Revise and go to next repetition group"
          >
            REP &gt;&gt;
          </Button>,
        )
      }
    }
    return (
      <div
        className="buttons"
        data-mount="main-buttons"
        id={'segment-' + segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </div>
    )
  }

  const getTranslateButtons = () => {
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
        <Button
          type={BUTTON_TYPE.PRIMARY}
          onClick={clickOnGuessTags}
          disabled={disabled}
          tooltip={`${isMac ? 'CMD' : 'CTRL'} ENTER`}
        >
          Guess tags
        </Button>
      )
    } else {
      nextButton = enableGoToNext ? (
        <Button
          type={BUTTON_TYPE.PRIMARY}
          onClick={(e) => clickOnTranslatedButton(e, true)}
          disabled={disabled}
          tooltip={`{isMac ? 'CMD' : 'CTRL'}+SHIFT+ENTER`}
          title="Translate and go to next untranslated"
        >
          T+&gt;&gt;
        </Button>
      ) : null
      currentButton = getTranslateButton()
    }
    if (filtering) {
      nextButton = null
      const data = SegmentFilter.getStoredState()
      const filterinRepetitions =
        data.reactState && data.reactState.samplingType === 'repetitions'
      if (filterinRepetitions) {
        nextButton = []
        nextButton.push(
          <Button
            type={BUTTON_TYPE.PRIMARY}
            onClick={(e) => goToNextRepetition(e, 'translated')}
            title="Translate and go to next repetition"
          >
            REP &gt;
          </Button>,
        )
        nextButton.push(
          <Button
            type={BUTTON_TYPE.PRIMARY}
            onClick={(e) => goToNextRepetitionGroup(e, 'translated')}
            title="Translate and go to next repetition group"
          >
            REP &gt;&gt;
          </Button>,
        )
      }
    }

    return (
      <div
        className="buttons"
        data-mount="main-buttons"
        id={'segment-' + segment.sid + '-buttons'}
      >
        {nextButton}
        {currentButton}
      </div>
    )
  }

  const getTranslateButton = () => {
    return (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        onClick={(e) => clickOnTranslatedButton(e, false)}
        tooltip={`${isMac ? 'CMD' : 'CTRL'}+ENTER`}
      >
        {config.status_labels.TRANSLATED}
      </Button>
    )
  }

  const getReviewButton = () => {
    const type =
      config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
        ? BUTTON_TYPE.SUCCESS
        : BUTTON_TYPE.PURPLE
    return (
      <Button
        type={type}
        onClick={(event) => clickOnApprovedButton(event, false)}
        tooltip={`${isMac ? 'CMD' : 'CTRL'}+ENTER`}
      >
        {config.status_labels.APPROVED}
      </Button>
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
