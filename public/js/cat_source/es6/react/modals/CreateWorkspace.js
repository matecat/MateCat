
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
        return <div className="success-modal">
                    <div className="image content">
                        <div className="description">
                            <form className="ui form">
                                <div className="required field">
                                    <label>New Workspace Name</label>
                                    <input type="text" name="Project Name" placeholder="Workspace name"
                                           ref={(workspaceInput) => this.workspaceInput = workspaceInput}/>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div className="matecat-modal-footer">
                        <div className="actions">
                            <div className="ui positive right labeled icon button"
                                 onClick={this.createWorkspace.bind(this)}>
                                Si Crea Workspace
                                <i className="checkmark icon"/>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default CreateWorkspace ;