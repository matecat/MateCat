class NotificationItem extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            visible: false,
            removed: false
        };
        this._isMounted = false;
        this.dismissNotification = this.dismissNotification.bind(this);
        this.hideNotification = this.hideNotification.bind(this);
    }

    dismissNotification() {
        if (!this.props.dismiss) {
            return;
        }

        this.hideNotification();
    }

    hideNotification() {
        var self = this;
        if (this._notificationTimer) {
            clearTimeout(this._notificationTimer);
        }

        if (this._isMounted) {
            this.setState({
                visible: false,
                removed: true
            });
        }
        setTimeout(function () {
            self.props.onRemove(self.props.uid);
        }, 5000);

    }

    componentWillMount() {
        var getStyles = this.props.getStyles;
        var level = this.props.type;


        this.styleNameContainer =  "notification-" + this.props.type;
        this.styleNameTitle =  "notification-title-" + this.props.type;

        this.containerStyle = getStyles.byElement('notification')(level);
        this.titleStyle = getStyles.byElement('title')(level);
    }

    componentDidMount() {
        this._isMounted = true;
        var self = this;
        setTimeout(function() {
            self.setState({
                visible: true
            });
        }, 50);

        if (this.props.dismiss) {
            this._notificationTimer = setTimeout(function() {
                self.hideNotification();
            }, 5000);
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var dismiss, message = null;
        var notificationStyle = $.extend({}, this.containerStyle);
        if (!this.state.visible && !this.state.removed) {
            notificationStyle.left = "-200px";
        }

        if (this.state.visible && !this.state.removed) {
            notificationStyle.left = 0;
            notificationStyle.opacity = 1;

        }

        if (this.state.removed) {
            notificationStyle.overlay = 'hidden';
            notificationStyle.height = 0;
            notificationStyle.marginTop = 0;
            notificationStyle.paddingTop = 0;
            notificationStyle.paddingBottom = 0;
        }

        if (this.props.dismiss) {
            dismiss = <span class='notification-close' style={this.props.getStyles.byElement('dismiss')()} onClick={this.dismissNotification}>x</span>;
        }
        if (this.props.allowHtml) {
            message = <div class='notification-message' dangerouslySetInnerHTML={ this.allowHTML(this.props.text) }></div>;
        } else {
            message = <div class='notification-message' >{this.props.text}</div>;
        }
        return <div className={this.styleNameContainer} style={notificationStyle}>
            {dismiss}
            <h2 className={this.styleNameTitle} style={this.titleStyle}> {this.props.title}</h2>
            {message}
        </div> ;
    }
}

NotificationItem.propTypes = {
    position: React.PropTypes.string,
    title: React.PropTypes.string.isRequired,
    text: React.PropTypes.string.isRequired,
    type: React.PropTypes.string,
    dismiss: React.PropTypes.bool,
    closeCallback: React.PropTypes.func,
    allowHtml: React.PropTypes.bool
};

NotificationItem.defaultProps = {
    position: "bl",
    type: "info",
    dismiss: true,
    allowHtml: false
};

export default NotificationItem ;
