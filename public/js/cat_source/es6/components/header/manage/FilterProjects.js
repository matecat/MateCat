import React from 'react'
import {isUndefined} from 'lodash'
import FilterProjectsStatus from './FilterProjectsStatus'
import SearchInput from './SearchInput'
import ManageActions from '../../../actions/ManageActions'
import ManageConstants from '../../../constants/ManageConstants'
import MembersFilter from './MembersFilter'

class FilterProjects extends React.Component {
  constructor(props) {
    super(props)

    this.state = {
      currentStatus: 'active',
      currentUser: ManageConstants.ALL_MEMBERS_FILTER,
    }
  }

  setCurrentUser = (value) => {
    this.setState({currentUser: value})

    ManageActions.filterProjects(
      typeof value === 'object' ? value.user.uid : value,
      this.currentText,
      this.state.currentStatus,
    )
  }

  onChangeSearchInput(value) {
    this.currentText = value
    let self = this
    ManageActions.filterProjects(
      self.selectedUser,
      self.currentText,
      self.state.currentStatus,
    )
  }

  filterByStatus(status) {
    this.setState({currentStatus: status})
    ManageActions.filterProjects(this.selectedUser, this.currentText, status)
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      isUndefined(this.props.selectedTeam) ||
      (!isUndefined(nextProps.selectedTeam) &&
        !nextProps.selectedTeam.equals(this.props.selectedTeam)) ||
      nextState.currentUser !== this.state.currentUser
    )
  }

  render() {
    const canRenderMemebersFilter =
      this.props.selectedTeam &&
      this.props.selectedTeam.get('type') === 'general' &&
      this.props.selectedTeam.get('members') &&
      this.props.selectedTeam.get('members').size > 1

    return (
      <section className="row sub-head">
        <div className="ui grid">
          <div className="twelve wide column">
            <div className="ui right labeled fluid input search-state-filters">
              <SearchInput onChange={this.onChangeSearchInput.bind(this)} />
              <FilterProjectsStatus
                filterFunction={this.filterByStatus.bind(this)}
              />
            </div>
          </div>
          <div className="four wide column pad-right-0">
            {canRenderMemebersFilter && (
              <MembersFilter
                selectedTeam={this.props.selectedTeam}
                currentUser={this.state.currentUser}
                setCurrentUser={this.setCurrentUser}
              />
            )}
          </div>
        </div>
      </section>
    )
  }
}

export default FilterProjects
