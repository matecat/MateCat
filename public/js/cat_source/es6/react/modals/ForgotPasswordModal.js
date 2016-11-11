class ForgotPasswordModal extends React.Component {


    constructor(props) {
        super(props);

    }

    openLoginModal() {
        $('#modal').trigger('openlogin');
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div className="forgot-password-modal">
            <h2>Forgot Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <input type="text" name="email" placeholder="Email" /><br/>
            <a className="send-password-button btn-confirm-medium" > Send </a> <br/>
            <span className="forgot-password" onClick={this.openLoginModal}>Back to login</span>
        </div>;
    }
}

export default ForgotPasswordModal ;
