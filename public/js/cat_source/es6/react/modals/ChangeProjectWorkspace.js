
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
                    <div className="matecat-modal-middle">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Choose new Workspace</h3>
                                <div className="column">
                                    <div className="ui segment">
                                        <div className="ui middle aligned divided list">
                                            {workspacesList}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="matecat-modal-bottom">
                            <div className="ui one column grid right aligned">
                                <div className="column">
                                    <button className="ui button green"
                                            onClick={this.changeWorkspace.bind(this)}>Move</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default ChangeProjectWorkspace ;
