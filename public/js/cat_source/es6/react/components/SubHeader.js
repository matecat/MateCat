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

            $(this.dropdownUsers).dropdown('set selected', 2000);
            $(this.dropdownUsers).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.changeUser(value);
                }
            });

            $(this.dropdownWorkspaces).dropdown('set selected', '0');
            $(this.dropdownWorkspaces).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.changeWorkspace(value);
                }
            });
        }
    }

    changeUser(value) {
        let self = this;
        this.selectedUser = this.props.selectedOrganization.get('users').find(function (user) {
            if (user.get("id") === parseInt(value)) {
                return true;
            }
        });
        setTimeout(function () {
            ManageActions.filterProjects(self.selectedUser, self.selectedWorkspace, self.currentText, self.currentStatus);
        });
    }

    changeWorkspace(value) {
        let self = this;
        if (value === 'all') {
            this.selectedWorkSpace =  {
                id: -1,
                name: 'all'
            };
        } else if (value === '0') {
            this.selectedWorkSpace =  {
                id: 0,
                name: 'General'
            };
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
        if (this.props.selectedOrganization && this.props.selectedOrganization.get('users')) {

            let users = this.props.selectedOrganization.get('users').map((user, i) => (
                <div className="item" data-value={user.get('id')}
                     key={'organization' + user.get('userShortName') + user.get('id')}>
                    <a className="ui avatar image initials green">{user.get('userShortName')}</a>
                    {/*<img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>*/}
                    {(user.get('id') === 0)? 'My Projects' : user.get('userFullName')}
                </div>

            ));

            let item = <div className="item" data-value="2000"
                            key={'organization' + config.userShortName + 2000}>
                <a className="ui avatar image initials green">ALL</a>
                {/*<img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>*/}
                All Members
            </div>;
            users = users.unshift(item);

            result = <div className="row">
                        <div className="col top-12">
                            <div className="assigned-list">
                                <p>Projects of: </p>
                            </div>
                        </div>
                        <div className="input-field col top-8">
                            <div className="list-organization">
                                <span>
                                    <div className="ui inline dropdown users-projects"
                                         ref={(dropdownUsers) => this.dropdownUsers = dropdownUsers}>
                                        <div className="text">
                                            <a className=" btn-floating green assigned-member center-align">{config.userShortName}</a>
                                          My Projects
                                        </div>
                                        <i className="dropdown icon"/>
                                        <div className="menu">
                                            {users}
                                        </div>
                                    </div>
                                </span>
                            </div>
                        </div>

                    </div>;
        }
        return result;
    }
    getWorkspacesSelect() {
        let result = '';
        if (this.props.selectedOrganization) {
            let items = this.props.selectedOrganization.get("workspaces").map((workspace, i) => (
                <div className="item" data-value={workspace.get('id')}
                     data-text={workspace.get('name')}
                     key={'organization' + workspace.get('name') + workspace.get('id')}>
                    {workspace.get('name')}
                </div>
            ));
            result = <div className="ui dropdown selection fluid organization-dropdown top-5"
                          ref={(dropdownWorkspaces) => this.dropdownWorkspaces = dropdownWorkspaces}>
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Workspace</div>
                <div className="menu">
                    <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateWorkspace.bind(this)}>New Workspace
                        <a className="organization-filter button show">
                            <i className="icon-plus3 right"/>
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
                        <div className="item" data-value='0'
                        data-text='General'>
                        General
                        </div>
                        <div className="item" data-value='-1'
                             data-text='All'>
                             All
                        </div>
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }
    render () {
        let usersFilter = this.getUserFilter();
        let workspaceDropDown = this.getWorkspacesSelect();

        return (
            <section className="sub-head z-depth-1">
                <div className="container-fluid">
                    <div className="row">
                        <div className="col m2">
                            {workspaceDropDown}
                        </div>
                        <div className="col m3 offset-m2">
                            {usersFilter}
                        </div>
                        <div className="col m3">
                            <nav>
                                <div className="nav-wrapper">
                                    <SearchInput
                                        onChange={this.onChangeSearchInput.bind(this)}/>
                                </div>
                            </nav>
                        </div>
                        <div className="col m2 right">
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