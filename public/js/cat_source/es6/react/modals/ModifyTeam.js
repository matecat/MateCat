let Immutable = require('immutable');
class ModifyTeam extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            team: this.props.team,
            inputUserError: false,
            inputNameError: false,
            showRemoveMessageUserID: null,
            readyToSend: false,
        };
        this.updateTeam = this.updateTeam.bind(this);
    }

    updateTeam(team) {
        if (this.state.team.get('id') == team.get('id')) {
            this.setState({
                team: team
            });
        }
    }

    showRemoveUser(userId) {
        this.setState({
            showRemoveMessageUserID: userId
        });
    }

    removeUser(user) {
        ManageActions.removeUserFromTeam(this.state.team, user);
        if (user.get('uid') === APP.USER.STORE.user.uid) {
            APP.ModalWindow.onCloseModal();
        }
        this.setState({
            showRemoveMessageUserID: null
        });
    }

    undoRemoveAction() {
        this.setState({
            showRemoveMessageUserID: null
        });
    }

    resendInvite(mail) {
        ManageActions.addUserToTeam(this.state.team, mail);
    }

    handleKeyPressUserInput(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if ( APP.checkEmail(this.inputNewUSer.value)) {
                ManageActions.addUserToTeam(this.state.team, this.inputNewUSer.value);
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
           this.setState({
               inputNameError: false,
           });
    }

    changeTeamName() {
        if (this.inputName.value.length > 0 && this.inputName.value != this.state.team.get('name')) {
            ManageActions.changeTeamName(this.state.team.toJS(), this.inputName.value);
            $(this.inputName).blur();
            this.setState({
                readyToSend: true
            });
            APP.ModalWindow.onCloseModal();
        } else {
            this.setState({
                inputNameError: true
            });
        }

    }

    getUserList() {
        let self = this;

        return this.state.team.get('members').map(function(member, i) {
            let user = member.get('user');
            if (user.get('uid') == APP.USER.STORE.user.uid && self.state.showRemoveMessageUserID == user.get('uid')) {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="right floated content top-1 bottom-1">
                        <div className="ui button green" onClick={self.removeUser.bind(self, user)}>YES</div>
                        <div className="ui button red" onClick={self.undoRemoveAction.bind(self)}>NO</div>
                    </div>
                    <div className="content pad-top-6 pad-bottom-8">
                        Are you sure you want to leave this team?
                    </div>
                </div>
            }else if (self.state.showRemoveMessageUserID == user.get('uid')) {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="right floated content top-1 bottom-1">
                        <div className="mini ui button green" onClick={self.removeUser.bind(self, user)}>YES</div>
                        <div className="mini ui button red" onClick={self.undoRemoveAction.bind(self)}>NO</div>
                    </div>
                    <div className="content pad-top-6 pad-bottom-8">
                        Are you sure you want to remove this user?
                    </div>
                </div>
            } else {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="mini ui basic button right floated" onClick={self.showRemoveUser.bind(self, user.get('uid'))}>Remove</div>
                    <div className="ui tiny image label">{APP.getUserShortName(user.toJS())}</div>
                    <div className="middle aligned content">
                        <div className="content user">
                            {' ' + user.get('first_name') + ' ' + user.get('last_name')}
                        </div>
                        <div className="content email-user-invited">{user.get('email')}</div>
                    </div>

                </div>
            }

        });

    }

    getPendingInvitations() {
        let self = this;
        if (!this.state.team.get('pending_invitations').size > 0) return;
        return this.state.team.get('pending_invitations').map(function(mail, i) {
            return <div className="item pending-invitation"
                         key={'user-invitation' + i}>
                        <div className="mini ui basic button right floated"
                             onClick={self.resendInvite.bind(self, mail)}>Resend Invite</div>
                        <div className="ui right floated content pending-msg">Pending user</div>
                        <div className="ui tiny image label">{mail.substring(0, 1).toUpperCase()}</div>
                        <div className="middle aligned content">
                            <div className="content user">
                                {mail}
                            </div>
                        </div>

                    </div>;
        });

    }

    componentDidUpdate() {
        var self = this;
        clearTimeout(this.inputTimeout);
        if (this.state.readyToSend) {
            this.inputTimeout = setTimeout(function () {
                self.setState({
                    readyToSend: false
                })
            }, 3000);
        }
    }

    componentDidMount() {
        TeamsStore.addListener(ManageConstants.UPDATE_TEAM, this.updateTeam);
    }

    componentWillUnmount() {
        TeamsStore.removeListener(ManageConstants.UPDATE_TEAM, this.updateTeam);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.team !== this.state.team ||
                nextState.inputUserError !== this.state.inputUserError ||
                nextState.inputNameError !== this.state.inputNameError ||
                nextState.showRemoveMessageUserID !== this.state.showRemoveMessageUserID ||
                nextState.readyToSend !== this.state.readyToSend
        )
    }

    render() {
        let usersError = (this.state.inputUserError) ? 'error' : '';
        let orgNameError = (this.state.inputNameError) ? 'error' : '';
        let userlist = this.getUserList();
        let pendingUsers = this.getPendingInvitations();
        let icon = (this.state.readyToSend && !this.state.inputNameError ) ?<i className="icon-checkmark green icon"/> : <i className="icon-pencil icon"/>;

        return <div className="modify-team-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h2>Change Team Name</h2>
                        <div className={"ui fluid icon input " + orgNameError}>
                            <input type="text" defaultValue={this.state.team.get('name')}
                            onKeyUp={this.onKeyPressEvent.bind(this)}
                            ref={(inputName) => this.inputName = inputName}/>
                            {icon}
                        </div>
                    </div>
                </div>
            </div>
            { this.state.team.get('type') !== "personal" ? (
                    <div className="matecat-modal-middle">
                        <div className="ui grid left aligned">
                            <div className="sixteen wide column">
                                <h2>Add Members</h2>
                                <div className={"ui fluid icon input " + usersError }>
                                    <input type="text" placeholder="name@email.com"
                                           onKeyUp={this.handleKeyPressUserInput.bind(this)}
                                           ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                                </div>
                                {this.state.inputUserError ? (
                                        <div className="validation-error"><span className="text" style={{color: 'red', fontSize: '14px'}}>Email is required</span></div>
                                    ): ''}
                            </div>



                            <div className="sixteen wide column">
                                <div className="ui members-list team">
                                    <div className="ui divided list">
                                        {pendingUsers}
                                        {userlist}
                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>
                ) : ('')}
                    <div className="matecat-modal-bottom">
                        <div className="ui one column grid right aligned">
                            <div className="column">
                                <button className="create-team ui primary button open"
                                onClick={this.changeTeamName.bind(this)}>Confirm</button>
                            </div>
                        </div>
                    </div>

        </div>;
    }
}


export default ModifyTeam ;