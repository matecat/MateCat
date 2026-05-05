import React, {useRef, useEffect} from 'react'
import PropTypes from 'prop-types'

const SHADOW_STYLES = `
  p, li, td, th, h1, h2, h3, h4 {
    cursor: pointer;
    transition: background-color 0.15s ease;
  }
  p:hover, li:hover, td:hover, th:hover,
  h1:hover, h2:hover, h3:hover, h4:hover {
    background-color: rgba(42, 140, 252, 0.08);
    border-radius: 2px;
  }
  mark.context-preview-highlight {
    background-color: #fee47a;
    color: inherit;
    padding: 1px 2px;
    border-radius: 2px;
    box-decoration-break: clone;
  }
  mark.context-preview-highlight--active {
    background-color: #f2711c;
    outline: 2px solid #df681a;
    outline-offset: 1px;
  }
  [data-context-sids].context-preview-node--mismatch {
    outline: 2px solid #e05c00;
    outline-offset: 2px;
    position: relative;
  }
  [data-context-sids].context-preview-node--mismatch::after {
    content: '⚠';
    position: absolute;
    top: -0.6em;
    right: -0.3em;
    font-size: 0.75em;
    color: #e05c00;
    pointer-events: none;
  }
`

export const LivePreviewPanel = ({panelRef, title, zoomLevel, ...props}) => {
  const hostRef = useRef(null)

  useEffect(() => {
    const host = hostRef.current
    if (!host || host.shadowRoot) return

    const shadow = host.attachShadow({mode: 'open'})

    const style = document.createElement('style')
    style.textContent = SHADOW_STYLES
    shadow.appendChild(style)

    const wrapper = document.createElement('div')
    shadow.appendChild(wrapper)

    panelRef.current = wrapper
  }, [panelRef])

  return (
    <div className="context-preview-panel" {...props}>
      <div className="context-preview-panel-header">{title}</div>
      <div className="context-preview-content">
        <div
          ref={hostRef}
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
