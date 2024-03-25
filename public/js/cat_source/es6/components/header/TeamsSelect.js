import React from 'react'
import {isUndefined} from 'lodash'

import ManageConstants from '../../constants/ManageConstants'
import TeamsStore from '../../stores/TeamsStore'
import IconDown from '../icons/IconDown'
import IconSettings from '../icons/IconSettings'
import ManageActions from '../../actions/ManageActions'
import TeamsActions from '../../actions/TeamsActions'
import ModalsActions from '../../actions/ModalsActions'

class TeamsSelect extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      teams: [],
      selectedTeamId: null,
    }
    this.showPopup = true
  }

  componentDidMount() {
    TeamsStore.addListener(
      ManageConstants.OPEN_INFO_TEAMS_POPUP,
      this.initPopup,
    )
  }

  componentWillUnmount() {
    TeamsStore.removeListener(
      ManageConstants.OPEN_INFO_TEAMS_POPUP,
      this.initPopup,
    )
  }

  componentDidUpdate() {
    this.initDropdown()
  }

  initDropdown = () => {
    let self = this
    if (this.props.teams.size > 0 && !isUndefined(this.dropdownTeams)) {
      if (this.props.teams.size == 1) {
        this.dropdownTeams.classList.add('only-one-team')
      } else {
        this.dropdownTeams.classList.remove('only-one-team')
      }
      let dropdownTeams = $(this.dropdownTeams)
      if (this.props.selectedTeamId) {
        setTimeout(function () {
          dropdownTeams.dropdown('set selected', self.props.selectedTeamId)
        })
      } else {
        dropdownTeams.dropdown()
      }
    }
  }

  initPopup = () => {
    var self = this
    if (this.props.teams.size == 1 && this.props.showModals && this.showPopup) {
      let tooltipTex =
        "<h4 class='header'>Add your first team!</h4>" +
        "<div class='content'>" +
        '<p>Create a team and invite your colleagues to share and manage projects.</p>' +
        "<a class='close-popup-teams'>Got it!</a>" +
        '</div>'
      $(this.dropdownTeams)
        .popup({
          on: 'click',
          onHidden: self.removePopup,
          html: tooltipTex,
          closable: false,
          onCreate: self.onCreatePopup,
          className: {
            popup: 'ui popup cta-create-team',
          },
        })
        .popup('show')
      this.showPopup = false
    }
  }

  removePopup = () => {
    $(this.dropdownTeams).popup('destroy')
    ManageActions.setPopupTeamsCookie()
    return true
  }

  onCreatePopup = () => {
    var self = this
    $('.close-popup-teams').on('click', function () {
      $(self.dropdownTeams).popup('hide')
    })
  }

  changeTeamHandler = (event, team) => {
    if (team.get('id') !== this.props.selectedTeamId) {
      let selectedTeam = this.props.teams.find(function (org) {
        if (org.get('id') === team.get('id')) {
          return true
        }
      })
      if (this.props.isManage) {
        window.scrollTo(0, 0)
        ManageActions.changeTeam(selectedTeam.toJS())
      } else {
        TeamsActions.changeTeamFromUploadPage(selectedTeam.toJS())
      }
    }
  }

  openCreateTeams = () => {
    ModalsActions.openCreateTeamModal()
  }

  openModifyTeam = (event, team) => {
    event.stopPropagation()
    event.preventDefault()
    $(this.dropdownTeams).dropdown(
      'set selected',
      '' + this.props.selectedTeamId,
    )
    ManageActions.openModifyTeamModal(team.toJS())
  }

  getTeamsSelect = () => {
    const {openModifyTeam, changeTeamHandler, openCreateTeams} = this
    const {teams, changeTeam, showModals, selectedTeamId} = this.props

    let result = ''
    let dropdownIcon = <IconDown width={16} height={16} color={'#788190'} />
    let dontShowCursorClass = teams.size == 1 ? 'disable-dropdown-team' : ''
    let personalTeam = ''
    if (teams.size > 0 && changeTeam) {
      let items = teams.map((team) => {
        // item dom attributes
        const itemAttributes = {
          className: 'item',
          'data-value': team.get('id'),
          'data-text': team.get('name'),
          title: team.get('name'),
          onClick: (e) => changeTeamHandler(e, team),
          'data-testid': team.get('name'),
        }

        let iconModal = ''
        if (team.get('type') == 'personal') {
          personalTeam = (
            <div
              {...itemAttributes}
              key={'team' + team.get('name') + team.get('id')}
            >
              <div className={'item-info'}>
                <span className={'text'}>{team.get('name')}</span>
                <span className={'icon'}>{iconModal}</span>
              </div>
            </div>
          )
          return
        }
        if (showModals && team.get('type') !== 'personal') {
          iconModal = (
            <a
              className="team-filter button show right"
              onClick={(e) => openModifyTeam(e, team)}
              data-testid={`team-setting-icon-${team.get('name')}`}
            >
              {/*<i className="icon-settings icon"/>*/}
              <IconSettings width={17} height={17} color={'#0099CC'} />
            </a>
          )
        }
        return (
          <div
            {...itemAttributes}
            key={'team' + team.get('name') + team.get('id')}
          >
            <div className={'item-info'}>
              <span className={'text'}>{team.get('name')}</span>
              <span className={'icon'}>{iconModal}</span>
            </div>
          </div>
        )
      })
      let addTeam = ''
      if (showModals) {
        dontShowCursorClass = ''
        addTeam = (
          <div className="header" onClick={openCreateTeams}>
            Create New Team
            <a className="team-filter button show">
              <span className={'icon'}>
                <i className="icon-plus3 icon" />
              </span>
            </a>
          </div>
        )
      }
      result = (
        <div
          className={
            'ui top right pointing dropdown select-org ' + dontShowCursorClass
          }
          ref={(dropdownTeams) => (this.dropdownTeams = dropdownTeams)}
        >
          <input type="hidden" name="team" className="team-dd" />

          <span className="text">Choose Team</span>
          <div className="icon">{dropdownIcon}</div>
          <div className="menu">
            {addTeam}
            {showModals ? <div className="divider"></div> : ''}
            <div className="scrolling menu">
              {personalTeam}
              {items}
            </div>
          </div>
        </div>
      )
    } else if (teams.size > 0 && selectedTeamId) {
      let team = teams.find(function (team) {
        return team.get('id') === selectedTeamId
      })
      return <div className="organization-name">{team.get('name')}</div>
    }
    return result
  }

  render() {
    const {loggedUser} = this.props
    let teamsSelect = loggedUser ? this.getTeamsSelect() : ''
    return <div data-testid="team-select">{teamsSelect}</div>
  }
}

TeamsSelect.defaultProps = {
  isManage: true,
  showModals: true,
  loggedUser: true,
  showTeams: true,
  changeTeam: true,
}

export default TeamsSelect
