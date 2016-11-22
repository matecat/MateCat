class PreferencesModal extends React.Component {


    constructor(props) {
        super(props);
    }

    openResetPassword() {
        $('#modal').trigger('openresetpassword');
    }

    componentWillMount() { }

    componentDidMount() {}

    checkboxChange() {
        var url = config.gdriveAuthURL ;
        var newWindow = window.open( url, 'name', 'height=600,width=900' );

        if ( window.focus ) {
            newWindow.focus();
        }
        var interval = setInterval( function() {
            if ( newWindow.closed ) {
                APP.USER.loadUserData();
                clearInterval( interval ) ;
            }
        }, 600 );
    }

    render() {
        var gdriveMessage = '';
        if (this.props.showGDriveMessage) {
            gdriveMessage = <div className="preference-modal-message">
                Connect a google drive account to add files
            </div>;
        }
        return <div className="preferences-modal">
                    <h1>Preferences</h1>
                    <div className="user-info-form">
                        <label htmlFor="user-login-name">Name</label><br/>
                        <input type="text" name="name" id="user-login-name" defaultValue="Federico" disabled="true"/><br/>
                        <label htmlFor="user-login-name">Surname</label><br/>
                        <input type="text" name="name" id="user-login-surname" defaultValue="Ricciuti" disabled="true"/><br/>
                        <label htmlFor="user-login-name">Email</label><br/>
                        <input type="text" name="name" id="user-login-email" defaultValue="federico@translated.net" disabled="true"/><br/>
                    </div>
                    <div className="user-reset-password">
                        {gdriveMessage}
                        <label>Reset Password</label>
                        <a className="reset-password btn-confirm-medium" onClick={this.openResetPassword.bind(this)}> Reset </a>
                    </div>
                    <div className="user-gdrive">
                        <div className="onoffswitch-drive">
                            <input type="checkbox" name="onoffswitch" onChange={this.checkboxChange} className="onoffswitch-checkbox" id="gdrive_check"/>
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
