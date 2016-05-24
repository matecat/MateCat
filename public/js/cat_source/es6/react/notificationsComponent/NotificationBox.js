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
 */

var NotificationItem = require('./NotificationItem').default;

class NotificationBox extends React.Component {


    constructor(props) {
        super(props);
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

        /*if (notification ) {
            notification.onRemove(notification);
        }*/

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
                     var items = [];
                    _notifications.forEach(function (notification, i) {
                        var item = <NotificationItem
                            title = {notification.title}
                            position = {notification.position}
                            type = {notification.type}
                            text = {notification.text}
                            autoDismiss={notification.autoDismiss}
                            onRemove={self.closeNotification}
                            allowHtml={notification.allowHtml}
                            closeCallback={notification.closeCallback}
                            openCallback={notification.openCallback}
                            key={notification.uid}
                            uid={notification.uid}
                        />;
                        items.push(item);
                    });
                    return <div key={index} className={ 'notifications-position-' + position } id={'not-' + index}> 
                            { items }
                          </div>
                }
            });
        }


        return (
            <div className="notifications-wrapper-inside">
                { containers }
            </div>

        );
    }
};



export default NotificationBox ;

