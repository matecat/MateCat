
class CreateWorkspace extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization,
            showRemoveMessageWSId: null,
            showModifyMessageWSId: null,
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
        if ( this.inputNewWS.value.length > 0) {
            ManageActions.createWorkspace(this.state.organization.toJS(), this.inputNewWS.value);
            this.inputNewWS.value = "";
        }
    }

    showRemoveWS(WSId) {
        this.setState({
            showRemoveMessageWSId: WSId
        });
    }

    showModifyWS(WSId) {
        this.setState({
            showModifyMessageWSId: WSId
        });
    }

    removeWS(ws) {
        ManageActions.removeWorkspace(this.state.organization.toJS(), ws.toJS());
    }

    undoRemoveAction() {
        this.setState({
            showRemoveMessageWSId: null,
            showModifyMessageWSId: null
        });
    }

    updateOrganization(organization) {
        if (this.state.organization.get('id') == organization.get('id')) {
            this.setState({
                organization: organization,
                showRemoveMessageWSId: null,
                showModifyMessageWSId: null
            });
        }
    }

    modifyWSName(ws) {
        if (this.wsName.value != "" && this.wsName.value !== this.state.organization.get('name')) {
            ws = ws.set("name", this.wsName.value);
            ManageActions.renameWorkspace(this.state.organization, ws);
        }
    }

    handleKeyPressInModify(ws, event) {
        if(event.key == 'Enter'){
            this.modifyWSName(ws);
        }
    }

    handleKeyPressInCreate(event) {
        if(event.key == 'Enter'){
            this.createWorkspace();
        }
    }

    getWorkspacesList() {
        let self = this;
        if (this.state.organization.get('workspaces')) {
            return this.state.organization.get('workspaces').map(function(ws, i) {
                if (self.state.showRemoveMessageWSId == ws.get('id')) {
                    return <div className="item"
                                key={'WS' + ws.get('id')}>
                        <div className="right floated content">
                            <div className="ui button green" onClick={self.removeWS.bind(self, ws)}>YES
                            </div>
                        </div>
                        <div className="right floated content">
                            <div className="ui button red" onClick={self.undoRemoveAction.bind(self)}>NO</div>
                        </div>
                        <div className="content">
                            Are you sure you want to remove this workspace?
                        </div>
                    </div>
                } if (self.state.showModifyMessageWSId == ws.get('id')) {
                    return <div className="item"
                                key={'WS' + ws.get('id')}>
                        <div className="right floated content">
                            <div className="ui button green" onClick={self.modifyWSName.bind(self, ws)}>OK</div>
                        </div>
                        <div className="right floated content">
                            <div className="ui button red" onClick={self.undoRemoveAction.bind(self)}>CANCEL</div>
                        </div>
                        <div className="content">
                            <div className="ui input focus">
                                <input type="text" defaultValue={ws.get('name')}
                                onKeyPress={self.handleKeyPressInModify.bind(self, ws)}
                                       ref={(wsName) => self.wsName = wsName}/>
                            </div>
                        </div>
                    </div>;
                } else {
                    return <div className="item"
                                key={'user' + ws.get('id')}>
                        <div className="right floated content">
                            <div className="ui button" onClick={self.showModifyWS.bind(self, ws.get('id'))}>Modify Name</div>
                            <div className="ui button" onClick={self.showRemoveWS.bind(self, ws.get('id'))}>Remove</div>
                        </div>
                        <div className="content">
                            {ws.get('name')}
                        </div>
                    </div>;
                }
            });

        } else {
            return '';
        }
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.organization !== this.state.organization ||
        nextState.showRemoveMessageWSId !== this.state.showRemoveMessageWSId ||
        nextState.showModifyMessageWSId !== this.state.showModifyMessageWSId)
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
                                           ref={(inputNewWS) => this.inputNewWS = inputNewWS}
                                           onKeyPress={this.handleKeyPressInCreate.bind(this)}/>
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