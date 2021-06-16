import React from 'react'
import PropTypes from 'prop-types'

class NotificationItem extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      visible: false,
      removed: false,
    }
    this._isMounted = false
    this.dismissNotification = this.dismissNotification.bind(this)
    this.hideNotification = this.hideNotification.bind(this)

    this.styleNameContainer = 'notification-type-' + this.props.type
    this.styleNameTitle = 'notification-title-' + this.props.type
  }

  dismissNotification() {
    /*if (!this.props.autoDismiss) {
            return;
        }*/
    this.hideNotification()
  }

  hideNotification() {
    var self = this
    if (this._notificationTimer) {
      clearTimeout(this._notificationTimer)
    }

    if (this._isMounted) {
      this.setState({
        visible: false,
        removed: true,
      })
      this.props.hideMateCat(self.props.uid)
    }
    setTimeout(function () {
      self.props.onRemove(self.props.uid)
    }, 1000)

    if (this.props.closeCallback) {
      this.props.closeCallback.call()
    }
  }

  componentDidMount() {
    this._isMounted = true
    var self = this
    setTimeout(function () {
      self.setState({
        visible: true,
      })
      self.props.showMateCat()
    }, 50)

    if (this.props.autoDismiss) {
      this._notificationTimer = setTimeout(function () {
        self.hideNotification()
      }, this.props.timer)
    }
    if (this.props.openCallback) {
      this.props.openCallback.call()
    }
  }

  allowHTML(string) {
    return {__html: string}
  }

  getCssPropertyByPosition() {
    var position = this.props.position
    var css = {}

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

  render() {
    var autoDismiss,
      message,
      title = null
    var notificationStyle = {}
    var cssByPos = this.getCssPropertyByPosition()
    if (!this.state.visible && !this.state.removed) {
      notificationStyle[cssByPos.property] = cssByPos.value
    }

    if (this.state.visible && !this.state.removed) {
      notificationStyle[cssByPos.property] = 0
      notificationStyle.opacity = 1
    }

    if (this.state.removed) {
      notificationStyle.overflow = 'hidden'
      notificationStyle.opacity = 0
      notificationStyle[cssByPos.property] = cssByPos.value
      notificationStyle.height = 0
      notificationStyle.marginTop = 0
      notificationStyle.paddingTop = 0
      notificationStyle.paddingBottom = 0
      notificationStyle.borderTop = 0
    }

    if (this.props.dismissable) {
      autoDismiss = (
        <span
          className={'notification-close-button'}
          onClick={this.dismissNotification}
        >
          ×
        </span>
      )
    }
    if (this.props.allowHtml) {
      title = (
        <div
          className={this.styleNameTitle}
          dangerouslySetInnerHTML={this.allowHTML(this.props.title)}
        ></div>
      )
      message = (
        <div
          className={'notification-message'}
          dangerouslySetInnerHTML={this.allowHTML(this.props.text)}
        ></div>
      )
    } else {
      title = <div className={this.styleNameTitle}> {this.props.title}</div>
      message = <div className={'notification-message'}>{this.props.text}</div>
    }
    return (
      <div className={this.styleNameContainer} style={notificationStyle}>
        {autoDismiss}
        {title}
        {message}
      </div>
    )
  }
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
