
class CreateWorkspace extends React.Component {


    constructor(props) {
        super(props);
    }
    componentDidMount () {
    }

    createWorkspace() {
        $(this.workspaceInput).val();
        // ManageActions.createOrganization($(this.workspaceInput).val());
        APP.ModalWindow.onCloseModal();
    }

    render() {
        return <div className="create-workspace-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Create New Workspace into ORGANIZATION </h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" placeholder="Workspace Name"/>
                                    <i className="icon-pencil icon"/>
                                </div>
                            </div>
                            <div className="column right aligned">
                                <button className="ui button green right aligned">Create</button>
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
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 1
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 2
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 3
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 4
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 5
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="right floated content">
                                                    <div className="ui button">Modify Name</div>
                                                    <div className="ui button">Remove</div>
                                                </div>
                                                <div className="content">
                                                    Workspace 6
                                                </div>
                                            </div>
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