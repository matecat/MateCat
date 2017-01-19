
var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            teams: []
        };
        this.renderTeams = this.renderTeams.bind(this);
    }
    componentDidMount () {
        $('.team-dropdown').dropdown();
        TeamsStore.addListener(ManageConstants.RENDER_TEAMS, this.renderTeams);
    }

    componentWillUnmount() {
        TeamsStore.removeListener(ManageConstants.RENDER_TEAMS, this.renderTeams);
    }

    componentDidUpdate() {
        var self = this;
        if (!this.selectedTeam) {
            var dropdownTeams = $('.team-dropdown');
            dropdownTeams.dropdown('set selected', 'Personal');
            dropdownTeams.dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.changeTeam(value);
                }
            });
        }

    }

    changeTeam(value) {
        this.selectedTeam = value;
    }

    openCreateTeams () {
        ManageActions.openCreateTeamModal();
    }

    openModifyTeam (name) {
        var team = {
            name: name
        };
        ManageActions.openModifyTeamModal(team);
    }

    renderTeams(teams) {
        this.setState({
            teams : teams
        });
    }

    getTeamsSelect() {
        var result = '';
        if (this.state.teams.length > 0) {
            result = <div className="ui fluid selection dropdown team-dropdown top-5">
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Team</div>
                <div className="menu">
                    <div className="header">Create Workspace
                        <a className="team-filter button show"
                           onClick={this.openCreateTeams.bind(this)}>
                            <i className="icon-plus3 right"/>
                        </a>
                    </div>
                    <div className="item" data-value="Personal" data-text="Personal">Personal

                    </div>
                    <div className="item" data-value="Ebay" data-text="Ebay">Ebay
                        <a className="team-filter button show right"
                           onClick={this.openModifyTeam.bind(this, 'Ebay')}>
                            <i className="icon-more_vert"/>
                        </a>
                    </div>
                    <div className="item" data-value="MSC" data-text="MSC">MSC
                        <a className="team-filter button show right"
                           onClick={this.openModifyTeam.bind(this, 'MSC')}>
                            <i className="icon-more_vert"/></a>
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render () {
        var teamsSelect = this.getTeamsSelect();
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                    <a href="/" className="logo logo-col"/>

                                    <div className="col m2 offset-m8">
                                        {teamsSelect}
                                    </div>
                         
                                        

                                    <div className="col l2 right profile-area">
                                        <ul className="right">
                                            <li>
                                                <a href="" className="right waves-effect waves-light">
                                                    <span id="nome-cognome" className="hide-on-med-and-down">Nome Cognome </span>
                                                    <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </nav>
                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        />
                </section>;
    }
}
export default Header ;