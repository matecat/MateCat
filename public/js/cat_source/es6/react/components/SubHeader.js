let FilterProjects = require("./FilterProjects").default;
let SearchInput = require("./SearchInput").default;

class SubHeader extends React.Component {
    constructor (props) {
        super(props);
    }

    componentDidUpdate() {
        let self = this;
        if (this.props.selectedTeam) {

            $(this.dropdownUsers).dropdown('set selected', 2000);
            $(this.dropdownUsers).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.changeUser(value);
                }
            });
        }
    }

    changeUser(value) {
        let selectedUser = this.props.selectedTeam.get('users').find(function (user) {
            if (user.get("id") === parseInt(value)) {
                return true;
            }
        });
        setTimeout(function () {
            ManageActions.changeUser(selectedUser);
        });


    }

    getUserFilter() {
        let result = '';
        if (this.props.selectedTeam && this.props.selectedTeam.name !== 'all' && this.props.selectedTeam.get('users')) {

            let users = this.props.selectedTeam.get('users').map((user, i) => (
                <div className="item" data-value={user.get('id')}
                     key={'team' + user.get('userShortName') + user.get('id')}>
                    <a className="ui avatar image initials green">{user.get('userShortName')}</a>
                    {/*<img className="ui avatar image" src="http://semantic-ui.com/images/avatar/small/jenny.jpg"/>*/}
                    {(user.get('id') === 0)? 'My Projects' : user.get('userFullName')}
                </div>

            ));

            let item = <div className="item" data-value="2000"
                            key={'team' + config.userShortName + 2000}>
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
                            <div className="list-team">
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
    render () {
        let usersFilter = this.getUserFilter();
        return (
            <section className="sub-head z-depth-1">
                <div className="container-fluid">
                    <div className="row">
                        <div className="col m3">
                            <nav>
                                <div className="nav-wrapper">
                                    <SearchInput
                                        closeSearchCallback={this.props.closeSearchCallback}
                                        onChange={this.props.searchFn}/>
                                </div>
                            </nav>
                        </div>
                        <div className="col m3 offset-m2">
                            {usersFilter}
                        </div>
                        <div className="col m2 right">
                            <FilterProjects
                                filterFunction={this.props.filterFunction}/>
                        </div>
                    </div>
                </div>
            </section>
        );
    }
}

export default SubHeader ;