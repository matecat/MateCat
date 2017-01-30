
class CreateOrganization extends React.Component {


    constructor(props) {
        super(props);
    }
    componentDidMount () {
        $('.ui.checkbox').checkbox();
        $('.advanced-popup').popup();
    }

    createOrganization() {
        $(this.organizationInput).val();
        ManageActions.createOrganization($(this.organizationInput).val());
        APP.ModalWindow.onCloseModal();
    }

    render() {
        return <div className="success-modal">

                    <div className="image content">
                        <div className="description">
                            <form className="ui form">
                                <div className="required field">
                                    <label>Organization Name</label>
                                    <input type="text" name="Project Name" placeholder="es. Accounts, Project Managers, Translators"
                                           ref={(organizationInput) => this.organizationInput = organizationInput}/>
                                </div>
                                <div className="field">
                                    <label>Add People to organization</label>
                                    <input type="email" name="email" placeholder="Name or Email/s separated by commas ',' " />
                                </div>
                                <div className="field">
                                    <label>Advanced Options</label>
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabIndex="0" className="hidden" />
                                        <label>Impostazioni Ebay</label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabIndex="0" className="hidden" />
                                        <label>Impostazioni DQF</label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabIndex="0" className="hidden" />
                                        <label>Impostazione tranquilla</label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabIndex="0" className="hidden" />
                                        <label>Impostazione più facile di così si muore </label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>

                            </form>
                        </div>
                    </div>
                    <div className="matecat-modal-footer">
                        <div className="actions">
                            <div className="ui positive right labeled icon button"
                            onClick={this.createOrganization.bind(this)}>
                                Si Crea Organization
                                <i className="checkmark icon"/>
                            </div>
                        </div>
                    </div>
                </div>;
    }
}


export default CreateOrganization ;