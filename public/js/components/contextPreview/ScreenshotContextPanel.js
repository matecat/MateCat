import React, {memo, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {resolveScreenshotUrl} from '../../utils/contextPreviewUtils'

export const ScreenshotContextPanel = memo(({
  screenshotUrl,
  zoomLevel,
  title,
  ...props
}) => {
  const [resolvedUrl, setResolvedUrl] = useState(null)
  const [loading, setLoading] = useState(false)
  const scale = zoomLevel / 100
  const margin = scale > 1 ? `${(scale - 1) * 25}%` : '0'

  useEffect(() => {
    if (!screenshotUrl) {
      setResolvedUrl(null)
      setLoading(false)
      return
    }

    let cancelled = false
    setResolvedUrl(null)
    setLoading(true)

    resolveScreenshotUrl(screenshotUrl)
      .then((url) => {
        if (!cancelled) setResolvedUrl(url)
      })
      .catch(() => {
        if (!cancelled) setResolvedUrl(null)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [screenshotUrl])

  return (
    <div className="context-preview-panel" {...props}>
      <div className="context-preview-screenshot">
        {resolvedUrl ? (
          <div
            className="context-preview-screenshot__scaler"
            style={{transform: `scale(${scale})`, margin}}
          >
            <img
              src={resolvedUrl}
              alt="Segment context screenshot"
              onError={() => setResolvedUrl(null)}
            />
          </div>
        ) : loading ? null : (
          <div className="context-preview-screenshot-placeholder">
            No screenshot available
          </div>
        )}
      </div>
    </div>
  )
})

ScreenshotContextPanel.propTypes = {
  screenshotUrl: PropTypes.string,
  zoomLevel: PropTypes.number.isRequired,
  title: PropTypes.string.isRequired,
}
