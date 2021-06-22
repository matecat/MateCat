import React from 'react'

import ProductionSummary from './ProductionSummary'
import QualitySummaryTable from './QualitySummaryTable'
import {QualitySummaryTableOldRevise} from './QualitySummaryTableOldRevise'
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

        {this.props.jobInfo ? (
          config.project_type === 'new' ? (
            <QualitySummaryTable
              jobInfo={this.props.jobInfo}
              qualitySummary={this.props.qualitySummary}
              secondPassReviewEnabled={this.props.secondPassReviewEnabled}
            />
          ) : (
            <QualitySummaryTableOldRevise
              qualitySummary={this.props.qualitySummary}
            />
          )
        ) : null}

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
