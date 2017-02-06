
class CreateOrganization extends React.Component {


    constructor(props) {
        super(props);
    }
    componentDidMount () {
        $('.ui.checkbox').checkbox();
        $('.advanced-popup').popup();
    }

    handleKeyPress(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if (this.inputNewOrg.value.length > 0) {
                ManageActions.createOrganization(this.inputNewOrg.value );
                APP.ModalWindow.onCloseModal();
                this.inputNewOrg.value = '';
            }
        }
        return false;
    }

    render() {
        return  <div className="create-organization-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Organization Name</h3>
                                <div className="ui large fluid icon input">
                                    <input type="text" placeholder="Organization Name"
                                           onKeyPress={this.handleKeyPress.bind(this)}
                                           ref={(inputNewOrg) => this.inputNewOrg = inputNewOrg}/>
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
                                    <input type="text" defaultValue="emails"/>
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