import PropTypes from 'prop-types'
import React, {useState, useRef, useEffect} from 'react'
import IconClose from '../icons/IconClose'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import InfoIcon from '../../../img/icons/InfoIcon'
import CheckCircleBroken from '../../../img/icons/CheckCircleBroken'

const NotificationItem = ({
  uid,
  position = 'bl',
  title,
  text,
  type = 'info',
  autoDismiss = true,
  closeCallback,
  openCallback,
  timer = 7000,
  dismissable = true,
  onRemove,
  remove,
}) => {
  const [visible, setVisible] = useState(false)
  const [removed, setRemoved] = useState(false)
  
  let _isMounted = useRef()

  const styleNameContainer = 'notification-type-' + type
  let _notificationTimer = useRef()

  const hideNotification = () => {
    if (_notificationTimer.current) {
      clearTimeout(_notificationTimer.current)
    }

    if (_isMounted.current) {
      setVisible(false)
      setRemoved(true)
    }
    setTimeout(function () {
      onRemove(uid)
    }, 1000)

    if (closeCallback) {
      closeCallback.call()
    }
  }

  useEffect(() => {
    _isMounted.current = true
    setTimeout(() => {
      setVisible(true)
    }, 50)

    if (autoDismiss) {
      clearTimeout(_notificationTimer.current)
      _notificationTimer.current = setTimeout(() => {
        hideNotification()
      }, timer)
    }
    if (openCallback) {
      openCallback.call()
    }
  }, [])

  useEffect(() => {
    if (remove && visible) {
      hideNotification()
    }
  }, [remove])

  const getCssPropertyByPosition = () => {
    let css = {}

    switch (position) {
      case 'bl':
      case 'bc':
      case 'br':
        css = {
          property: 'bottom',
          value: -200,
        }
        break
      case 'tl':
      case 'tr':
      case 'tc':
        css = {
          property: 'top',
          value: -200,
        }
        break
      default:
    }

    return css
  }

  const getNotificationStyle = () => {
    let notificationStyle = {}
    let cssByPos = getCssPropertyByPosition()
    if (!visible && !removed) {
      notificationStyle[cssByPos.property] = cssByPos.value
    }

    if (visible && !removed) {
      notificationStyle[cssByPos.property] = 0
      notificationStyle.opacity = 1
    }

    if (removed) {
      notificationStyle.overflow = 'hidden'
      notificationStyle.opacity = 0
      notificationStyle[cssByPos.property] = cssByPos.value
      notificationStyle.height = 0
      notificationStyle.marginTop = 0
      notificationStyle.paddingTop = 0
      notificationStyle.paddingBottom = 0
      notificationStyle.borderTop = 0
    }
    return notificationStyle
  }

  const icon =
    type === 'success' ? (
      <CheckCircleBroken size={16} />
    ) : (
      <InfoIcon size={16} />
    )

  return (
    <div
      className={`notification-item ${styleNameContainer}`}
      style={getNotificationStyle()}
    >
      <div>
        <div className="notification-item-icon">{icon}</div>
        <div className="notification-item-content">
          <h6>{title}</h6>
          <p>{text}</p>
        </div>
      </div>
      {dismissable && (
        <Button
          mode={BUTTON_MODE.GHOST}
          type={BUTTON_TYPE.ICON}
          size={BUTTON_SIZE.ICON_XSMALL}
          onClick={hideNotification}
        >
          <IconClose size={10} />
        </Button>
      )}
    </div>
  )
}

NotificationItem.propTypes = {
  position: PropTypes.string,
  title: PropTypes.oneOfType([PropTypes.string, PropTypes.node]).isRequired,
  text: PropTypes.oneOfType([PropTypes.string, PropTypes.node]).isRequired,
  type: PropTypes.string,
  autoDismiss: PropTypes.bool,
  closeCallback: PropTypes.func,
  openCallback: PropTypes.func,
  timer: PropTypes.number,
  dismissable: PropTypes.bool,
}

export default NotificationItem
