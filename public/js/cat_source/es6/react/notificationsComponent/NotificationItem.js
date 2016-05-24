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
        /*if (!this.props.autoDismiss) {
            return;
        }*/
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
        
        if (this.props.closeCallback) {
            this.props.closeCallback.call();
        }

    }

    componentWillMount() {
        this.styleNameContainer =  "notification-type-" + this.props.type;
        this.styleNameTitle =  "notification-title-" + this.props.type;
    }

    componentDidMount() {
        this._isMounted = true;
        var self = this;
        setTimeout(function() {
            self.setState({
                visible: true
            });
        }, 50);

        if (this.props.autoDismiss) {
            this._notificationTimer = setTimeout(function() {
                self.hideNotification();
            }, 5000);
        }
        if (this.props.openCallback) {
            this.props.openCallback.call();
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    getCssPropertyByPosition() {
        var position = this.props.position;
        var css = {};

        switch (position) {
            case "bl":
            case "bc":
            case "br":
                css = {
                    property: 'bottom',
                    value: -200
                };
                break;
            case "tl":
            case "tr":
            case "tc":
                css = {
                    property: 'top',
                    value: -200
                };
                break;
            default:
        }

        return css;
    }

    render() {
        var autoDismiss, message = null;
        var notificationStyle = {};
        var cssByPos = this.getCssPropertyByPosition();
        if (!this.state.visible && !this.state.removed) {
            notificationStyle[cssByPos.property] = cssByPos.value;
        }

        if (this.state.visible && !this.state.removed) {
            notificationStyle[cssByPos.property] = 0;
            notificationStyle.opacity = 1;

        }

        if (this.state.removed) {
            notificationStyle.overflow = 'hidden';
            notificationStyle.opacity = 0;
            notificationStyle[cssByPos.property] = cssByPos.value;
            notificationStyle.height = 0;
            notificationStyle.marginTop = 0;
            notificationStyle.paddingTop = 0;
            notificationStyle.paddingBottom = 0;
        }

        // if (!this.props.autoDismiss) {
        autoDismiss = <span className={'notification-close-button'} onClick={this.dismissNotification}>×</span>;
        // }
        if (this.props.allowHtml) {
            message = <div className= {'notification-message'} dangerouslySetInnerHTML={ this.allowHTML(this.props.text) }></div>;
        } else {
            message = <div className= {'notification-message'} >{this.props.text}</div>;
        }
        return <div className={this.styleNameContainer} style={notificationStyle}>
            {autoDismiss}
            <h2 className={this.styleNameTitle} > {this.props.title}</h2>
            {message}
        </div> ;
    }
}

NotificationItem.propTypes = {
    position: React.PropTypes.string,
    title: React.PropTypes.string.isRequired,
    text: React.PropTypes.string.isRequired,
    type: React.PropTypes.string,
    autoDismiss: React.PropTypes.bool,
    closeCallback: React.PropTypes.func,
    openCallback: React.PropTypes.func,
    allowHtml: React.PropTypes.bool
};

NotificationItem.defaultProps = {
    position: "bl",
    type: "info",
    autoDismiss: true,
    allowHtml: false
};

export default NotificationItem ;
