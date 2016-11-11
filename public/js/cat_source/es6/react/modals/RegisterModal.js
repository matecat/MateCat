class RegisterModal extends React.Component {


    constructor(props) {
        super(props);

    }

    openLoginModal() {
        $('#modal').trigger('openlogin');
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
        return <div className="register-modal">
            <h2>Register</h2>
            <a className="google-login btn-confirm-medium" onClick={this.googole_popup.bind(this)}> Google login </a>
            <p>By clicking you accept terms and conditions</p>
            <div className="register-form-container">
                <h2>Register with your email</h2>
                <input type="text" name="name" placeholder="Name" /><br/>
                <input type="text" name="surname" placeholder="Surname" /><br/>
                <input type="text" name="email" placeholder="email" /><br/>
                <input type="text" name="password" placeholder="password" /><br/>
                <input type="checkbox" id="check-conditions" name="terms"/>
                <label htmlFor="check-conditions">Accept terms and conditions</label><br/>
                <a className="register-submit btn-confirm-medium"> Register Now </a><br/>
                <span style={{cursor:'pointer'}} onClick={this.openLoginModal}>Already registered? Login</span>
            </div>
        </div>;
    }
}

export default RegisterModal ;
