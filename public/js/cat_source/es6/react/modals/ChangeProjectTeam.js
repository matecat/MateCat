
class ChangeProjectTeam extends React.Component {


    constructor(props) {
        super(props);
        this.state ={
            buttonEnabled: false
        };
    }

    changeTeam() {
        ManageActions.changeProjectTeam(this.props.currentTeam.get('name'), this.selectedTeam.toJS(), this.props.projectId);
        APP.ModalWindow.onCloseModal();
    }

    componentDidMount () {
        let self = this;
        $(this.dropdownTeams).dropdown('set selected', ''+ this.props.currentTeam.get('id'));
        $(this.dropdownTeams).dropdown({
            onChange: function(value, text, $selectedItem) {
                self.changeSelectedTeam(value);
            }
        });
    }

    changeSelectedTeam(value) {
        if (this.props.currentTeam.get('id') !== parseInt(value)) {
            this.selectedTeam = this.props.teams.find(function (team) {
                if (team.get('id') === parseInt(value)) {
                    return true;
                }
            });
            this.setState({
                buttonEnabled: true
            });
        } else {
            this.setState({
                buttonEnabled: false
            });
        }
    }

    getTeamsSelect() {
        let result = '';
        if (this.props.teams.size > 0) {
            let items = this.props.teams.map((team, i) => (
                <div className="item" data-value={team.get('id')}
                     data-text={team.get('name')}
                     key={'team' + team.get('name') + team.get('id')}>
                    {team.get('name')}
                </div>
            ));
            result = <div className="ui dropdown selection fluid team-dropdown top-5"
                          ref={(dropdownTeams) => this.dropdownTeams = dropdownTeams}>
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Team</div>
                <div className="menu">
                    <div className="scrolling menu">
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render() {
        let teamsSelect = this.getTeamsSelect();
        let buttonClass =  (this.state.buttonEnabled)? '' : 'disabled';
        return <div className="change-team-modal" style={{minHeight: '300px'}}>
                    <div className="container-fluid">
                        <div className="row">
                            <div className="col m8">
                                {teamsSelect}
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-footer">
                        <div className="actions">
                            <div className={"ui positive right labeled icon button " + buttonClass}
                                 onClick={this.changeTeam.bind(this)}>
                                Si Cambia Team
                                <i className="checkmark icon"/>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default ChangeProjectTeam ;
