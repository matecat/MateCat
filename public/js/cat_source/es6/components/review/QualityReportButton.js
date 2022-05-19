import React from 'react'
import classnames from 'classnames'

import {IconQR} from '../icons/IconQR'
import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'
import CatToolActions from '../../actions/CatToolActions'
import CatToolConstants from '../../constants/CatToolConstants'

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
      vote: this.props.vote,
      progress: null,
    }
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

  updateButton = (is_pass, score, feedback) => {
    this.setState({
      is_pass,
      score,
      feedback,
    })
  }

  openFeedbackModal = (e) => {
    e.preventDefault()
    e.stopPropagation()
    CatToolActions.openFeedbackModal(this.state.feedback, config.revisionNumber)
  }

  componentDidMount() {
    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, this.updateProgress)
    CatToolStore.addListener(CatToolConstants.UPDATE_QR, this.updateButton)
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      CattoolConstants.SET_PROGRESS,
      this.updateProgress,
    )
    CatToolStore.removeListener(CatToolConstants.UPDATE_QR, this.updateButton)
  }

  render() {
    const {quality_report_href} = this.props
    const {progress, feedback} = this.state

    let classes, label, menu, alert
    if (progress && config.isReview) {
      if (config.revisionNumber === 1 || config.revisionNumber === 2) {
        classes = classnames({
          'ui simple pointing top center floating dropdown': true,
        })

        label = !feedback
          ? `Write feedback (R${config.revisionNumber})`
          : `Edit feedback (R${config.revisionNumber})`

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
