class PreferencesModal extends React.Component {


    constructor(props) {
        super(props);
    }

    openResetPassword() {
        $('#modal').trigger('openresetpassword');
    }

    componentWillMount() { }

    componentDidMount() {}

    render() {
        return <div>
                    <h1>Preferences</h1>
                    <div className="user-info-form">
                        <label htmlFor="user-login-name">Name</label><br/>
                        <input type="text" name="name" id="user-login-name" defaultValue="Federico"/><br/>
                        <label htmlFor="user-login-name">Surname</label><br/>
                        <input type="text" name="name" id="user-login-surname" defaultValue="Ricciuti"/><br/>
                        <label htmlFor="user-login-name">Email</label><br/>
                        <input type="text" name="name" id="user-login-email" defaultValue="federico@translated.net"/><br/>
                    </div>
                    <div className="user-reset-password">
                        <label>Reset Password</label>
                        <a className="reset-password btn-confirm-medium" onClick={this.openResetPassword.bind(this)}> Reset </a>
                    </div>
                    <div className="user-gdrive">
                        <div className="onoffswitch-drive">
                            <input type="checkbox" name="onoffswitch" className="onoffswitch-checkbox" id="gdrive_check"/>
                            <label className="onoffswitch-label" htmlFor="gdrive_check">
                                <span className="onoffswitch-inner"/>
                                <span className="onoffswitch-switch"/>
                                <span className="onoffswitch-label-status-active">ON</span>
                                <span className="onoffswitch-label-status-inactive">OFF</span>
                                <span className="onoffswitch-label-status-unavailable">Unavailable</span>
                            </label>
                        </div>
                        <label>Connect your Google Drive</label>
                    </div>
                    <br/>
                    <a className="btn-confirm-medium send-user-updates">Update preferences</a>
            </div>;
    }
}

export default PreferencesModal ;
