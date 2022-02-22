/**
 * React Component to add notifications to Matecat.
 * You can add new notifications calling the addNotification method passing a
 * notification object with the following properties
 *
 * uid:             (String) To uniquely identify a notification (Not mandatory)
 * title:           (String) Title of the notification.
 * text:            (String) Message of the notification
 * type:            (String, Default "info") Level of the notification. Available: success, error, warning and info.
 * position:        (String, Default "bl") Position of the notification. Available: tr (top right), tl (top left),
 *                      tc (top center), br (bottom right), bl (bottom left), bc (bottom center)
 * autoDismiss:     (Boolean, Default true) Set if notification is dismissible by the user.
 * allowHtml:       (Boolean, Default false) Set to true if the text contains HTML, like buttons
 * closeCallback    (Function) A callback function that will be called when the notification is about to be removed.
 * openCallback     (Function) A callback function that will be called when the notification is successfully added.
 * dismissable      (Boolean, Default true) If show or not the button to close the notification
 * timer            (Number, Default 700) The timer to auto dismiss the notification
 */
import React, {useState, useRef, useEffect} from 'react'

import NotificationItem from './NotificationItem'
import CatToolStore from '../../stores/CatToolStore'
import CattolConstants from '../../constants/CatToolConstants'

const NotificationBox = () => {
  const [notifications, setNotifications] = useState([])
  const uid = useRef(300)
  const positions = {
    tl: 'tl',
    tr: 'tr',
    tc: 'tc',
    bl: 'bl',
    br: 'br',
    bc: 'bc',
  }

  const closeNotification = (notificationUid) => {
    setNotifications((prevState) => {
      const newNotifications = [...prevState].filter(
        (toCheck) => toCheck.uid !== notificationUid,
      )
      return [...newNotifications]
    })
  }
  const removeAllNotifications = () => {
    setNotifications((prevState) => {
      const newNotifications = [...prevState].map((n) => {
        n.remove = true
        return n
      })
      return [...newNotifications]
    })
  }

  useEffect(() => {
    //Add notification to the array, if it finds one with the same uid it replaces it
    const addNotification = (newNotification) => {
      newNotification.uid = newNotification.uid
        ? newNotification.uid
        : uid.current
      if (typeof newNotification.position === 'undefined') {
        newNotification.position = 'bl'
      }
      if (typeof newNotification.type === 'undefined') {
        newNotification.type = 'info'
      }
      uid.current++
      setNotifications((prevState) => {
        const newNotifications = [...prevState]
        const notificationIndex = newNotifications.findIndex(
          (n) => n.uid === newNotification.uid,
        )
        notificationIndex > -1 && newNotifications.splice(notificationIndex, 1)
        return [...newNotifications, newNotification]
      })
    }
    const removeNotification = (notification) => {
      setNotifications((prevState) => {
        const newNotifications = [...prevState].map((n) => {
          if (n.uid === notification.uid && !n.remove) {
            n.remove = true
          }
          return n
        })
        return [...newNotifications]
      })
    }

    CatToolStore.addListener(CattolConstants.ADD_NOTIFICATION, addNotification)
    CatToolStore.addListener(
      CattolConstants.REMOVE_NOTIFICATION,
      removeNotification,
    )
    CatToolStore.addListener(
      CattolConstants.REMOVE_ALL_NOTIFICATION,
      removeAllNotifications,
    )
    return () => {
      CatToolStore.removeListener(
        CattolConstants.ADD_NOTIFICATION,
        addNotification,
      )
      CatToolStore.removeListener(
        CattolConstants.REMOVE_NOTIFICATION,
        removeNotification,
      )
      CatToolStore.removeListener(
        CattolConstants.REMOVE_ALL_NOTIFICATION,
        removeAllNotifications,
      )
    }
  }, [])

  return (
    <div className="notifications-wrapper-inside">
      {notifications.length > 0
        ? Object.keys(positions).map((position, index) => {
            const _notifications = notifications.filter(function (
              notification,
            ) {
              return position === notification.position
            })

            if (_notifications.length) {
              let items = []
              _notifications.forEach((notification) => {
                const item = (
                  <NotificationItem
                    title={notification.title}
                    position={notification.position}
                    type={notification.type}
                    text={notification.text}
                    autoDismiss={notification.autoDismiss}
                    onRemove={closeNotification}
                    allowHtml={notification.allowHtml}
                    timer={notification.timer}
                    closeCallback={notification.closeCallback}
                    openCallback={notification.openCallback}
                    dismissable={notification.dismissable}
                    key={notification.uid}
                    uid={notification.uid}
                    remove={notification.remove}
                  />
                )
                items.push(item)
              })

              return (
                <div
                  key={index}
                  className={`notifications-position-${position}`}
                  id={'not-' + index}
                >
                  {items}
                </div>
              )
            }
          })
        : null}
    </div>
  )
}

export default NotificationBox
