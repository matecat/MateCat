
class ChangeProjectWorkspace extends React.Component {


    constructor(props) {
        super(props);
        this.state ={};
    }

    changeWorkspace() {
        // ManageActions.changeProjectWorkspace(this.props.currentWorkspace.get('name'), this.selectedWorkspace.toJS(), this.props.project.get('id'));
        APP.ModalWindow.onCloseModal();
    }

    componentDidMount () {
    }

    getWorkspacesList() {
        let self = this;
        if (this.props.workspaces) {
            return this.props.workspaces.map(function (ws, i) {
                return <div className="item"
                            key={'user' + ws.get('id')}>
                            <div className="content">
                                {ws.get('name')}
                            </div>
                        </div>;
            });

        } else {
            return '';
        }
    }

    render() {
        let workspacesList = this.getWorkspacesList();
        return <div className="change-workspace-modal">
            <div className="matecat-modal-top">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Send this project:</h3>
                        <div className="ui teal label">
                            <span className="project-id">929830</span> (archived)
                        </div>
                        <span className="project-name">NOME_PROGETTO.TXT</span>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-middle">
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <h3>Choose new Workspace</h3>
                        <div className="column">
                            <div className="ui middle aligned selection divided list">
                                <div className="item">
                                    <div className="content">
                                        <div className="header">Workspace 1</div>
                                    </div>
                                </div>
                                <div className="item active">
                                    <div className="content">
                                        <div className="header">Workspace 2</div>
                                    </div>
                                </div>
                                <div className="item">
                                    <div className="content">
                                        <div className="header">Workspace 3</div>
                                    </div>
                                </div>
                                <div className="item">
                                    <div className="content">
                                        <div className="header">Workspace 4</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="column right aligned">
                        <div className="column">
                            <button className="ui button green right aligned"
                            onClick={this.changeWorkspace.bind(this)}>Move</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>;
    }
}


export default ChangeProjectWorkspace ;
