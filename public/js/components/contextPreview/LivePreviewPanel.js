import React from 'react'
import PropTypes from 'prop-types'

export const LivePreviewPanel = ({panelRef, title, zoomLevel, ...props}) => {
  return (
    <div className="context-preview-panel" {...props}>
      <div className="context-preview-panel-header">{title}</div>
      <div className="context-preview-content">
        <div
          ref={panelRef}
          className="context-preview-content__scaler"
          style={{transform: `scale(${zoomLevel / 100})`}}
        />
      </div>
    </div>
  )
}

LivePreviewPanel.propTypes = {
  panelRef: PropTypes.object.isRequired,
  title: PropTypes.string.isRequired,
  zoomLevel: PropTypes.number.isRequired,
}
