import IconDown from "../../icons/IconDown";
import {filterProjects} from "../../../actions/ManageActions";

let FilterProjectsStatus = require("./FilterProjectsStatus").default;
let SearchInput = require("./SearchInput").default;

class FilterProjects extends React.Component {
    constructor(props) {
        super(props);
        this.ALL_MEMBERS = "-1";
        this.NOT_ASSIGNED = "0";

        this.teamChanged = false;
        this.dropDownUsersInitialized = false;
        this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
    }

    state = {
        currentStatus: 'active'
    };

    componentDidUpdate() {
        const {changeUser, selectedUser, dropdownUsers} = this;
        let {dropDownUsersInitialized, teamChanged} = this;
        const {selectedTeam,} = this.props;
        if (selectedTeam) {
            if (teamChanged) {
                if (!dropDownUsersInitialized && selectedTeam.get('members').size > 1) {
                    if (selectedUser === ManageConstants.ALL_MEMBERS_FILTER) {
                        $(dropdownUsers).dropdown('set selected', "-1");
                    } else {
                        $(dropdownUsers).dropdown('set selected', selectedUser);
                    }
                    $(
                        dropdownUsers).dropdown({
                        fullTextSearch: 'exact',
                        onChange: (value, text, $selectedItem) => {
                            changeUser(value);
                        }
                    });
                    dropDownUsersInitialized = true;
                }
                teamChanged = false;
            }
        }
    }

    componentWillReceiveProps(nextProps) {
        if ((_.isUndefined(nextProps.selectedTeam))) return;
        if ((_.isUndefined(this.props.selectedTeam)) ||
            nextProps.selectedTeam.get('id') !== this.props.selectedTeam.get('id') ||
            nextProps.selectedTeam.get('members') !== this.props.selectedTeam.get('members')) {
            this.teamChanged = true;
            this.dropDownUsersInitialized = false;
            if (!_.isUndefined(this.props.selectedTeam) && nextProps.selectedTeam.get('id') !== this.props.selectedTeam.get('id')) {
                this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
            }
            if (nextProps.selectedTeam && nextProps.selectedTeam.get('type') !== 'personal' && nextProps.selectedTeam.get('members').size == 1) {
                this.dropDownUsersInitialized = true;
            }
        }
    }

    changeUser = (value) => {
        const {ALL_MEMBERS, NOT_ASSIGNED} = this;
        const {selectedTeam} = this.props;
        let self = this;
        let selectedUser;

        if (value === ALL_MEMBERS) {
            selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
        } else if (value === NOT_ASSIGNED && value !== this.selectedUser) {
            selectedUser = ManageConstants.NOT_ASSIGNED_FILTER;
        } else {
            selectedUser = selectedTeam.get('members').find(function (member) {
                if (parseInt(member.get('user').get("uid")) === parseInt(value)) {
                    return true;
                }
            });
        }

        if (selectedUser !== this.selectedUser) {
            this.selectedUser = selectedUser;
            filterProjects(self.selectedUser, self.currentText, self.state.currentStatus);
        }
    };

    onChangeSearchInput(value) {
        let {currentText, selectedUser} = this;
        const {currentStatus} = this.state;
        currentText = value;

        filterProjects(selectedUser, currentText, currentStatus);
    }

    filterByStatus(status) {
        const {currentStatus} = this.state;
        const {currentText, selectedUser} = this.state;

        this.setState({currentStatus: status});
        filterProjects(selectedUser, currentText, currentStatus);
    }

    getUserFilter = () => {
        let result = '';
        let members = null;
        let item = null;
        const {} = this.props;

        if (this.props.selectedTeam && this.props.selectedTeam.get('type') === "general" &&
            this.props.selectedTeam.get('members') && this.props.selectedTeam.get('members').size > 1) {

            let members = this.props.selectedTeam.get('members').map((member, i) => {
                let classDisable = (member.get('projects') === 0) ? 'disabled' : '';
                let userIcon = <a className="ui circular label">{APP.getUserShortName(member.get('user').toJS())}</a>;
                if (member.get('user_metadata')) {
                    userIcon = <img className="ui avatar image ui-user-dropdown-image" alt=""
                                    src={member.get('user_metadata').get('gplus_picture') + "?sz=80"}/>;
                }
                return <div className={"item " + classDisable} data-value={member.get('user').get('uid')}
                            key={'user' + member.get('user').get('uid')}>
                    {userIcon}
                    <div className="user-projects">
                        <div
                            className="user-name-filter">{member.get('user').get('first_name') + ' ' + member.get('user').get('last_name')}</div>
                        <div className="box-number-project">{member.get('projects')}</div>
                    </div>
                </div>;

            });

            item = <div>
                <div className="item" data-value="0"
                     key={'user' + 0}>
                    <a className="ui all label">NA</a>
                    <div className="user-projects">
                        <div className="user-name-filter">Not assigned</div>
                        <div className="box-number-project"/>
                    </div>
                </div>
                <div className="item" data-value="-1"
                     key={'user' + -1}>
                    <a className="ui all label">ALL</a>
                    <div className="user-projects">
                        <div className="user-name-filter">All Members</div>
                        <div className="box-number-project"/>
                    </div>
                </div>
            </div>;

            members = members.unshift(item);

            result = <div className="ui top left pointing dropdown users-filter" title="Filter project by members"
                          ref={(dropdown) => this.dropdownUsers = dropdown}>
                <div className="text">
                    <div className="ui all label">ALL</div>
                    All Members
                </div>
                <div className="icon"><IconDown width={16} height={16} color={'#788190'}/></div>
                <div className="menu">
                    {members}
                </div>
            </div>;
        }

        return result;
    };

    getCurrentStatusLabel = () => {
        const {currentStatus} = this.state;
        switch (currentStatus) {
            case'active':
                return <div className="active">Active:</div>;
            case 'archived':
                return <div className="archived">Archived:</div>;
            case 'cancelled':
                return <div className="cancelled">Cancelled:</div>;
        }
    }

    shouldComponentUpdate(nextProps, nextState) {
        return _.isUndefined(this.props.selectedTeam) || (!_.isUndefined(nextProps.selectedTeam) && !nextProps.selectedTeam.equals(this.props.selectedTeam))
    }

    render() {
        const {getUserFilter,onChangeSearchInput,filterByStatus} = this;

        return (
            <section className="row sub-head">
                <div className="ui grid">

                    <div className="ten wide column">
                        <div className="ui right labeled fluid input search-state-filters">
                            <SearchInput onChange={onChangeSearchInput}/>
                            <FilterProjectsStatus filterFunction={filterByStatus}/>
                        </div>
                    </div>

                    <div className="six wide column pad-right-0">
                        {getUserFilter}
                    </div>

                </div>
            </section>
        );
    }
}

export default FilterProjects;
