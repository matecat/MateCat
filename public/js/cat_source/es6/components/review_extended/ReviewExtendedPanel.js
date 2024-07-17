import {isEmpty} from 'lodash'
import React from 'react'
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

class ReviewExtendedPanel extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    this.removeMessageType = 0
    this.addIssueToApproveMessageType = 1
    this.addIssueToSelectedTextMessageType = 2
    this.state = {
      versionNumber: this.props.segment.versions[0]
        ? this.props.segment.versions[0].version_number
        : 0,
      diffPatch: null,
      newtranslation: this.props.segment.translation,
      issueInCreation: false,
      showAddIssueMessage: false,
      showAddIssueToSelectedTextMessage: false,
    }
  }

  removeSelection() {
    this.setCreationIssueLoader(false)
    this.context.removeSelection()
    this.setState({
      showAddIssueMessage: false,
      showAddIssueToSelectedTextMessage: false,
    })
  }

  getAllIssues() {
    let issues = []
    this.props.segment.versions.forEach(function (version) {
      if (!isEmpty(version.issues)) {
        issues = issues.concat(version.issues)
      }
    })
    return issues
  }

  setCreationIssueLoader(inCreation) {
    this.setState({
      issueInCreation: inCreation,
    })
  }

  showIssuesMessage(sid, type) {
    switch (type) {
      case this.addIssueToApproveMessageType:
        this.setState({
          showAddIssueMessage: true,
          showAddIssueToSelectedTextMessage: false,
        })
        break
      case this.addIssueToSelectedTextMessageType:
        this.setState({
          showAddIssueMessage: false,
          showAddIssueToSelectedTextMessage: true,
        })
        break
      case this.removeMessageType:
        this.setState({
          showAddIssueMessage: false,
          showAddIssueToSelectedTextMessage: false,
        })
        break
    }
  }

  closePanel() {
    SegmentActions.closeSegmentIssuePanel(this.props.segment.sid)
  }

  static getDerivedStateFromProps(props) {
    return {
      versionNumber: props.segment.versions[0]
        ? props.segment.versions[0].version_number
        : 0,
    }
  }

  componentDidMount() {
    // this.props.setParentLoader(false);
    SegmentStore.addListener(
      SegmentConstants.SHOW_ISSUE_MESSAGE,
      this.showIssuesMessage.bind(this),
    )
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.SHOW_ISSUE_MESSAGE,
      this.showIssuesMessage,
    )
  }

  render() {
    let issues = this.getAllIssues()
    let thereAreIssuesClass = issues.length > 0 ? 'thereAreIssues' : ''
    let cornerClass = classnames({
      error: this.state.showAddIssueMessage,
      warning: this.state.showAddIssueToSelectedTextMessage,
      're-open-view re-issues': true,
    })
    return (
      <div className={'re-wrapper shadow-1 ' + thereAreIssuesClass}>
        <div className={cornerClass} />
        <a
          className="re-close-balloon re-close-err shadow-1"
          onClick={this.closePanel.bind(this)}
        >
          <i className="icon-cancel3 icon" />
        </a>
        <ReviewExtendedIssuesContainer
          loader={this.state.issueInCreation}
          issues={issues}
          isReview={this.props.isReview}
        />
        {this.state.showAddIssueMessage ? (
          <div className="re-warning-not-added-issue">
            <p>
              In order to Approve the segment you need to add an Issue from the
              list below.
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
              .
            </p>
          </div>
        ) : null}

        {this.state.showAddIssueToSelectedTextMessage ? (
          <div className="re-warning-selected-text-issue">
            <p>
              Select an issue from the list below to associate it to the
              selected text.
            </p>
          </div>
        ) : null}

        {this.props.isReview &&
        !(
          SegmentUtils.isIceSegment(this.props.segment) &&
          !this.props.segment.unlocked
        ) ? (
          <ReviewExtendedIssuePanel
            selection={this.props.selectionObj}
            segmentVersion={this.state.versionNumber}
            submitIssueCallback={this.removeSelection.bind(this)}
            newtranslation={this.state.newtranslation}
            setCreationIssueLoader={this.setCreationIssueLoader.bind(this)}
          />
        ) : null}
      </div>
    )
  }
}

export default ReviewExtendedPanel
