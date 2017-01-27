
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
        return <div className="change-workspace-modal" style={{minHeight: '300px'}}>
                    <div className="ui form">
                        <div className="field">
                            <label>Move this project</label>
                        </div>
                    </div>
                    <div className="row">
                        <div className="project-referral">
                             <h4>{this.props.project.get('name')}<span><a className="chip">{this.props.currentWorkspace.get('name')}</a></span></h4>
                        </div>
                    </div>
                    <div className="ui form">
                        <div className="field">
                            <label>into another workspace</label>
                        </div>
                    </div>
                    <div className="container-fluid">
                        <div className="row">
                            <div className="col m12">
                                {workspacesSelect}
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-footer">
                        <div className="actions">
                            <div className={"ui positive right labeled icon button " + buttonClass}
                                 onClick={this.changeWorkspace.bind(this)}>
                                Yes, Change Workspace
                                <i className="checkmark icon"/>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default ChangeProjectWorkspace ;
