
class CreateWorkspace extends React.Component {


    constructor(props) {
        super(props);
    }
    componentDidMount () {
    }

    createWorkspace() {
        ManageActions.createWorkspace(this.props.organization, $(this.workspaceInput).val());
        $(this.inputNewWS).val();
        APP.ModalWindow.onCloseModal();
    }

    getWorkspacesList() {
        return this.props.organization.get('workspaces').map((ws, i) => (
            <div className="item"
                 key={'user' + ws.get('id')}>
                <div className="right floated content">
                <div className="ui button">Modify Name</div>
                <div className="ui button">Remove</div>
                    </div>
                    <div className="content">
                        {ws.get('name')}
                </div>
            </div>

        ));
    }

    render() {
        var workspacesList = this.getWorkspacesList();
        return <div className="create-workspace-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Create New Workspace into ORGANIZATION </h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" placeholder="Workspace Name"
                                           ref={(inputNewWS) => this.inputNewWS = inputNewWS}/>
                                    <i className="icon-pencil icon"/>
                                </div>
                            </div>
                            <div className="column right aligned">
                                <button className="ui button green right aligned"
                                onClick={this.createWorkspace.bind(this)}>Create</button>
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-middle">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>ORGANIZATION Workspaces</h3>
                                <div className="column">
                                    <div className="ui segment members-list">
                                        <div className="ui middle aligned divided list">
                                            {workspacesList}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default CreateWorkspace ;