import React from 'react'
import PropTypes from 'prop-types'

export const HtmlContextPanel = ({panelRef, title, zoomLevel, ...props}) => {
  return (
    <div className="context-review-panel" {...props}>
      <div className="context-review-panel-header">{title}</div>
      <div className="context-review-content">
        <div
          ref={panelRef}
          className="context-review-content__scaler"
          style={{transform: `scale(${zoomLevel / 100})`}}
        />
      </div>
    </div>
  )
}

HtmlContextPanel.propTypes = {
  panelRef: PropTypes.object.isRequired,
  title: PropTypes.string.isRequired,
  zoomLevel: PropTypes.number.isRequired,
}
