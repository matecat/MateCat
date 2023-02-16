import TeamConstants from '../../constants/TeamConstants'
import TeamsStore from '../../stores/TeamsStore'
import ManageActions from '../../actions/ManageActions'
import React from 'react'
import CommonUtils from '../../utils/commonUtils'
import IconSearch from '../icons/IconSearch'
import IconClose from '../icons/IconClose'
import TEXT_UTILS from '../../utils/textUtils'
class ModifyTeam extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      team: this.props.team,
      inputUserError: false,
      inputNameError: false,
      showRemoveMessageUserID: null,
      readyToSend: false,
      resendInviteArray: [],
      searchMember: '',
    }
    this.updateTeam = this.updateTeam.bind(this)
    this.onLabelCreate = this.onLabelCreate.bind(this)
  }

  onLabelCreate(value, text) {
    var self = this

    // if ( APP.checkEmail(text) && event.key === 'Enter') {
    if (CommonUtils.checkEmail(text)) {
      $(this.inputNewUSer).dropdown('set selected', value)
      this.setState({
        inputUserError: false,
      })
      this.addUsers()
      return true
    } else if (text.indexOf(',') > -1) {
      let members = text.split(',')
      members.forEach(function (item) {
        self.createLabel(item)
      })
      return false
    } else {
      this.createLabel(text)
      return false
    }
    // else {
    //     this.setState({
    //         inputUserError: true
    //     });
    //     $(this.inputNewUSer).dropdown('set text', text);
    //     return false;
    // }
  }

  createLabel(text) {
    var self = this
    if (CommonUtils.checkEmail(text)) {
      $(this.inputNewUSer).find('input.search').val('')
      $(this.inputNewUSer).dropdown('set selected', text)
      this.setState({
        inputUserError: false,
      })
      return true
    } else if (text.indexOf(',') > -1) {
      let members = text.split(',')
      members.forEach(function (item) {
        self.createLabel(item)
      })
      return false
    } else {
      this.setState({
        inputUserError: true,
      })
      $(this.inputNewUSer).dropdown('set text', text)
      return true
    }
  }

  updateTeam(team) {
    if (this.state.team.get('id') == team.get('id')) {
      this.setState({
        team: team,
      })
    }
  }

  showRemoveUser(userId) {
    this.setState({
      showRemoveMessageUserID: userId,
    })
  }

  removeUser(user) {
    ManageActions.removeUserFromTeam(this.state.team, user)
    if (user.get('uid') === APP.USER.STORE.user.uid) {
      ModalsActions.onCloseModal()
    }
    this.setState({
      showRemoveMessageUserID: null,
    })
  }

  undoRemoveAction() {
    this.setState({
      showRemoveMessageUserID: null,
    })
  }

  resendInvite(mail) {
    ManageActions.addUserToTeam(this.state.team, mail)
    var resendInviteArray = this.state.resendInviteArray
    resendInviteArray.push(mail)
    this.setState({
      resendInviteArray: resendInviteArray,
    })
  }

  handleKeyPressUserInput(e) {
    let mail = $(this.inputNewUSer).find('input.search').val()
    if (e.key == 'Enter') {
      if (mail == '') {
        this.addUsers()
      }
      return
    }
    if (e.key === ' ') {
      e.stopPropagation()
      e.preventDefault()
      this.createLabel(mail)
    } else {
      this.setState({
        inputUserError: false,
      })
    }
    return false
  }

  addUsers() {
    var members =
      $(this.inputNewUSer).dropdown('get value').length > 0
        ? $(this.inputNewUSer).dropdown('get value').split(',')
        : []
    if (members.length > 0) {
      ManageActions.addUserToTeam(this.state.team, members)
      $(this.inputNewUSer).dropdown('restore defaults')
    }
  }

  addUser() {
    if (CommonUtils.checkEmail(this.inputNewUSer.value)) {
      ManageActions.addUserToTeam(this.state.team, this.inputNewUSer.value)
      var resendInviteArray = this.state.resendInviteArray
      resendInviteArray.push(this.inputNewUSer.value)
      this.inputNewUSer.value = ''
      this.setState({
        resendInviteArray: resendInviteArray,
      })
      return true
    } else {
      this.setState({
        inputUserError: true,
      })
      return false
    }
  }

  onKeyPressEvent(e) {
    if (e.key === 'Enter') {
      this.changeTeamName()
      return false
    } else if (this.inputName.value.length == 0) {
      this.setState({
        inputNameError: true,
      })
    } else {
      this.setState({
        inputNameError: false,
      })
    }
  }

  changeTeamName() {
    if (
      this.inputName &&
      this.inputName.value.length > 0 &&
      this.inputName.value != this.state.team.get('name')
    ) {
      ManageActions.changeTeamName(this.state.team.toJS(), this.inputName.value)
      $(this.inputName).blur()
      this.setState({
        readyToSend: true,
      })
      return true
    } else if (this.inputName && this.inputName.value.length == 0) {
      this.setState({
        inputNameError: true,
      })
      return false
    }
    return true
  }

  applyChanges() {
    var teamNameOk = this.changeTeamName()
    if ($(this.inputNewUSer).dropdown('get value').length > 0) {
      this.addUsers()
    }
    if (teamNameOk) {
      ModalsActions.onCloseModal()
    }
  }

  getUserList() {
    let self = this

    const teamMembers = this.state.team.get('members')
    const filteredMembers = teamMembers.filter((member) => {
      const {searchMember} = this.state
      const user = member.get('user')
      const fullName = `${user.get('first_name')} ${user.get('last_name')}`
      const regex = new RegExp(TEXT_UTILS.escapeRegExp(searchMember), 'i')
      if (!searchMember) return true
      else return regex.test(fullName) || regex.test(user.get('email'))
    })

    if (!filteredMembers.size)
      return <span className="no-result">No results!</span>

    return filteredMembers.map(function (member, i) {
      let user = member.get('user')
      if (
        user.get('uid') == APP.USER.STORE.user.uid &&
        self.state.showRemoveMessageUserID == user.get('uid')
      ) {
        if (self.state.team.get('members').size > 1) {
          return (
            <div className="item" key={'user' + user.get('uid')}>
              <div className="right floated content top-5 bottom-5">
                <div
                  className="ui mini primary button"
                  onClick={self.removeUser.bind(self, user)}
                >
                  <i className="icon-check icon" />
                  Confirm
                </div>
                <div
                  className="ui icon mini button red"
                  onClick={self.undoRemoveAction.bind(self)}
                >
                  <i className="icon-cancel3 icon" />
                </div>
              </div>
              <div className="content pad-top-10 pad-bottom-8">
                Are you sure you want to leave this team?
              </div>
            </div>
          )
        } else {
          return (
            <div className="item" key={'user' + user.get('uid')}>
              <div className="right floated content top-20 bottom-5">
                <div
                  className="ui mini primary button"
                  onClick={self.removeUser.bind(self, user)}
                >
                  <i className="icon-check icon" />
                  Confirm
                </div>
                <div
                  className="ui icon mini button red"
                  onClick={self.undoRemoveAction.bind(self)}
                >
                  <i className="icon-cancel3 icon" />
                </div>
              </div>
              <div className="content pad-top-10 pad-bottom-8">
                By removing the last member the team will be deleted. All
                projects will be moved to your Personal area.
              </div>
            </div>
          )
        }
      } else if (self.state.showRemoveMessageUserID == user.get('uid')) {
        return (
          <div className="item" key={'user' + user.get('uid')}>
            <div className="right floated content top-5 bottom-5">
              <div
                className="ui mini primary button"
                onClick={self.removeUser.bind(self, user)}
              >
                <i className="icon-check icon" /> Confirm
              </div>
              <div
                className="mini ui icon button red"
                onClick={self.undoRemoveAction.bind(self)}
              >
                <i className="icon-cancel3 icon" />
              </div>
            </div>
            <div className="content pad-top-10 pad-bottom-8">
              Are you sure you want to remove this user?
            </div>
          </div>
        )
      } else {
        return (
          <div className="item" key={'user' + user.get('uid')}>
            <div
              className="mini ui button right floated"
              onClick={self.showRemoveUser.bind(self, user.get('uid'))}
            >
              Remove
            </div>

            {member.get('user_metadata') ? (
              <img
                className="ui mini circular image"
                src={
                  member.get('user_metadata').get('gplus_picture') + '?sz=80'
                }
              />
            ) : (
              <div className="ui tiny image label">
                {CommonUtils.getUserShortName(user.toJS())}
              </div>
            )}

            <div className="middle aligned content">
              <div className="content user">
                {' ' + user.get('first_name') + ' ' + user.get('last_name')}
              </div>
              <div className="content email-user-invited">
                {user.get('email')}
              </div>
            </div>
          </div>
        )
      }
    })
  }

  getPendingInvitations() {
    let self = this
    if (
      !this.state.team.get('pending_invitations') ||
      !this.state.team.get('pending_invitations').size > 0
    )
      return
    return this.state.team.get('pending_invitations').map(function (mail, i) {
      var inviteResended = self.state.resendInviteArray.indexOf(mail) > -1
      return (
        <div className="item pending-invitation" key={'user-invitation' + i}>
          <div className="ui tiny image label">
            {mail.substring(0, 1).toUpperCase()}
          </div>
          <span className="email content user">{mail}</span>
          <div>
            {inviteResended ? (
              <span className="content pending-msg">Invite sent</span>
            ) : (
              <>
                <span className="content pending-msg">Pending user</span>
                <div
                  className="mini ui button right floated"
                  onClick={self.resendInvite.bind(self, mail)}
                >
                  Resend Invite
                </div>
              </>
            )}
          </div>
        </div>
      )
    })
  }

  componentDidUpdate() {
    var self = this
    clearTimeout(this.inputTimeout)
    if (this.state.readyToSend) {
      this.inputTimeout = setTimeout(function () {
        self.setState({
          readyToSend: false,
        })
      }, 1000)
    }
  }

  componentDidMount() {
    $(this.inputNewUSer).dropdown({
      allowAdditions: true,
      action: this.onLabelCreate,
    })
    TeamsStore.addListener(TeamConstants.UPDATE_TEAM, this.updateTeam)
  }

  componentWillUnmount() {
    TeamsStore.removeListener(TeamConstants.UPDATE_TEAM, this.updateTeam)
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      nextState.team !== this.state.team ||
      nextState.inputUserError !== this.state.inputUserError ||
      nextState.inputNameError !== this.state.inputNameError ||
      nextState.showRemoveMessageUserID !==
        this.state.showRemoveMessageUserID ||
      nextState.readyToSend !== this.state.readyToSend ||
      nextState.searchMember !== this.state.searchMember
    )
  }

  onChangeSearchMember(event) {
    this.setState({searchMember: event.target.value})
  }

  render() {
    let usersError = this.state.inputUserError ? 'error' : ''
    let orgNameError = this.state.inputNameError ? 'error' : ''
    let userlist = this.getUserList()
    let pendingUsers = this.getPendingInvitations()
    let icon =
      this.state.readyToSend && !this.state.inputNameError ? (
        <i className="icon-checkmark green icon" />
      ) : (
        <i className="icon-pencil icon" />
      )
    let applyButtonClass =
      this.state.inputUserError || this.state.inputNameError ? 'disabled' : ''
    let middleContainerStyle = this.props.hideChangeName
      ? {paddingTop: '20px'}
      : {}
    return (
      <div className="modify-team-modal" data-testid="modify-team-modal">
        {!this.props.hideChangeName ? (
          <div className="matecat-modal-top">
            <div className="ui one column grid left aligned">
              <div className="column">
                <h2>Change Team Name</h2>
                <div className={'ui fluid icon input ' + orgNameError}>
                  <input
                    type="text"
                    defaultValue={this.state.team.get('name')}
                    onKeyUp={this.onKeyPressEvent.bind(this)}
                    ref={(inputName) => (this.inputName = inputName)}
                  />
                  {icon}
                </div>
                {this.state.inputNameError ? (
                  <div className="validation-error">
                    <span
                      className="text"
                      style={{color: 'red', fontSize: '14px'}}
                    >
                      Team name is required
                    </span>
                  </div>
                ) : (
                  ''
                )}
              </div>
            </div>
          </div>
        ) : (
          ''
        )}
        {this.state.team.get('type') !== 'personal' ? (
          <div className="matecat-modal-middle" style={middleContainerStyle}>
            <div className="ui grid left aligned">
              <div className="sixteen wide column">
                <h2>Manage Members</h2>
                {/* <div className={"ui fluid icon input " + usersError }>
                                    <input type="text" placeholder="insert email and press enter"
                                           onKeyUp={this.handleKeyPressUserInput.bind(this)}
                                           ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                                </div>
                                {this.state.inputUserError ? (
                                        <div className="validation-error"><span className="text" style={{color: 'red', fontSize: '14px'}}>A valid email is required</span></div>
                                    ): ''}*/}
                <div
                  className={
                    'ui multiple search selection dropdown ' + usersError
                  }
                  onKeyUp={this.handleKeyPressUserInput.bind(this)}
                  ref={(inputNewUSer) => (this.inputNewUSer = inputNewUSer)}
                >
                  <input name="tags" type="hidden" />
                  <div className="default text">
                    Add new people (separate email addresses with a comma)
                  </div>
                </div>
                {this.state.inputUserError ? (
                  <div className="validation-error">
                    <span
                      className="text"
                      style={{color: 'red', fontSize: '14px'}}
                    >
                      A valid email is required
                    </span>
                  </div>
                ) : (
                  ''
                )}
              </div>

              <div className="sixteen wide column">
                <div className="ui members-list team">
                  <div className="search-member-container">
                    <IconSearch />
                    <input
                      name="search_member"
                      placeholder="Search Member"
                      value={this.state.searchMember}
                      onChange={this.onChangeSearchMember.bind(this)}
                    />
                    <div
                      className={`reset_button ${
                        this.state.searchMember
                          ? 'reset_button--visible'
                          : 'reset_button--hidden'
                      }`}
                      onClick={() => this.setState({searchMember: ''})}
                    >
                      <IconClose />
                    </div>
                  </div>
                  <div className="ui divided list">
                    {pendingUsers}
                    {userlist}
                  </div>
                </div>
              </div>
            </div>
          </div>
        ) : (
          ''
        )}
        <div className="matecat-modal-bottom">
          <div className="ui one column grid right aligned">
            <div className="column">
              <button
                className={
                  'create-team ui primary button open ' + applyButtonClass
                }
                onClick={this.applyChanges.bind(this)}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default ModifyTeam
