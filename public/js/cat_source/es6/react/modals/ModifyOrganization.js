let Immutable = require('immutable');
class ModifyOrganization extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization,
            inputUserError: false,
            inputNameError: false,
            showRemoveMessageUserID: null
        };
        this.updateOrganization = this.updateOrganization.bind(this);
    }

    updateOrganization(organization) {
        if (this.state.organization.get('id') == organization.get('id')) {
            this.setState({
                organization: organization
            });
        }
    }

    showRemoveUser(userId) {
        this.setState({
            showRemoveMessageUserID: userId
        });
    }

    removeUser(userId) {
        ManageActions.removeUserFromOrganization(this.state.organization, userId);
        if (userId === APP.USER.STORE.user.uid) {
            APP.ModalWindow.onCloseModal();
        }
    }

    undoRemoveAction() {
        this.setState({
            showRemoveMessageUserID: null
        });
    }

    handleKeyPress(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if ( APP.checkEmail(this.inputNewUSer.value)) {
                ManageActions.addUserToOrganization(this.state.organization, this.inputNewUSer.value);
                this.inputNewUSer.value = '';
            } else {
                this.setState({
                    inputUserError: true
                });
            }
        } else {
            this.setState({
                inputUserError: false
            });
        }
        return false;
    }


    onKeyPressEvent(e) {
       if (e.key === 'Enter' ) {
            if (this.inputName.value.length > 0) {
                ManageActions.changeOrganizationName(this.state.organization.toJS(), this.inputName.value);
                $(this.inputName).blur();
            } else {
                this.setState({
                    inputNameError: true
                });
            }
            return false;
        } else {
           this.setState({
               inputNameError: false
           });
       }
    }


    getUserList() {
        let self = this;
        return this.state.organization.get('members').map(function(member, i) {
            let user = member.get('user');
            if (user.get('uid') == APP.USER.STORE.user.uid && self.state.showRemoveMessageUserID == user.get('uid')) {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="right floated content top-1 bottom-1">
                        <div className="ui button green" onClick={self.removeUser.bind(self, user.get('uid'))}>YES</div>
                        <div className="ui button red" onClick={self.undoRemoveAction.bind(self)}>NO</div>
                    </div>
                    <div className="content pad-top-6 pad-bottom-8">
                        Are you sure you want to leave this organization?
                    </div>
                </div>
            }else if (self.state.showRemoveMessageUserID == user.get('uid')) {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="right floated content top-1 bottom-1">
                        <div className="mini ui button green" onClick={self.removeUser.bind(self, user.get('uid'))}>YES</div>
                        <div className="mini ui button red" onClick={self.undoRemoveAction.bind(self)}>NO</div>
                    </div>
                    <div className="content pad-top-6 pad-bottom-8">
                        Are you sure you want to remove this user?
                    </div>
                </div>
            } else {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="ui circular label top-3 bottom-3">{APP.getUserShortName(user.toJS())}</div>
                    <span className="content">
                        {' ' + user.get('first_name') + ' ' + user.get('last_name')}
                    </span>
                    <div className="right floated content top-2">
                        <div className="mini ui button" onClick={self.showRemoveUser.bind(self, user.get('uid'))}>Remove</div>
                    </div>
                </div>
            }

        });

    }

    componentDidMount() {
        OrganizationsStore.addListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    componentWillUnmount() {
        OrganizationsStore.removeListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.organization !== this.state.organization ||
                nextState.inputUserError !== this.state.inputUserError ||
                nextState.inputNameError !== this.state.inputNameError ||
                nextState.showRemoveMessageUserID !== this.state.showRemoveMessageUserID)
    }

    render() {
        let usersError = (this.state.inputUserError) ? 'error' : '';
        let orgNameError = (this.state.inputNameError) ? 'error' : '';
        let userlist = this.getUserList();
        return <div className="modify-organization-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Change Organization Name</h3>
                        <div className={"ui fluid icon input " + orgNameError}>
                            <input type="text" defaultValue={this.state.organization.get('name')}
                            onKeyPress={this.onKeyPressEvent.bind(this)}
                            ref={(inputName) => this.inputName = inputName}/>
                            <i className="icon-pencil icon"/>
                            {/*<i class="icon-checkmark green icon"></i>*/}
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-middle">
                <div className="ui grid left aligned">
                    <div className="sixteen wide column">
                        <h3>Add members</h3>
                        <div className={"ui fluid icon input " + usersError }>
                            <input type="text" placeholder="joe@email.com"
                                   onKeyPress={this.handleKeyPress.bind(this)}
                                   ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                        </div>
                    </div>
                    <div className="sixteen wide column">
                        <div className="ui members-list">
                            <div className="ui divided list">
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