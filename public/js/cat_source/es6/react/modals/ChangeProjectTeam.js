
class ChangeProjectWorkspace extends React.Component {


    constructor(props) {
        super(props);
        this.state ={
            selectedTeam: this.props.selectedTeam
        };
    }

    changeTeam() {
        ManageActions.changeProjectTeam(this.state.selectedTeam,  this.props.project);
        APP.ModalWindow.onCloseModal();
    }

    componentDidMount () {
    }

    selectTeam(teamId) {
        this.setState({
            selectedTeam: teamId
        })
    }

    getTeamsList() {
        let self = this;
        if (this.props.teams) {
            return this.props.teams.map(function (team, i) {
                let selectedClass = (self.state.selectedTeam == team.get('id')) ? 'active' : '';
                return <div className={"item " + selectedClass}
                            key={'team' + team.get('id')}
                            onClick={self.selectTeam.bind(self, team.get('id'))}>
                            <div className="content" >
                                {team.get('name')}
                            </div>
                        </div>;
            });

        } else {
            return '';
        }
    }

    render() {
        let teamsList = this.getTeamsList();
        return <div className="change-team-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Move this project:</h3>
                        <div className="ui label">
                            <span className="project-id">ID: {this.props.project.get('id')}</span>
                        </div>
                        <span className="project-name"> {this.props.project.get('name')}</span>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-middle">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Choose new Team</h3>
                        <div className="column">
                            <div className="ui middle aligned selection divided list">
                                {teamsList}
                            </div>
                        </div>
                    </div>
                    <div className="column right aligned">
                        <div className="column">
                            <button className="ui button blue right aligned"
                            onClick={this.changeTeam.bind(this)}>Move</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>;
    }
}


export default ChangeProjectWorkspace ;
