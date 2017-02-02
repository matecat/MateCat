
class CreateOrganization extends React.Component {


    constructor(props) {
        super(props);
    }
    componentDidMount () {
        $('.ui.checkbox').checkbox();
        $('.advanced-popup').popup();
    }

    createOrganization() {
        $(this.workspaceInput).val();
        ManageActions.createOrganization($(this.workspaceInput).val());
        APP.ModalWindow.onCloseModal();
    }

    handleKeyPress() {

    }

    render() {
        return  <div className="create-organization-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Organization Name</h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" placeholder="Organization Name"/>
                                    <i className="icon-pencil icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-middle">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Add member</h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" defaultValue="emails"
                                           onKeyPress={this.handleKeyPress.bind(this)}
                                           ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-bottom">
                        <div className="ui one column grid right aligned">
                            <div className="column">
                                <button className="ui button green">Create</button>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default CreateOrganization ;