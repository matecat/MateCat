import React from 'react'
import PropTypes from 'prop-types'

const RevisionFeedback = ({qualitySummary}) => {
  return (
    <div className="qr-feedback-container shadow-2">
      <div className="qr-feedback">
        <div className="qr-head">
          <h3>
            <div
              className={
                'ui revision-color empty circular label revision-' +
                qualitySummary.get('revision_number')
              }
            />
            Revision feedback
          </h3>
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
