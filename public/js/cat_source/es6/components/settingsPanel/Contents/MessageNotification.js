import React from 'react'
import PropTypes from 'prop-types'
import Close from '../../../../../../img/icons/Close'

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
      {typeof message === 'string' ? (
        <p dangerouslySetInnerHTML={{__html: message}} />
      ) : (
        message
      )}
      <div>
        {confirmCallback && (
          <button className="ui primary button" onClick={confirmCallback}>
            Confirm
          </button>
        )}
        <button
          className="ui button orange button-close"
          onClick={() => closeCallback()}
        >
          <Close />
        </button>
      </div>
    </div>
  )
}
MessageNotification.propTypes = {
  type: PropTypes.oneOf(['success', 'warning', 'error']),
  message: PropTypes.oneOfType([PropTypes.string, PropTypes.node]),
  confirmCallback: PropTypes.func,
  closeCallback: PropTypes.func,
}
