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

    googole_popup(  ) {
        //var rid=$('#rid').text();
        //url=url+'&rid='+rid;
        var url = this.props.googleUrl;
        var newWindow = window.open( url, 'name', 'height=600,width=900' );
        if ( window.focus ) {
            newWindow.focus();
        }
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div className="login-modal">
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
                        <a className="google-login-button btn-confirm-medium" onClick={this.googole_popup.bind(this)}> Google login </a>
                        <div className="login-form-container">
                            <input type="text" name="email" placeholder="Email" /><br/>
                            <input type="text" name="password" placeholder="Password" /><br/>
                            <a className="login-button btn-confirm-medium"> Log in </a><br/>
                            <span className="forgot-password" onClick={this.openForgotPassword}>Forgot password?</span>
                        </div>
                    </div>
                </div>;
    }
}


LoginModal.propTypes = {
    googleUrl: React.PropTypes.string
};

export default LoginModal ;
