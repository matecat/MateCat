
class CreateOrganization extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            errorInput: false,
            errorDropdown: false,
            textDropdown: 'Ex: joe@email.net',
            readyToSend: false
        };
        this.onLabelCreate = this.onLabelCreate.bind(this);
    }
    componentDidMount () {
        $(this.usersInput)
            .dropdown({
                allowAdditions: true,
                action: this.onLabelCreate,
            })
        ;
    }

    onLabelCreate(value, text){
        var self = this;
        if ( APP.checkEmail(text)) {
            $(this.usersInput)
                .dropdown('set selected', value);
            this.setState({
                errorDropdown: false
            });
        } else {

            this.setState({
                errorDropdown: true
            });
            setTimeout(function () {
                $(self.usersInput).find("input.search").val(text);
            });
        }
        return true;
    }

    checkMailDropDown() {
        let mail = $(this.usersInput).find("input.search").val();
        return ( mail !== '' || APP.checkEmail(mail))
    }

    onInputFocus() {
        let dropdownError = this.checkMailDropDown();
        this.setState({
            errorInput: false,
            errorDropdown: dropdownError,
        });
    }

    handleKeyPress(e) {
        if (this.inputNewOrg.value.length > 0 ) {
            this.setState({
                readyToSend: true,
            });
        } else {
            this.setState({
                readyToSend: false,
            });
        }
    }

    onClick(e) {
        e.stopPropagation();
        e.preventDefault();
        if (this.inputNewOrg.value.length > 0 && !this.state.errorDropdown) {
            var members = ($(this.usersInput).dropdown('get value').length > 0) ? $(this.usersInput).dropdown('get value').split(",") : [];
            ManageActions.createOrganization(this.inputNewOrg.value,  members);
            APP.ModalWindow.onCloseModal();
            this.inputNewOrg.value = '';
        } else if (this.inputNewOrg.value.length == 0 ) {
            this.setState({
                errorInput: true
            });
        }
        return false;
    }

    render() {
        var inputError = (this.state.errorInput) ? 'error' : '';
        var inputDropdown = (this.state.errorDropdown) ? 'error' : '';
        var buttonClass = (this.state.readyToSend && !this.state.errorInput && !this.state.errorDropdown ) ? '' : 'disabled';
        return  <div className="create-organization-modal">
                    <div className="matecat-modal-top">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Organization Name</h3>
                                <div className={"ui large fluid icon input " + inputError}>
                                    <input type="text" placeholder="Organization Name"
                                           onFocus={this.onInputFocus.bind(this)}
                                           onKeyUp={this.handleKeyPress.bind(this)}
                                           ref={(inputNewOrg) => this.inputNewOrg = inputNewOrg}/>
                                    <i className="icon-pencil icon"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-middle">
                        <div className="ui one column grid left aligned">
                            <div className="column">
                                <h3>Add members</h3>
                                <div className={"ui fluid multiple search selection dropdown " + inputDropdown}
                                     ref={(usersInput) => this.usersInput = usersInput}>
                                    <input name="tags" type="hidden" />
                                        <div className="default text">insert email or emails</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="matecat-modal-bottom">
                        <div className="ui one column grid right aligned">
                            <div className="column">
                                <button className={"ui button green " + buttonClass }
                                        onClick={this.onClick.bind(this)}>Create</button>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default CreateOrganization ;