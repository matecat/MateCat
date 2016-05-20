var Styles = require("./style").default;
var NotificationItem = require('./NotificationItem').default;

class NotificationBox extends React.Component {


    constructor(props) {
        super(props);
        this._getStyles =  new GetStyles();
        this.state = {
            notifications: []
        };
        this.positions = {
            tl: 'tl',
                tr: 'tr',
                tc: 'tc',
                bl: 'bl',
                br: 'br',
                bc: 'bc'
        };
        this.uid = 3000;
        this.closeNotification = this.closeNotification.bind(this);
    }

    closeNotification(uid) {
        var notification;
        var notifications = this.state.notifications.filter(function(toCheck) {
            if (toCheck.uid === uid) {
                notification = toCheck;
            }
            return toCheck.uid !== uid;
        });

        if (notification && notification.onRemove) {
            notification.onRemove(notification);
        }

        this.setState({ notifications: notifications });
    }

    addNotification(newNotification) {
        var notifications = this.state.notifications;
        newNotification.uid = this.uid;
        this.uid++;
        notifications.push(newNotification);
        this.setState({
            notifications: notifications
        });
    }

    render() {
        var self = this;
        var containers = null;
        var notifications = this.state.notifications;

        if (notifications.length) {
            containers = Object.keys(this.positions).map(function(position, index) {
                var _notifications = notifications.filter(function(notification) {
                    return position === notification.position;
                });

                if (_notifications.length) {
                     var _style = self._getStyles.container(position);
                     var items = [];
                    _notifications.forEach(function (notification, i) {
                        var item = <NotificationItem
                            title = {notification.title}
                            position = {notification.position}
                            type = {notification.type}
                            text = {notification.text}
                            getStyles={ self._getStyles }
                            dismiss={notification.dismiss}
                            onRemove={self.closeNotification}
                            allowHtml={notification.allowHtml}
                            key={i}
                            uid={notification.uid}
                        />;
                        items.push(item);
                    });
                    return <div key={index} className={ 'notifications-' + position } style={_style } >
                            { items }
                          </div>
                }
            });
        }


        return (
            <div className="notifications-wrapper-inside" style={ this._getStyles.wrapper() }>
                { containers }
            </div>

        );
    }
};



export default NotificationBox ;

function GetStyles() {
    this.overrideStyle = {};

    this.overrideWidth = null;

    this.setOverrideStyle = function(style) {
        this.overrideStyle = style;
    };

    this.wrapper = function() {
        if (!this.overrideStyle) return {};
        return $.extend({}, Styles.Wrapper, this.overrideStyle.Wrapper);
    };

    this.container = function(position) {
        var override = this.overrideStyle.Containers || {};
        if (!this.overrideStyle) return {};

        this.overrideWidth = Styles.Containers.DefaultStyle.width;

        if (override.DefaultStyle && override.DefaultStyle.width) {
            this.overrideWidth = override.DefaultStyle.width;
        }

        if (override[position] && override[position].width) {
            this.overrideWidth = override[position].width;
        }

        return $.extend({}, Styles.Containers.DefaultStyle, Styles.Containers[position], override.DefaultStyle, override[position]);
    };

    this.elements = {
        notification: 'NotificationItem',
            title: 'Title',
            messageWrapper: 'MessageWrapper',
            dismiss: 'Dismiss',
            action: 'Action',
            actionWrapper: 'ActionWrapper'
    };

    this.byElement = function(element) {
        var self = this;
        return function(level) {
            var _element = self.elements[element];
            var override = self.overrideStyle[_element] || {};
            if (!self.overrideStyle) return {};
            return $.extend({}, Styles[_element].DefaultStyle, Styles[_element][level], override.DefaultStyle, override[level]);
        };
    };
};
