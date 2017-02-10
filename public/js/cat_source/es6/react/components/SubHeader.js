let FilterProjects = require("./FilterProjects").default;
let SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
        this.selectedUser = {};
        this.selectedWorkSpace = {};
    }

    componentDidUpdate() {
        let self = this;
        if (this.props.selectedOrganization) {

            // $(this.dropdownUsers).dropdown('set selected', 2000);
            $(this.dropdownUsers).dropdown({
                fullTextSearch: 'exact',
                onChange: function(value, text, $selectedItem) {
                    self.changeUser(value);
                }
            });

            $(this.dropdownWorkspaces).dropdown('set selected', '-1');
            $(this.dropdownWorkspaces).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.changeWorkspace(value);
                }
            });
        }
    }

    changeUser(value) {
        let self = this;
        this.selectedUser = this.props.selectedOrganization.get('members').find(function (member) {
            if (member.get("id") === parseInt(value)) {
                return true;
            }
        });
        ManageActions.filterProjects(self.selectedUser.toJS(), self.selectedWorkspace.toJS(), self.currentText, self.currentStatus);
    }

    changeWorkspace(value) {
        let self = this;
        if (value === 'all') {
            this.selectedWorkSpace =  {
                id: -1,
                name: 'all'
            };
        } else {
            this.selectedWorkSpace = this.props.selectedOrganization.get('workspaces').find(function (workspace) {
                if (workspace.get("id") === parseInt(value)) {
                    return true;
                }
            });
        }
        setTimeout(function () {
            ManageActions.filterProjects(self.selectedUser, self.selectedWorkSpace.toJS(), self.currentText, self.currentStatus);
        });
    }

    openCreateWorkspace() {
        ManageActions.openCreateWorkspaceModal(this.props.selectedOrganization);
    }


    onChangeSearchInput(value) {
        this.currentText = value;
        ManageActions.filterProjects(this.selectedUser, this.selectedWorkSpace, this.currentText, this.currentStatus);
    }

    filterByStatus(status) {
        this.currentStatus = status;
        ManageActions.filterProjects(this.selectedUser, this.selectedWorkSpace, this.currentText, this.currentStatus);
    }

    getUserFilter() {
        let result = '';
        if (this.props.selectedOrganization && this.props.selectedOrganization.get('type') === "general" && this.props.selectedOrganization.get('members')) {

            let members = this.props.selectedOrganization.get('members').map((member, i) => (
                <div className="item" data-value={member.get('uid')}
                     key={'user' + member.get('uid')}>
                    <a className="ui circular label">{APP.getUserShortName(member.toJS())}</a>
                    {(member.get('uid') === APP.USER.STORE.user.uid)? 'My Projects' : member.get('first_name') + ' ' + member.get('last_name')}
                </div>

            ));

            let item = <div className="item" data-value="0"
                            key={'user' + 0}>
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
        let items
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