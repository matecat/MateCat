class NotificationBox extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            is_pass : null,
            score: null,
            vote: this.props.vote
        };
    }

    show() {

    }

    render() {

        return <div>Notification Box</div> ;
    }
}

export default NotificationBox ;
