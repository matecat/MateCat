
class ChangeProjectTeam extends React.Component {


    constructor(props) {
        super(props);
    }

    componentDidMount () {
        $('.menu .item').tab();
    }

    render() {
        return <div className="modify-team-modal">

            <div className="image content">
                <div className="description">
                    <div className="ui top attached tabular menu">
                          <div className="active item" data-tab="TeamSettings">Change Settings</div>
                          <div className="item" data-tab="TeamMembers">Members</div>
                    </div>
                    <div className="fixed-team-modal">
                        <div className="ui tab active" data-tab="TeamSettings">
                            {/*Tab Content*/}
                            <form className="ui form">
                                <div className="required field">
                                    <label>Change team name</label>
                                    <input type="text" name="Project Name" placeholder="es. Accounts, Project Managers, Translators" />
                                </div>
                                
                                <div className="field">
                                    <label>Advanced Options</label>
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
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
                                        <input type="checkbox" tabindex="0" className="hidden" />
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
                                        <input type="checkbox" tabindex="0" className="hidden" />
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
                                        <input type="checkbox" tabindex="0" className="hidden" />
                                        <label>Impostazione più facile di così si muore </label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
                                        <label>Impostazione più facile di così si muore </label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
                                        <label>Impostazione più facile di così si muore </label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
                                        <label>Impostazione più facile di così si muore </label>
                                    </div>
                                    <span>
                                        <a className="ui advanced-popup" data-tooltip="Add users to your feed" data-position="right center">
                                            <i className="icon-info"></i>
                                        </a>
                                    </span>
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
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
                    
                        <div className="ui tab" data-tab="TeamMembers">
                            {/*Tab Content list member*/}
                            <div className="row">
                                <form className="ui form">
                                    <div className="required field">
                                        <label>Add new Team Member</label>
                                        <input type="text" name="Project Name" placeholder="es. Accounts, Project Managers, Translators" />
                                    </div>
                                </form>
                            </div>
                            <div classname="row">
                                <div className="ui horizontal list">
                                
                                    <div className="item">
                                        <i className="right icon-search"></i>
                                        <div className=" ui avatar image initials green">RS</div>
                                        <div className="content"><span className="header"> Ruben Santillàn</span></div>
                                    </div>
                                
                                    <div className="item">
                                        <i className="right icon-search"></i>
                                        <div className=" ui avatar image initials green">RS</div>
                                        <div className="content"><span className="header"> Ruben Santillàn</span></div>
                                    </div>
                                
                                    <div className="item">
                                        <i className="right icon-search"></i>
                                        <div className=" ui avatar image initials green">RS</div>
                                        <div className="content"><span className="header"> Ruben Santillàn</span></div>
                                    </div>
                                
                                    <div className="item">
                                        <i className="right icon-search"></i>
                                        <div className=" ui avatar image initials green">RS</div>
                                        <div className="content"><span className="header"> Ruben Santillàn</span></div>
                                    </div>
                                </div>
                            </div>
                            <div className="row">
                                <div className="ui horizontal list">
                                    <div className="item">
                                        <i className="right icon-search"></i>
                                        <div className=" ui avatar image initials green">RS</div>
                                        <div className="content"><span className="header"> Ruben Santillàn</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="matecat-modal-footer">
                <div className="actions">
                    <div className="ui positive right labeled icon button" >
                        Salva Cambiamenti Team
                        <i className="checkmark icon"/>
                    </div>
                </div>
            </div>
        </div>;
    }
}


export default ChangeProjectTeam ;
