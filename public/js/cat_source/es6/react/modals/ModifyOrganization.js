let Immutable = require('immutable');
class ModifyOrganization extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            organization: this.props.organization
        }
    }

    componentDidMount () {
        $('.menu .item').tab();
        $('.fixed-organization-modal .ui.checkbox').checkbox();
    }

    removeUser(user, index) {
        let newOrganization = this.state.organization.set('users', this.state.organization.get('users').delete(index));
        this.setState({
            organization: newOrganization
        });
    }

    handleKeyPress(e) {
        e.stopPropagation();
        if (e.key === 'Enter' ) {
            e.preventDefault();
            if (this.inputNewUSer.value.length > 4) {

                let newUser = {
                    id: Math.floor((Math.random() * 100) + 1),
                    userMail: '@translated.net',
                    userFullName: this.inputNewUSer.value,
                    userShortName: 'XX'

                };
                this.setState({
                    organization: this.state.organization.set('users', this.state.organization.get('users').push(Immutable.fromJS(newUser)))
                });

                this.inputNewUSer.value = '';
            }
        }
        return false;
    }

    getUserList() {
        return this.state.organization.get('users').map((user, i) => (
            <a className="item list-member"
               key={'user' + user.get('userShortName') + user.get('id')}>
                <i className="right icon-cancel3" onClick={this.removeUser.bind(this, user, i)}/>
                <div className=" ui avatar image initials green">{user.get('userShortName')}</div>
                <div className="content"><span className="header">{user.get('userFullName')}</span></div>
            </a>

        ));

    }

    render() {
        let userlist = this.getUserList();
        return <div className="modify-organization-modal">

            <div className="image content">
                <div className="description">
                    <div className="ui top attached tabular menu">
                        <div className="active item" data-tab="OrganizationSettings">Change Settings</div>
                        <div className="item" data-tab="OrganizationMembers">Members</div>
                    </div>
                    <div className="fixed-organization-modal">
                        <div className="ui tab active" data-tab="OrganizationSettings">
                            {/*Tab Content*/}
                            <form className="ui form">
                                <div className="required field">
                                    <label>Change organization name</label>
                                    <input type="text" name="Project Name" defaultValue={this.props.organization.get('name')} />
                                </div>

                                <div className="row">
                                    <div className="ui form">
                                        <div className="field">
                                            <label>Advanced Options</label>
                                        </div>
                                    </div>
                                </div>

                                <div className="field">
                                    
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabIndex="0" className="hidden" />
                                        <label>Impostazioni Ebay</label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
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
                                            <i className="icon-info"/>
                                        </a>
                                    </span>
                                </div>

                            </form>
                        </div>

                        <div className="ui tab" data-tab="OrganizationMembers">
                            {/*Tab Content list member*/}
                            <div className="row">
                                <form className="ui form">
                                    <div className="required field">
                                        <label>Add new Organization Member</label>
                                        <input type="text" name="Project Name" placeholder="es. Accounts, Project Managers, Translators"
                                               onKeyPress={this.handleKeyPress.bind(this)}
                                               ref={(inputNewUSer) => this.inputNewUSer = inputNewUSer}/>
                                    </div>
                                </form>
                            </div>
                            <div className="row">
                                <div className="ui form">
                                    <div className="field">
                                        <label>All Organization Members</label>
                                    </div>
                                </div>
                            </div>
                            <div className="row">
                                <div className="ui horizontal list organization-settings">
                                    {userlist}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-footer">
                <div className="actions">
                    <div className="ui positive right labeled icon button" >
                        Salva Cambiamenti Organization
                        <i className="checkmark icon"/>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ModifyOrganization ;