import React from 'react'
import JobProgressBar from '../common/JobProgressBar'
import {Popup} from 'semantic-ui-react'

class ProductionSummary extends React.Component {
  getTimeToEdit = () => {
    let time = parseInt(this.props.jobInfo.get('total_time_to_edit') / 1000)
    let hours = Math.floor(time / 3600)
    let minutes = Math.floor((time % 3600) / 60)
    let seconds = Math.floor((time % 3600) % 60)

    return [hours, minutes, seconds]
      .map((num) => String(num).padStart(2, '0'))
      .join(':')
  }

  render() {
    const {qualitySummary, jobInfo} = this.props

    const tooltipText = (
      <div style={{color: 'gray'}}>
        Matecat calculates the score as follows: <br />
        <br />
        <code>(Tot. error points * 1000) / reviewed words</code>
        <br />
        Reviewed words = raw words - unmodified ICE matches
        <br />
        <br />
        The score is compared to a max. amount of tolerated error points.
        <a
          style={{textDecoration: 'underline'}}
          href="https://guides.matecat.com/quality-report-in-matecat"
          target="_blank"
        >
          Learn more
        </a>
      </div>
    )
    const score = parseFloat(qualitySummary.get('score'))
    const limit = qualitySummary.get('passfail')
      ? parseInt(qualitySummary.getIn(['passfail', 'options', 'limit']))
      : 0
    const qualityOverall = qualitySummary.get('quality_overall')
    const reviewedWordsCount = qualitySummary.get('total_reviewed_words_count')
    const jobPassed =
      qualityOverall !== null
        ? qualityOverall !== 'fail' && reviewedWordsCount > 0
        : null
    const jobPassedClass =
      jobPassed === null || reviewedWordsCount === 0
        ? 'qr-norevision'
        : jobPassed
          ? 'qr-pass'
          : 'qr-fail'
    const translator = jobInfo.get('translator')
      ? jobInfo.get('translator').get('email')
      : 'Not assigned'
    const stats = jobInfo.get('stats').toJS()

    return (
      <div className="qr-production shadow-2">
        <div className="qr-effort job-id">ID: {jobInfo.get('id')}</div>

        <div className="qr-effort source-to-target">
          <div className="qr-source">
            <b>{jobInfo.get('sourceTxt')}</b>
          </div>

          <div className="qr-to">
            <i className="icon-chevron-right icon" />
          </div>

          <div className="qr-target">
            <b>{jobInfo.get('targetTxt')}</b>
          </div>
        </div>

        <div
          className="qr-effort progress-percent"
          ref={(bar) => (this.progressBar = bar)}
        >
          <JobProgressBar stats={stats} showPercent={false} />
          <div className="percent">
            {Math.round(
              ((stats.equivalent.approved +
                stats.equivalent.approved2 +
                stats.equivalent.translated) /
                stats.equivalent.total) *
                100,
            )}
            %
          </div>
        </div>

        <div className="qr-effort">
          <div className="qr-label">Reviewed Words</div>
          <div className="qr-info">
            <b>{reviewedWordsCount}</b>
          </div>
        </div>

        <div className="qr-effort translator">
          <div className="qr-label">Translator</div>
          <div className="qr-info" title={translator}>
            <b>{translator}</b>
          </div>
        </div>

        <div className="qr-effort time-edit">
          <div className="qr-label">Time to edit</div>
          <div className="qr-info">
            <b>{this.getTimeToEdit()}</b>{' '}
          </div>
        </div>

        <div className="qr-effort pee">
          <div className="qr-label">PEE</div>
          <div className="qr-info">
            <b>
              {jobInfo.get('pee') ? Math.round(jobInfo.get('pee')) : 0}%
            </b>{' '}
          </div>
        </div>

        {config.project_type !== 'old' ? (
          <div className={'qr-effort qr-score ' + jobPassedClass}>
            <div className="qr-info">
              <div className="qr-tolerated-score">
                <b>{score}</b>
              </div>

              {jobPassed === null || reviewedWordsCount === 0 ? (
                <div>
                  <div className="qr-label">Quality score</div>
                  <div className="qr-pass-score">No revision</div>
                </div>
              ) : jobPassed ? (
                <div>
                  <div className="qr-label">Quality score</div>
                  <div className="qr-pass-score">
                    <b>Pass</b>
                  </div>
                </div>
              ) : (
                <div>
                  <div className="qr-label">Quality score</div>
                  <div className="qr-pass-score">
                    <b>Fail</b>
                  </div>
                </div>
              )}
            </div>
            <Popup
              content={tooltipText}
              position="bottom right"
              // /**/ popper={{className: 'ui popup qr-score-popup'}}
              hoverable
              wide="very"
              trigger={
                <div className="qr-label">
                  Threshold {limit} <i className="icon-info icon" />
                </div>
              }
            />
          </div>
        ) : null}
      </div>
    )
  }
}

export default ProductionSummary
