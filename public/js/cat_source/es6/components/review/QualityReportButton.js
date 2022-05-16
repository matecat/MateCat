import React from 'react'
import classnames from 'classnames'

import {IconQR} from '../icons/IconQR'
import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'
import CatToolActions from '../../actions/CatToolActions'

/**
 * @NOTE because the state of this component is manipulated
 * outside of the React tree, we currently cannot convert it
 * into a function component...
 */
export class QualityReportButton extends React.Component {
  constructor(props) {
    super(props)

    /**
     * @NOTE for legacy reasons this state is manipulated
     * from outside of the React tree...
     */
    this.state = {
      is_pass: null,
      score: null,
      vote: this.props.overallQualityClass,
      progress: null,
    }
    const {revisionNumber, secondRevisionsCount, qualityReportHref} = this.props
    const revision_number = revisionNumber ? revisionNumber : '1'
    const qrParam = secondRevisionsCount
      ? '?revision_type=' + revision_number
      : ''
    this.quality_report_href = qualityReportHref + qrParam
  }

  getVote = () => {
    const {is_pass, vote} = this.state

    if (is_pass != null) {
      return is_pass ? 'excellent' : 'fail'
    }

    return vote
  }

  updateProgress = (stats) => {
    this.setState({
      progress: stats,
    })
  }

  openFeedbackModal = (e) => {
    e.preventDefault()
    e.stopPropagation()
    CatToolActions.openFeedbackModal(
      this.state.feedback,
      this.props.revisionNumber,
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
    const {quality_report_href} = this
    const {progress, feedback} = this.state

    let classes, label, menu, alert
    if (progress && this.props.isReview) {
      if (this.props.revisionNumber === 1 || this.props.revisionNumber === 2) {
        classes = classnames({
          'ui simple pointing top center floating dropdown': true,
        })

        label = !feedback
          ? `Write feedback (R${this.props.revisionNumber})`
          : `Edit feedback (R${this.props.revisionNumber})`

        menu = (
          <ul className="menu" id="qualityReportMenu">
            <li className="item">
              <a
                title="Open QR"
                onClick={(event) => {
                  event.stopPropagation()
                  window.open(quality_report_href, '_blank')
                }}
              >
                Open QR
              </a>
            </li>
            <li className="item">
              <a title="Revision Feedback" onClick={this.openFeedbackModal}>
                {label}
              </a>
            </li>
          </ul>
        )

        if (!feedback && progress) {
          alert = <div className="feedback-alert" />
        }
      }
    }

    return (
      <div
        id="quality-report"
        className={classes}
        data-vote={this.getVote()}
        data-testid="report-button"
        onClick={() => {
          window.open(quality_report_href, '_blank')
        }}
      >
        <IconQR width={30} height={30} />

        {alert}

        <div className="dropdown-menu-overlay" />

        {menu}
      </div>
    )
  }
}
