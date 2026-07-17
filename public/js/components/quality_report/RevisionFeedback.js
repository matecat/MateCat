import React from 'react'

class RevisionFeedback extends React.Component {
  render() {
    return (
      <div className="qr-feedback-container shadow-2">
        <div className="qr-feedback">
          <div className={'qr-head'}>
            <h3>
              <div
                className={
                  'ui revision-color empty circular label revision-' +
                  this.props.qualitySummary.get('revision_number')
                }
              />
              Revision feedback
            </h3>
          </div>
          <p>{this.props.qualitySummary.get('feedback')}</p>
        </div>
      </div>
    )
  }
}

export default RevisionFeedback
