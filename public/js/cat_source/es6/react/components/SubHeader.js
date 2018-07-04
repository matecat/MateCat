let FilterProjects = require("./FilterProjects").default;
let SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
        this.ALL_MEMBERS = "-1";
        this.NOT_ASSIGNED = "0";

        this.teamChanged = false;
        this.dropDownUsersInitialized = false;
        this.state= {
            currentStatus : 'active'
        };
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
    }

    componentDidUpdate() {
        let self = this;
        if (this.props.selectedTeam) {
            if (this.teamChanged) {
                if (!this.dropDownUsersInitialized && this.props.selectedTeam.get('members').size > 1) {
                    if (this.selectedUser === ManageConstants.ALL_MEMBERS_FILTER) {
                        $(this.dropdownUsers).dropdown('set selected', "-1");
                    } else {
                        $(this.dropdownUsers).dropdown('set selected', this.selectedUser);
                    }
                    $(this.dropdownUsers).dropdown({
                        fullTextSearch: 'exact',
                        onChange: function(value, text, $selectedItem) {
                            self.changeUser(value);
                        }
                    });
                    this.dropDownUsersInitialized = true;
                }
                this.teamChanged = false;
            }
        }
    }

    componentWillReceiveProps(nextProps) {
        if ( (_.isUndefined(nextProps.selectedTeam) )) return;
        if ( (_.isUndefined(this.props.selectedTeam)) ||
            nextProps.selectedTeam.get('id') !== this.props.selectedTeam.get('id') ||
            nextProps.selectedTeam.get('members') !== this.props.selectedTeam.get('members') ) {
            this.teamChanged = true;
            this.dropDownUsersInitialized = false;
            if ( !_.isUndefined(this.props.selectedTeam) && nextProps.selectedTeam.get('id') !== this.props.selectedTeam.get('id')) {
                this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
            }
            if ( nextProps.selectedTeam && nextProps.selectedTeam.get('type') !== 'personal' && nextProps.selectedTeam.get('members').size == 1) {
                this.dropDownUsersInitialized = true;
            }
        }
    }

    changeUser(value) {

        let self = this;
        let selectedUser;
        if (value === this.ALL_MEMBERS) {
            selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
        } else if ( value === this.NOT_ASSIGNED && value !== this.selectedUser) {
            selectedUser = ManageConstants.NOT_ASSIGNED_FILTER;
        } else {
            selectedUser = this.props.selectedTeam.get('members').find(function (member) {
                if (parseInt(member.get('user').get("uid")) === parseInt(value)) {
                    return true;
                }
            });
        }
        if (selectedUser !== this.selectedUser) {
            this.selectedUser = selectedUser;
            ManageActions.filterProjects(self.selectedUser, self.currentText, self.state.currentStatus);
        }
    }

    onChangeSearchInput(value) {
        this.currentText = value;
        let self = this;
        ManageActions.filterProjects(self.selectedUser, self.currentText, self.state.currentStatus);
    }

    filterByStatus(status) {
        this.setState({currentStatus: status});
        ManageActions.filterProjects(this.selectedUser, this.currentText, this.state.currentStatus);
    }

    getUserFilter() {
        let result = '';
        if (this.props.selectedTeam && this.props.selectedTeam.get('type') === "general" &&
            this.props.selectedTeam.get('members') && this.props.selectedTeam.get('members').size > 1) {

            let members = this.props.selectedTeam.get('members').map(function(member, i) {
                let classDisable = (member.get('projects') === 0) ? 'disabled' : '';
                let userIcon = <a className="ui circular label">{APP.getUserShortName(member.get('user').toJS())}</a>;
                if ( member.get('user_metadata') ) {
                    userIcon = <img className="ui avatar image ui-user-dropdown-image"
                                     src={member.get('user_metadata').get('gplus_picture') + "?sz=80"}/>;
                }
                return <div className={"item " + classDisable} data-value={member.get('user').get('uid')}
                     key={'user' + member.get('user').get('uid')}>
                    {userIcon}
                    <span className="user-name-filter">{member.get('user').get('first_name') + ' ' + member.get('user').get('last_name')}</span>
                    <div className="box-number-project">{member.get('projects')}</div>
                </div>;

            });

            let item = <div className="item" data-value="-1"
                            key={'user' + -1}>
                            <a className="ui all label">All</a>
                            All Members
                        </div>;
            members = members.unshift(item);
            item = <div className="item" data-value="0"
                            key={'user' + 0}>
                        <a className="ui all label">NA</a>
                        Not assigned
                    </div>;
            members = members.unshift(item);


            result = <div className="users-filter" title="Filter project by members">

                        <div className="assigned-list">
                            <p>Projects of: </p>
                        </div>

                        <div className="list-team">
                            <div className="ui dropdown top right pointing users-projects"
                                 ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                                <div className="text">
                                    <div className="ui all label">ALL</div>
                                  All Members
                                </div>
                                <i className="dropdown icon"/>
                                <div className="menu">
                                    <div className="ui icon search input">
                                        <i className="icon-search icon"/>
                                        <input type="text" name="UserName" placeholder="Search by name." />
                                    </div>
                                    <div className="scrolling menu">
                                    {members}
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>;
        }
        return result;
    }

    getCurrentStatusLabel() {
        switch (this.state.currentStatus) {
            case 'active':
                return <div className="active">Active:</div>;
                break;
            case 'archived':
                return <div className="archived">Archived:</div>;
                break;
            case 'cancelled':
                return <div className="cancelled">Cancelled:</div>;
                break;
        }
    }

    shouldComponentUpdate(nextProps, nextState) {
        return _.isUndefined(this.props.selectedTeam) || (!_.isUndefined(nextProps.selectedTeam) && !nextProps.selectedTeam.equals(this.props.selectedTeam) )
    }

    render () {
        let membersFilter = this.getUserFilter();
        let currentStatusLabel = this.getCurrentStatusLabel();
        return (
            <section className="row sub-head">
                <div className="ui grid">

                    <div className="eight wide column">
                        <div className="ui right labeled fluid input search-state-filters">
                            <SearchInput
                                onChange={this.onChangeSearchInput.bind(this)}/>
                            <FilterProjects
                                filterFunction={this.filterByStatus.bind(this)}/>
                        </div>
                    </div>

                    {/*<div className="cta-create-team" onClick={this.openCreateTeams.bind(this)}>
                        <a class="cta-create-team-text">Create New Team <i className="icon-settings icon"></i></a>
                    </div>*/}

                    <div className="eight wide column pad-right-0">
                        {membersFilter}
                    </div>

                </div>
            </section>
        );
    }
}

export default SubHeader ;