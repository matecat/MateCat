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
            usersToAdd: []
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

    cancelInvite(mail) {
        var newArray = this.state.usersToAdd.slice();
        var index = newArray.indexOf(mail);
        if (index > -1) {
            newArray.splice(index, 1);this.setState({
                usersToAdd: newArray
            });

        }
    }

    handleKeyPressUserInput(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            this.addUser();
        } else {
            this.setState({
                inputUserError: false
            });
        }
        return false;
    }

    /*addTemporaryUser() {
        if ( APP.checkEmail(this.inputNewUSer.value)) {
            // this.state.usersToAdd.push(this.inputNewUSer.value);
            let arrayNewUsers = this.state.usersToAdd.slice();
            arrayNewUsers.push(this.inputNewUSer.value);
            this.setState({
                usersToAdd: arrayNewUsers
            });
            this.inputNewUSer.value = '';
        } else {
            this.setState({
                inputUserError: true
            });
        }
    }*/

    /*addUser() {
        ManageActions.addUserToTeam(this.state.team, this.state.usersToAdd);
        return true;
    }*/

    addUser() {
        if ( APP.checkEmail(this.inputNewUSer.value)) {
            ManageActions.addUserToTeam(this.state.team, this.inputNewUSer.value);
            this.inputNewUSer.value = '';
            return true;
        } else {
            this.setState({
                inputUserError: true
            });
            return false;
        }
    }


    onKeyPressEvent(e) {
        if (e.key === 'Enter' ) {
            this.changeTeamName();
            return false;
        } else if (this.inputName.value.length == 0) {
            this.setState({
                inputNameError: true
            });
        } else {
            this.setState({
                inputNameError: false,
            });
        }
    }

    changeTeamName() {
        if (this.inputName && this.inputName.value.length > 0 && this.inputName.value != this.state.team.get('name')) {
            ManageActions.changeTeamName(this.state.team.toJS(), this.inputName.value);
            $(this.inputName).blur();
            this.setState({
                readyToSend: true
            });
            return true;
        } else if (this.inputName && this.inputName.value.length == 0){
            this.setState({
                inputNameError: true
            });
            return false;
        }
        return true;
    }

    applyChanges() {
        self = this;
        var teamNameOk = this.changeTeamName();
        if (this.inputNewUSer.value.length > 0) {
            if ( APP.checkEmail(this.inputNewUSer.value)) {
                this.addUser();
                setTimeout(function () {
                    self.applyChanges();
                });
                return false;
            } else {
                this.setState({
                    inputUserError: true
                });
                return true;
            }
        }
        if (this.state.usersToAdd.length > 0) {
            this.addUser();
        }
        if ( teamNameOk )  {
            APP.ModalWindow.onCloseModal();
        }
    }

    getUserList() {
        let self = this;

        return this.state.team.get('members').map(function(member, i) {
            let user = member.get('user');
            if (user.get('uid') == APP.USER.STORE.user.uid && self.state.showRemoveMessageUserID == user.get('uid')) {
                if (self.state.team.get('members').size > 1) {
                    return <div className="item"
                                key={'user' + user.get('uid')}>
                        <div className="right floated content top-5 bottom-5">
                            <div className="ui mini primary button" onClick={self.removeUser.bind(self, user)}><i className="icon-check icon"/>Confirm</div>
                            <div className="ui icon mini button red" onClick={self.undoRemoveAction.bind(self)}><i className="icon-cancel3 icon"/></div>
                        </div>
                        <div className="content pad-top-10 pad-bottom-8">
                            Are you sure you want to leave this team?
                        </div>
                    </div>
                } else {
                    return <div className="item"
                                key={'user' + user.get('uid')}>
                        <div className="right floated content top-20 bottom-5">
                            <div className="ui mini primary button" onClick={self.removeUser.bind(self, user)}><i className="icon-check icon"/>Confirm</div>
                            <div className="ui icon mini button red" onClick={self.undoRemoveAction.bind(self)}><i className="icon-cancel3 icon"/></div>
                        </div>
                        <div className="content pad-top-10 pad-bottom-8">
                            Abandoning this team, all projects within are unattainable, move them to your Personal Team if you do not want to lose them permanently
                        </div>
                    </div>;
                }
            }else if (self.state.showRemoveMessageUserID == user.get('uid')) {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="right floated content top-5 bottom-5">
                        <div className="ui mini primary button" onClick={self.removeUser.bind(self, user)}><i className="icon-check icon" /> Confirm</div>
                        <div className="mini ui icon button red" onClick={self.undoRemoveAction.bind(self)}><i className="icon-cancel3 icon" /></div>
                    </div>
                    <div className="content pad-top-10 pad-bottom-8">
                        Are you sure you want to remove this user?
                    </div>
                </div>
            } else {
                return <div className="item"
                            key={'user' + user.get('uid')}>
                    <div className="mini ui button right floated" onClick={self.showRemoveUser.bind(self, user.get('uid'))}>Remove</div>

                    { member.get('user_metadata') ?
                        (<img className="ui mini circular image"
                                                          src={member.get('user_metadata').get('gplus_picture') + "?sz=80"}/>)
                        :(
                            <div className="ui tiny image label">{APP.getUserShortName(user.toJS())}</div>
                        )}


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
        if (!this.state.team.get('pending_invitations') || !this.state.team.get('pending_invitations').size > 0) return;
        return this.state.team.get('pending_invitations').map(function(mail, i) {
            return <div className="item pending-invitation"
                         key={'user-invitation' + i}>
                        <div className="mini ui button right floated"
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
            }, 1000);
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
                nextState.readyToSend !== this.state.readyToSend ||
                nextState.usersToAdd !== this.state.usersToAdd
        )
    }

    render() {
        let usersError = (this.state.inputUserError) ? 'error' : '';
        let orgNameError = (this.state.inputNameError) ? 'error' : '';
        let userlist = this.getUserList();
        let pendingUsers = this.getPendingInvitations();
        let icon = (this.state.readyToSend && !this.state.inputNameError ) ?<i className="icon-checkmark green icon"/> : <i className="icon-pencil icon"/>;
        let applyButtonClass = (this.state.inputUserError || this.state.inputNameError) ?  'disabled' : '';
        let middleContainerStyle = (this.props.hideChangeName ) ? {paddingTop: "20px"} : {};
        return <div className="modify-team-modal">
                { !this.props.hideChangeName ?(
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
                            {this.state.inputNameError ? (
                                    <div className="validation-error"><span className="text" style={{color: 'red', fontSize: '14px'}}>Team name is required</span></div>
                                ): ''}
                        </div>
                    </div>
                </div>) : ('')}
            { (this.state.team.get('type') !== "personal" ) ? (
                    <div className="matecat-modal-middle" style={middleContainerStyle}>
                        <div className="ui grid left aligned">
                            <div className="sixteen wide column">
                                <h2>Add Members</h2>
                                <div className={"ui fluid icon input " + usersError }>
                                    <input type="text" placeholder="insert email and press enter"
                                           onKeyUp={this.handleKeyPressUserInput.bind(this)}
                                           ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                                </div>
                                {this.state.inputUserError ? (
                                        <div className="validation-error"><span className="text" style={{color: 'red', fontSize: '14px'}}>A valid email is required</span></div>
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
                                <button className={"create-team ui primary button open " + applyButtonClass}
                                onClick={this.applyChanges.bind(this)}>Confirm</button>
                            </div>
                        </div>
                    </div>

        </div>;
    }
}


export default ModifyTeam ;