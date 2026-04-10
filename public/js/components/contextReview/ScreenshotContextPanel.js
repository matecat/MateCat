import React from 'react'
import PropTypes from 'prop-types'

export const ScreenshotContextPanel = ({
  screenshotUrl,
  zoomLevel,
  title,
  ...props
}) => {
  const scale = zoomLevel / 100
  const margin = scale > 1 ? `${(scale - 1) * 25}%` : '0'

  return (
    <div className="context-review-panel" {...props}>
      <div className="context-review-panel-header">{title}</div>
      <div className="context-review-screenshot">
        {screenshotUrl ? (
          <div
            className="context-review-screenshot__scaler"
            style={{transform: `scale(${scale})`, margin}}
          >
            <img src={screenshotUrl} alt="Segment context screenshot" />
          </div>
        ) : (
          <div className="context-review-screenshot-placeholder">
            No screenshot available
          </div>
        )}
      </div>
    </div>
  )
}

ScreenshotContextPanel.propTypes = {
  screenshotUrl: PropTypes.string,
  zoomLevel: PropTypes.number.isRequired,
  title: PropTypes.string.isRequired,
}
