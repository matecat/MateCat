
class CreateWorkspace extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization
        };
        this.updateOrganization = this.updateOrganization.bind(this);
    }

    componentDidMount() {
        OrganizationsStore.addListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    componentWillUnmount() {
        OrganizationsStore.removeListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    createWorkspace() {
        ManageActions.createWorkspace(this.state.organization.toJS(), this.inputNewWS.value);
        this.inputNewWS.value = "";
    }

    updateOrganization(organization) {
        if (this.state.organization.get('id') == organization.get('id')) {
            this.setState({
                organization: organization
            });
        }
    }

    getWorkspacesList() {
        if (this.state.organization.get('workspaces')) {
            return this.state.organization.get('workspaces').map((ws, i) => (
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
        } else {
            return '';
        }
    }

    render() {
        let workspacesList = this.getWorkspacesList();
        let body = '';
        if (workspacesList.size > 0){
            body = <div className="matecat-modal-middle">
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
            </div>;
        }
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
                    {body}
                </div>;
    }
}


export default CreateWorkspace ;