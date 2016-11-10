class LoginModal extends React.Component {


    constructor(props) {
        super(props);

    }

    openRegisterModal() {
        $('#modal').trigger('openregister');
    }

    openForgotPassword() {
        $('#modal').trigger('openforgotpassword');
    }


    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div>
                    <div className="login-container-right">
                        <h2>All the advatnages</h2>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        <ul className="">
                            <li>Lorem ipsum dolor sit amet</li>
                            <li>Lorem ipsum dolor sit amet</li>
                            <li>Lorem ipsum dolor sit amet</li>
                        </ul>
                        <a className="register-button btn-confirm-medium" onClick={this.openRegisterModal}> Register now </a>
                    </div>
                    <div className="login-container-left">
                        <h2>Log in</h2>
                        <a className="google-login btn-confirm-medium"> Google login </a>
                        <div className="login-form-container">
                            <input type="text" name="email" placeholder="email" /><br/>
                            <input type="text" name="password" placeholder="password" /><br/>
                            <a className="login-button btn-confirm-medium"> Log in </a><br/>
                            <span className="forgot-password" onClick={this.openForgotPassword}>Forgot password?</span>
                        </div>
                    </div>
                </div>;
    }
}

export default LoginModal ;
