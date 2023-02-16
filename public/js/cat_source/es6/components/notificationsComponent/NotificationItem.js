import PropTypes from 'prop-types'
import React, {useState, useRef, useEffect} from 'react'

const NotificationItem = ({
  uid,
  position,
  title,
  text,
  type,
  autoDismiss,
  closeCallback,
  openCallback,
  allowHtml,
  timer,
  dismissable,
  onRemove,
  remove,
}) => {
  const [visible, setVisible] = useState(false)
  const [removed, setRemoved] = useState(false)

  let _isMounted = useRef()

  const styleNameContainer = 'notification-type-' + type
  const styleNameTitle = 'notification-title-' + type
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

  const allowHTML = (string) => {
    return {__html: string}
  }

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

  return (
    <div className={styleNameContainer} style={getNotificationStyle()}>
      {position === 'bl' && type === 'info' ? (
        <div className="notifications-cat-smiling" />
      ) : null}
      {dismissable ? (
        <span
          className={'notification-close-button'}
          onClick={hideNotification}
        >
          ×
        </span>
      ) : null}
      {allowHtml ? (
        <>
          <div
            className={styleNameTitle}
            dangerouslySetInnerHTML={allowHTML(title)}
          />
          <div
            className={'notification-message'}
            dangerouslySetInnerHTML={allowHTML(text)}
          />
        </>
      ) : (
        <>
          <div className={styleNameTitle}> {title}</div>
          <div className={'notification-message'}>{text}</div>
        </>
      )}
    </div>
  )
}

NotificationItem.propTypes = {
  position: PropTypes.string,
  title: PropTypes.string.isRequired,
  text: PropTypes.string.isRequired,
  type: PropTypes.string,
  autoDismiss: PropTypes.bool,
  closeCallback: PropTypes.func,
  openCallback: PropTypes.func,
  allowHtml: PropTypes.bool,
  timer: PropTypes.number,
  dismissable: PropTypes.bool,
}

NotificationItem.defaultProps = {
  position: 'bl',
  type: 'info',
  autoDismiss: true,
  allowHtml: false,
  dismissable: true,
  timer: 7000,
}

export default NotificationItem
