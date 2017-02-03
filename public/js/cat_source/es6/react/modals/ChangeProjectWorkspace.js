
class ChangeProjectWorkspace extends React.Component {


    constructor(props) {
        super(props);
        this.state ={
            buttonEnabled: false
        };
    }

    changeWorkspace() {
        ManageActions.changeProjectWorkspace(this.props.currentWorkspace.get('name'), this.selectedWorkspace.toJS(), this.props.project.get('id'));
        APP.ModalWindow.onCloseModal();
    }

    componentDidMount () {
        let self = this;
        $(this.dropdownWorkspaces).dropdown('set selected', ''+ this.props.currentWorkspace.get('id'));
        $(this.dropdownWorkspaces).dropdown({
            onChange: function(value, text, $selectedItem) {
                self.changeSelectedWorkspace(value);
            }
        });
    }

    changeSelectedWorkspace(value) {
        if (this.props.currentWorkspace.get('id') !== parseInt(value)) {
            this.selectedWorkspace = this.props.workspaces.find(function (workspace) {
                if (workspace.get('id') === parseInt(value)) {
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

    getWorkspacesSelect() {
        let result = '';
        if (this.props.workspaces.size > 0) {
            let items = this.props.workspaces.map((workspace, i) => (
                <div className="item" data-value={workspace.get('id')}
                     data-text={workspace.get('name')}
                     key={'workspace' + workspace.get('name') + workspace.get('id')}>
                    {workspace.get('name')}
                </div>
            ));
            result = <div className="ui dropdown selection fluid workspace-dropdown top-5"
                          ref={(dropdownWorkspaces) => this.dropdownWorkspaces = dropdownWorkspaces}>
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Workspace</div>
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
        let workspacesSelect = this.getWorkspacesSelect();
        let buttonClass =  (this.state.buttonEnabled)? '' : 'disabled';
        return <div className="change-workspace-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Create New Workspace into ORGANIZATION </h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" defaultValue={this.props.currentWorkspace.get('name')} disabled/>
                                </div>
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
                                                <div className="content">
                                                    Workspace 1
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="content">
                                                    Workspace 2
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="content">
                                                    Workspace 3
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="content">
                                                    Workspace 4
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="content">
                                                    Workspace 5
                                                </div>
                                            </div>
                                            <div className="item">
                                                <div className="content">
                                                    Workspace 6
                                                </div>
                                            </div>
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
