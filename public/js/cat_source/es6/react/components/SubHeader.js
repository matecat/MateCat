let FilterProjects = require("./FilterProjects").default;
let SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
        this.ALL_MEMBERS = "-1";
        this.NOT_ASSIGNED = "0";
        this.ALL_WORKSPACES = "-1";
        this.NO_WORKSPACES = "0";

        this.organizazionChanged = false;
    }

    componentDidUpdate() {
        let self = this;
        if (this.props.selectedOrganization) {
            if (this.organizazionChanged) {
                $(this.dropdownWorkspaces).dropdown('set selected', '-1');
                $(this.dropdownUsers).dropdown('set selected', '-1');
                this.organizazionChanged = false;
                $(this.dropdownUsers).dropdown({
                    fullTextSearch: 'exact',
                    onChange: function(value, text, $selectedItem) {
                        self.changeUser(value);
                    }
                });
                $(this.dropdownWorkspaces).dropdown({
                    onChange: function(value, text, $selectedItem) {
                        self.changeWorkspace(value);
                    }
                });
            }
        }
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.selectedOrganization !== this.props.selectedOrganization) {
            this.organizazionChanged = true;
        }
    }

    changeUser(value) {
        if ( this.organizazionChanged ) {
            return;
        }
        let self = this;
        if (value === this.ALL_MEMBERS) {
            this.selectedUser = ManageConstants.ALL_MEMBERS_FILTER;
        } else if ( value === this.NOT_ASSIGNED ) {
            this.selectedUser = ManageConstants.NOT_ASSIGNED_FILTER;
        } else {
            this.selectedUser = this.props.selectedOrganization.get('members').find(function (member) {
                if (member.get('user').get("uid") === parseInt(value)) {
                    return true;
                }
            });
        }
        ManageActions.filterProjects(self.selectedUser, self.selectedWorkspace, self.currentText, self.currentStatus);
    }

    changeWorkspace(value) {
        if ( this.organizazionChanged ) {
            return;
        }
        let self = this;
        if (value === this.ALL_WORKSPACES) {
            this.selectedWorkSpace = ManageConstants.ALL_WORKSPACES_FILTER;
        } else if ( value === this.NO_WORKSPACES ) {
            this.selectedWorkSpace = ManageConstants.NO_WORKSPACE_FILTER;
        } else {
            this.selectedWorkSpace = this.props.selectedOrganization.get('workspaces').find(function (workspace) {
                if (workspace.get("id") === parseInt(value)) {
                    return true;
                }
            });
        }
        setTimeout(function () {
            ManageActions.filterProjects(self.selectedUser, self.selectedWorkSpace, self.currentText, self.currentStatus);
        });
    }

    openCreateWorkspace() {
        ManageActions.openCreateWorkspaceModal(this.props.selectedOrganization);
    }


    onChangeSearchInput(value) {
        this.currentText = value;
        let self = this;
        ManageActions.filterProjects(self.selectedUser, self.selectedWorkSpace, self.currentText, self.currentStatus);
    }

    filterByStatus(status) {
        this.currentStatus = status;
        ManageActions.filterProjects(this.selectedUser, this.selectedWorkSpace, this.currentText, this.currentStatus);
    }

    getUserFilter() {
        let result = '';
        if (this.props.selectedOrganization && this.props.selectedOrganization.get('type') === "general" && this.props.selectedOrganization.get('members')) {

            let members = this.props.selectedOrganization.get('members').map((member, i) => (
                <div className="item" data-value={member.get('user').get('uid')}
                     key={'user' + member.get('user').get('uid')}>
                    <a className="ui circular label">{APP.getUserShortName(member.get('user').toJS())}</a>
                    {(member.get('user').get('uid') === APP.USER.STORE.user.uid)? 'My Projects' : member.get('user').get('first_name') + ' ' + member.get('user').get('last_name')}
                </div>

            ));

            let item = <div className="item" data-value="-1"
                            key={'user' + -1}>
                            <a className="ui circular label">ALL</a>
                            All Members
                        </div>;
            members = members.unshift(item);


            result = <div className="users-filter">

                        <div className="assigned-list">
                            <p>Projects of: </p>
                        </div>

                        <div className="list-organization">
                            <div className="ui dropdown top right pointing users-projects shadow-1"
                                 ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                                <span className="text">
                                    <div className="ui circlar label">ALL</div>
                                  All Members
                                </span>
                                <i className="dropdown icon"/>
                                <div className="menu">
                                    <div className="ui icon search input">
                                        <i className="icon-search icon"/>
                                        <input type="text" name="UserName" placeholder="Name or email." />
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
    getWorkspacesSelect() {
        let result = '';
        let items;
        if (this.props.selectedOrganization && this.props.selectedOrganization.get("workspaces")) {
            items = this.props.selectedOrganization.get("workspaces").map((workspace, i) => (
                <div className="item" data-value={workspace.get('id')}
                     data-text={workspace.get('name')}
                     key={'organization' + workspace.get('name') + workspace.get('id')}>
                    {workspace.get('name')}
                </div>
            ));
        }
        result = <div className="ui dropdown selection workspace-dropdown"
                      ref={(dropdownWorkspaces) => this.dropdownWorkspaces = dropdownWorkspaces}>
            <input type="hidden" name="gender" />
            <i className="dropdown icon"/>
            <div className="default text">Choose Workspace</div>
            <div className="menu">
                <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateWorkspace.bind(this)}>New Workspace
                    <a className="organization-filter button show">
                        <i className="icon-plus3 icon"/>
                    </a>
                </div>
                <div className="divider"></div>
                {/*<div className="header">
                 <div className="ui form">
                 <div className="field">
                 <input type="text" name="Project Name" placeholder="Translated Organization es." />
                 </div>
                 </div>
                 </div>
                 <div className="divider"></div>*/}
                <div className="scrolling menu">
                    <div className="item" data-value='-1'
                         data-text='All Projects'>
                        All
                    </div>
                    <div className="item" data-value='0'
                         data-text='No Workspace'>
                        No Workspace
                    </div>
                    {items}
                </div>
            </div>
        </div>;
        return result;
    }
    render () {
        let membersFilter = this.getUserFilter();
        let workspaceDropDown = this.getWorkspacesSelect();

        return (
            <section className="row sub-head">
                <div className="ui container equal width grid">
                    <div className="column">
                        {workspaceDropDown}
                    </div>
                    <div className="center aligned column">
                        {membersFilter}
                    </div>
                    <div className="column">
                        <div className="search-state-filters">
                            <SearchInput
                                onChange={this.onChangeSearchInput.bind(this)}/>
                            <FilterProjects
                                filterFunction={this.filterByStatus.bind(this)}/>
                        </div>
                    </div>

                </div>
            </section>
        );
    }
}

export default SubHeader ;