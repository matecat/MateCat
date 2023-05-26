import React from 'react'
import PropTypes from 'prop-types'

export const MessageNotification = ({
  type = 'warning',
  message,
  confirmCallback,
  closeCallback = () => {},
}) => {
  return (
    <div
      className={`settingsPanel-notification_${type} settingsPanel-notification`}
    >
      {message}
      <div>
        {confirmCallback && <button onClick={confirmCallback}>Confirm</button>}
        <button onClick={() => closeCallback()}>x</button>
      </div>
    </div>
  )
}
MessageNotification.propTypes = {
  type: PropTypes.oneOf(['success', 'warning', 'error']),
  message: PropTypes.string,
  confirmCallback: PropTypes.func,
  closeCallback: PropTypes.func,
}
