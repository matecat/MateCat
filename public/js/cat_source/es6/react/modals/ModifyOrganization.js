let Immutable = require('immutable');
class ModifyOrganization extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization,
            inputUserError: false
        };
        this.updateOrganization = this.updateOrganization.bind(this);
    }

    updateOrganization(organization) {
        this.setState({
            organization: organization
        });
    }



    removeUser(userId) {
        ManageActions.removeUserFromOrganization(this.state.organization.toJS(), userId);
    }

    handleKeyPress(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if ( APP.checkEmail(this.inputNewUSer.value)) {

                ManageActions.addUserToOrganization(this.state.organization.toJS(), this.inputNewUSer.value);

                this.inputNewUSer.value = '';
            } else {
                this.setState({
                    inputUserError: true
                });
            }
        }
        return false;
    }

    getUserList() {

        return this.state.organization.get('members').map((user, i) => (
            <div className="item"
               key={'user' +  user.get('uid')}>
                <div className="right floated content">
                    <div className="ui button" onClick={this.removeUser.bind(this, user.get('uid'))}>Remove</div>
                </div>
                <div className="ui avatar image initials green">??</div>
                <div className="content">
                    {user.get('first_name') + ' ' + user.get('last_name')}
                </div>
            </div>
        ));

    }

    componentDidMount() {
        OrganizationsStore.addListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);

    }

    componentWillUnmount() {
        OrganizationsStore.removeListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);

    }

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.organization !== this.state.organization)
    }

    render() {
        var usersError = (this.state.inputUserError) ? 'error' : '';
        let userlist = this.getUserList();
        return <div className="modify-organization-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Change Organization Name</h3>
                        <div className="ui large fluid icon input">
                            <input type="text" defaultValue={this.state.organization.get('name')}/>
                            <i className="icon-pencil icon"/>
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-middle">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Add member</h3>
                        <div className={"ui large fluid icon input " + usersError }>
                            <input type="text" placeholder="Ex: joe@email.com"
                                   onKeyPress={this.handleKeyPress.bind(this)}
                                   ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                        </div>
                    </div>
                    <div className="column">
                        <div className="ui segment members-list">
                            <div className="ui middle aligned divided list">
                                {userlist}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ModifyOrganization ;