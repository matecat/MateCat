import React from 'react'

import ProductionSummary from './ProductionSummary'
import QualitySummaryTable from './QualitySummaryTable'
import RevisionFeedback from './RevisionFeedback'

class JobSummary extends React.Component {
  render() {
    return (
      <div className="qr-production-quality">
        {this.props.jobInfo ? (
          <ProductionSummary
            jobInfo={this.props.jobInfo}
            qualitySummary={this.props.qualitySummary}
            secondPassReviewEnabled={this.props.secondPassReviewEnabled}
          />
        ) : null}

        {this.props.jobInfo && (
          <QualitySummaryTable
            jobInfo={this.props.jobInfo}
            qualitySummary={this.props.qualitySummary}
            secondPassReviewEnabled={this.props.secondPassReviewEnabled}
          />
        )}

        {this.props.qualitySummary.get('feedback') ? (
          <RevisionFeedback
            jobInfo={this.props.jobInfo}
            qualitySummary={this.props.qualitySummary}
            secondPassReviewEnabled={this.props.secondPassReviewEnabled}
          />
        ) : null}
      </div>
    )
  }
}

export default JobSummary
