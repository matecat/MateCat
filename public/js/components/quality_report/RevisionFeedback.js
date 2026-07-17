import React from 'react'
import PropTypes from 'prop-types'

const RevisionFeedback = ({qualitySummary}) => {
  return (
    <div className="qr-feedback-container shadow-2">
      <div className="qr-feedback">
        <div className="qr-head">
          <h4>
            <div
              className={
                'color-dot revision-color revision-' +
                qualitySummary.get('revision_number')
              }
            />
            Revision feedback
          </h4>
        </div>
        <p>{qualitySummary.get('feedback')}</p>
      </div>
    </div>
  )
}

RevisionFeedback.propTypes = {
  qualitySummary: PropTypes.object.isRequired,
}

export default RevisionFeedback
