/**
 * React Component to add notifications to Matecat.
 * You can add new notifications calling the addNotification method passing a
 * notification object with the following properties
 *
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

import NotificationItem from './NotificationItem'

class NotificationBox extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      notifications: [],
      catVisible: false,
    }
    this.positions = {
      tl: 'tl',
      tr: 'tr',
      tc: 'tc',
      bl: 'bl',
      br: 'br',
      bc: 'bc',
    }
    this.uid = 3000
    this.closeNotification = this.closeNotification.bind(this)
    this.hideMateCat = this.hideMateCat.bind(this)
    this.showMateCat = this.showMateCat.bind(this)
  }

  closeNotification(uid) {
    var notification
    var notifications = this.state.notifications.filter(function (toCheck) {
      if (toCheck.uid === uid) {
        notification = toCheck
      }
      return toCheck.uid !== uid
    })

    this.setState({notifications: notifications})
  }

  addNotification(newNotification) {
    var notifications = this.state.notifications
    newNotification.uid = this.uid
    newNotification.dismissed = false
    if (typeof newNotification.position === 'undefined') {
      newNotification.position = 'bl'
    }
    if (typeof newNotification.type === 'undefined') {
      newNotification.type = 'info'
    }
    this.uid++
    notifications.push(newNotification)
    this.setState({
      notifications: notifications,
    })
    return newNotification
  }
  removeNotification(notification) {
    let self = this
    let containerToDelete = 'container-' + notification.uid
    Object.keys(this.refs).forEach(function (container) {
      if (container == containerToDelete) {
        self.refs[container].hideNotification()
      }
    })
  }

  removeAllNotifications() {
    let self = this
    Object.keys(this.refs).forEach(function (container) {
      self.refs[container].hideNotification()
    })
  }
  showMateCat() {
    this.setState({
      catVisible: true,
    })
  }

  hideMateCat(uid) {
    var catVisible = this.state.catVisible
    var notifications = this.state.notifications.filter(function (
      notification,
    ) {
      if (notification.uid === uid) {
        notification.dismissed = true
      }
      return notification
    })

    var notificationsBottomLeft = notifications.filter(function (notification) {
      return !notification.dismissed && notification.position === 'bl'
    })
    if (notificationsBottomLeft.length == 0) {
      catVisible = false
    }
    this.setState({
      notifications: notifications,
      catVisible: catVisible,
    })
    /*if (bottomLeftNot.length === 1) {
            this.setState({
                catVisible: false
            });
        }*/
  }

  render() {
    var self = this
    var containers = null
    var notifications = this.state.notifications
    var catStyle = {}

    if (notifications.length) {
      containers = Object.keys(this.positions).map(function (position, index) {
        var _notifications = notifications.filter(function (notification) {
          return position === notification.position
        })

        if (_notifications.length) {
          var items = []
          var cat = ''
          _notifications.forEach(function (notification) {
            var item = (
              <NotificationItem
                ref={'container-' + notification.uid}
                title={notification.title}
                position={notification.position}
                type={notification.type}
                text={notification.text}
                autoDismiss={notification.autoDismiss}
                onRemove={self.closeNotification}
                allowHtml={notification.allowHtml}
                timer={notification.timer}
                closeCallback={notification.closeCallback}
                openCallback={notification.openCallback}
                dismissable={notification.dismissable}
                key={notification.uid}
                uid={notification.uid}
                hideMateCat={self.hideMateCat}
                showMateCat={self.showMateCat}
              />
            )
            items.push(item)
          })
          if (position === 'bl' && _notifications[0].type === 'info') {
            if (self.state.catVisible) {
              catStyle.bottom = '0px'
              catStyle.opacity = 1
            } else {
              catStyle.bottom = '-200px'
              catStyle.opacity = 0
              catStyle.height = 0
            }
            cat = (
              <div className="notifications-cat-smiling" style={catStyle}></div>
            )
          }

          return (
            <div
              key={index}
              className={'notifications-position-' + position}
              id={'not-' + index}
            >
              {cat}
              {items}
            </div>
          )
        }
      })
    }

    return <div className="notifications-wrapper-inside">{containers}</div>
  }
}

export default NotificationBox
