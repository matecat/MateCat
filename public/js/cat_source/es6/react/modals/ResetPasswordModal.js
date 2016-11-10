class ResetPasswordModal extends React.Component {


    constructor(props) {
        super(props);

    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div className="reset-password-modal">
            <h2>Reset Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <input type="text" name="password" placeholder="New password" /><br/>
            <input type="text" name="new_password" placeholder="Repeat again" /><br/>
            <a className="reset-password-button btn-confirm-medium" > Reset </a> <br/>
        </div>;
    }
}

export default ResetPasswordModal ;
