import {isEmpty} from 'lodash'
import React, {useCallback, useContext, useEffect, useState} from 'react'
import classnames from 'classnames'

import ReviewExtendedIssuesContainer from './ReviewExtendedIssuesContainer'
import ReviewExtendedIssuePanel from './ReviewExtendedIssuePanel'
import SegmentConstants from '../../constants/SegmentConstants'
import {Shortcuts} from '../../utils/shortcuts'
import ShortCutsModal from '../modals/ShortCutsModal'
import SegmentActions from '../../actions/SegmentActions'
import SegmentStore from '../../stores/SegmentStore'
import SegmentUtils from '../../utils/segmentUtils'
import {SegmentContext} from '../segments/SegmentContext'
import ModalsActions from '../../actions/ModalsActions'
import IconClose from '../../../img/icons/IconClose'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'

const removeMessageType = 0
const addIssueToApproveMessageType = 1
const addIssueToSelectedTextMessageType = 2

const ReviewExtendedPanel = (props) => {
  const context = useContext(SegmentContext)

  const versionNumber = props.segment.versions[0]
    ? props.segment.versions[0].version_number
    : 0

  // eslint-disable-next-line no-unused-vars
  const [diffPatch, setDiffPatch] = useState(null)
  const [newtranslation] = useState(props.segment.translation)
  const [issueInCreation, setIssueInCreation] = useState(false)
  const [issueEditing, setIssueEditing] = useState(undefined)
  const [issueMessages, setIssueMessages] = useState({
    showAddIssueMessage: false,
    showAddIssueToSelectedTextMessage: false,
  })

  const getAllIssues = () => {
    let issues = []
    props.segment.versions.forEach(function (version) {
      if (!isEmpty(version.issues)) {
        issues = issues.concat(version.issues)
      }
    })
    return issues
  }

  const removeSelection = () => {
    setIssueInCreation(false)
    context.removeSelection()
    setIssueMessages({
      showAddIssueMessage: false,
      showAddIssueToSelectedTextMessage: false,
    })
    SegmentActions.unlockEditArea(props.segment.sid)
  }

  const showIssuesMessage = useCallback((sid, type) => {
    switch (type) {
      case addIssueToApproveMessageType:
        setIssueMessages({
          showAddIssueMessage: true,
          showAddIssueToSelectedTextMessage: false,
        })
        break
      case addIssueToSelectedTextMessageType:
        setIssueMessages({
          showAddIssueMessage: false,
          showAddIssueToSelectedTextMessage: true,
        })
        break
      case removeMessageType:
        setIssueMessages({
          showAddIssueMessage: false,
          showAddIssueToSelectedTextMessage: false,
        })
        break
    }
  }, [])

  const closePanel = () => {
    SegmentActions.closeSegmentIssuePanel(props.segment.sid)
  }

  useEffect(() => {
    SegmentStore.addListener(
      SegmentConstants.SHOW_ISSUE_MESSAGE,
      showIssuesMessage,
    )
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.SHOW_ISSUE_MESSAGE,
        showIssuesMessage,
      )
    }
  }, [showIssuesMessage])

  const issues = getAllIssues()
  const thereAreIssuesClass = issues.length > 0 ? 'thereAreIssues' : ''
  const cornerClass = classnames({
    error: issueMessages.showAddIssueMessage,
    warning: issueMessages.showAddIssueToSelectedTextMessage,
    're-open-view re-issues': true,
  })

  return (
    <div className={'re-wrapper shadow-1 ' + thereAreIssuesClass}>
      <div className={cornerClass} />
      <Button
        type={BUTTON_TYPE.ICON}
        mode={BUTTON_MODE.GHOST}
        size={BUTTON_SIZE.ICON_XSMALL}
        className="re-close-balloon"
        onClick={closePanel}
      >
        <IconClose />
      </Button>
      <ReviewExtendedIssuesContainer
        loader={issueInCreation}
        issues={issues}
        isReview={props.isReview}
        issueEditing={issueEditing}
        setIssueEditing={setIssueEditing}
        selection={props.selectionObj}
        segmentVersion={versionNumber}
      />
      {issueMessages.showAddIssueMessage ? (
        <div className="re-warning-not-added-issue">
          <p>
            You must add an issue from the list below before approving this
            segment.
            <br />
            <a
              onClick={() =>
                ModalsActions.showModalComponent(
                  ShortCutsModal,
                  null,
                  'Shortcuts',
                )
              }
            >
              {'Shortcut: ' +
                Shortcuts.cattol.events.navigateIssues.equivalent[
                  Shortcuts.shortCutsKeyType
                ]}
            </a>
            <br />
            <i>
              Note: the job owner and workspace members can disable this
              requirement from settings.
            </i>
          </p>
        </div>
      ) : null}

      {issueMessages.showAddIssueToSelectedTextMessage ? (
        <div className="re-warning-selected-text-issue">
          <p>
            Select an issue from the list below to associate it to the selected
            text.
          </p>
        </div>
      ) : null}

      {props.isReview &&
      !(SegmentUtils.isIceSegment(props.segment) && !props.segment.unlocked) ? (
        <ReviewExtendedIssuePanel
          selection={props.selectionObj}
          segmentVersion={versionNumber}
          submitIssueCallback={removeSelection}
          newtranslation={newtranslation}
          setCreationIssueLoader={setIssueInCreation}
          setIssueEditing={setIssueEditing}
        />
      ) : null}
    </div>
  )
}

export default ReviewExtendedPanel
