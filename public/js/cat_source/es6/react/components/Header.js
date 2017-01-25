
var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            teams: [],
            selectedTeam : null
        };
        this.renderTeams = this.renderTeams.bind(this);
        this.openModifyTeam = this.openModifyTeam.bind(this);
    }

    componentDidMount () {
        $('.team-dropdown').dropdown();
        TeamsStore.addListener(ManageConstants.RENDER_TEAMS, this.renderTeams);
    }

    componentWillUnmount() {
        TeamsStore.removeListener(ManageConstants.RENDER_TEAMS, this.renderTeams);
    }

    componentDidUpdate() {
        let self = this;

        if (this.state.teams.size > 0){
            let dropdownTeams = $('.team-dropdown');
            if (!this.state.selectedTeam) {
                dropdownTeams.dropdown('set selected', "0");
                dropdownTeams.dropdown({
                    onChange: function(value, text, $selectedItem) {
                        self.changeTeam(value);
                    }
                });
            }
            // else {
            //     setTimeout(function () {
            //         dropdownTeams.dropdown('set selected', "" + self.state.selectedTeam.get("id"));
            //     }, 100);
            // }
        }
    }

    changeTeam(value) {
        if (value === 'all') {
            ManageActions.changeTeam({name: value});
            this.setState({
                selectedTeam: {name: value, id: value}
            });
            return;
        }
        let selectedTeam = this.state.teams.find(function (team) {
            if (team.get("id") === parseInt(value)) {
                return true;
            }
        });
        ManageActions.changeTeam(selectedTeam.toJS());
        this.setState({
            selectedTeam: selectedTeam
        });
    }

    openCreateTeams () {
        ManageActions.openCreateTeamModal();
    }

    openModifyTeam (event, team) {
        event.stopPropagation();
        event.preventDefault();
        ManageActions.openModifyTeamModal(team);
    }

    renderTeams(teams, defaultTeam) {
        this.setState({
            teams : teams,
            selectedTeam: defaultTeam
        });
    }

    getTeamsSelect() {
        let result = '';
        if (this.state.teams.size > 0) {
            let items = this.state.teams.map((team, i) => (
                <div className="item" data-value={team.get('id')}
                     data-text={team.get('name')}
                     key={'team' + team.get('name') + team.get('id')}>
                        {team.get('name')}
                    <a className="team-filter button show right"
                       onClick={(e) => this.openModifyTeam(e, team)}>
                        <i className="icon-more_vert"/>
                    </a>
                </div>
            ));
            result = <div className="ui dropdown selection fluid team-dropdown top-5">
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Team</div>
                <div className="menu">
                    <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateTeams.bind(this)}>New Team
                        <a className="team-filter button show">
                            <i className="icon-plus3 right"/>
                        </a>
                    </div>
                    <div className="divider"></div>
                    {/*<div className="header">
                        <div className="ui form">
                            <div className="field">
                                <input type="text" name="Project Name" placeholder="Translated Team es." />
                            </div>
                        </div>
                    </div>
                    <div className="divider"></div>*/}
                    <div className="scrolling menu">
                        <div className="item" data-value='all'
                             data-text='All teams'>
                            All teams
                        </div>
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render () {
        let teamsSelect = this.getTeamsSelect();
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                    <a href="/" className="logo logo-col"/>

                                    <div className="col m2 right">
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
                        searchFn={this.props.searchFn}
                        closeSearchCallback={this.props.closeSearchCallback}
                        selectedTeam={this.state.selectedTeam}
                        />
                </section>;
    }
}
export default Header ;