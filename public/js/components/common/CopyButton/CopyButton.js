import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'
import CopyIcon from '../../../../img/icons/CopyIcon'

const CopyButton = ({onCopy}) => {
  const [showFeedback, setShowFeedback] = useState(false)
  const timeoutRef = useRef()

  useEffect(() => {
    return () => clearTimeout(timeoutRef.current)
  }, [])

  const handleClick = (e) => {
    if (typeof onCopy === 'function') onCopy(e)
    clearTimeout(timeoutRef.current)
    setShowFeedback(true)
    timeoutRef.current = setTimeout(() => setShowFeedback(false), 2000)
  }

  return (
    <span style={{position: 'relative', display: 'inline-flex'}}>
      <Button
        size={BUTTON_SIZE.ICON_XSMALL}
        mode={BUTTON_MODE.GHOST}
        type={BUTTON_TYPE.PRIMARY}
        onClick={handleClick}
        tooltip={showFeedback ? undefined : 'Copy to Clipboard'}
      >
        <CopyIcon size={16} />
      </Button>
      {showFeedback && (
        <span
          style={{
            position: 'absolute',
            bottom: '100%',
            left: '50%',
            transform: 'translateX(-50%)',
            marginBottom: '6px',
            background: 'rgba(0,0,0,0.75)',
            color: '#fff',
            fontSize: '12px',
            borderRadius: '4px',
            padding: '4px 8px',
            whiteSpace: 'nowrap',
            pointerEvents: 'none',
            zIndex: 9999,
          }}
        >
          Copied to Clipboard!
        </span>
      )}
    </span>
  )
}

CopyButton.propTypes = {
  onCopy: PropTypes.func.isRequired,
}

export default CopyButton
