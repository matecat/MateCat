import React from 'react'

import ProductionSummary from './ProductionSummary'
import QualitySummaryTable from './QualitySummaryTable'
import RevisionFeedback from './RevisionFeedback'

export const JobSummary = ({
  jobInfo,
  qualitySummary,
  secondPassReviewEnabled,
}) => {
  return (
    <div className="qr-production-quality">
      {jobInfo ? (
        <ProductionSummary
          jobInfo={jobInfo}
          qualitySummary={qualitySummary}
          secondPassReviewEnabled={secondPassReviewEnabled}
        />
      ) : null}

      {jobInfo && (
        <QualitySummaryTable
          jobInfo={jobInfo}
          qualitySummary={qualitySummary}
          secondPassReviewEnabled={secondPassReviewEnabled}
        />
      )}

      {qualitySummary.get('feedback') ? (
        <RevisionFeedback
          jobInfo={jobInfo}
          qualitySummary={qualitySummary}
          secondPassReviewEnabled={secondPassReviewEnabled}
        />
      ) : null}
    </div>
  )
}

export default JobSummary
