
var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            organizations: [],
            selectedOrganizationId : null
        };
        this.renderOrganizations = this.renderOrganizations.bind(this);
        this.updateOrganizations = this.updateOrganizations.bind(this);
        this.chooseOrganizations = this.chooseOrganizations.bind(this);
        this.openModifyOrganization = this.openModifyOrganization.bind(this);
    }

    componentDidMount () {
        if (this.state.selectedOrganizationId) {
            dropdownOrganizations.dropdown('set selected',  this.state.selectedOrganizationId);
        }
        OrganizationsStore.addListener(ManageConstants.RENDER_ORGANIZATIONS, this.renderOrganizations);
        OrganizationsStore.addListener(ManageConstants.UPDATE_ORGANIZATIONS, this.updateOrganizations);
        OrganizationsStore.addListener(ManageConstants.CHOOSE_ORGANIZATION, this.chooseOrganizations);
    }

    componentWillUnmount() {
        OrganizationsStore.removeListener(ManageConstants.RENDER_ORGANIZATIONS, this.renderOrganizations);
        OrganizationsStore.removeListener(ManageConstants.UPDATE_ORGANIZATIONS, this.updateOrganizations);
        OrganizationsStore.removeListener(ManageConstants.CHOOSE_ORGANIZATION, this.chooseOrganizations);
    }

    componentDidUpdate() {
        let self = this;
        if (this.state.organizations.size > 0){
            let dropdownOrganizations = $(this.dropdownOrganizations);
            if (this.state.selectedOrganizationId ) {
                setTimeout(function () {
                    dropdownOrganizations.dropdown('set selected', self.state.selectedOrganizationId);
                });
            } else {
                dropdownOrganizations.dropdown();
            }
        }
    }

    changeOrganization(event, organization) {
        if (this.props.showSubHeader) {
            if (organization.get('id')  !== this.state.selectedOrganizationId) {
                let selectedOrganization = this.state.organizations.find(function (org) {
                    if (org.get("id") === organization.get("id")) {
                        return true;
                    }
                });
                ManageActions.changeOrganization(selectedOrganization.toJS());
            }
        }
    }

    openCreateOrganizations () {
        ManageActions.openCreateOrganizationModal();
    }

    openModifyOrganization (event, organization) {
        event.stopPropagation();
        event.preventDefault();
        $(this.dropdownOrganizations).dropdown('set selected', '' + this.state.selectedOrganizationId);
        ManageActions.openModifyOrganizationModal(organization.toJS());
    }

    renderOrganizations(organizations) {
        this.setState({
            organizations : organizations
        });
    }

    updateOrganizations(organizations) {
        this.setState({
            organizations : organizations,
        });
    }

    chooseOrganizations(id) {
        this.setState({
            selectedOrganizationId : id,
        });
    }

    getOrganizationsSelect() {
        let result = '';
        var self = this;
        if (this.state.organizations.size > 0) {
            let items = this.state.organizations.map(function(organization, i) {
                let iconModal = '';
                if (self.props.showModals) {
                    iconModal = <a className="organization-filter button show right"
                                   onClick={(e) => self.openModifyOrganization(e, organization)}>
                        <i className="icon-more_vert icon"/>
                    </a>
                }
                return <div className="item" data-value={organization.get('id')}
                     data-text={organization.get('name')}
                     key={'organization' + organization.get('name') + organization.get('id')}
                     onClick={(e) => self.changeOrganization(e, organization)}>
                    {organization.get('name')}
                    {iconModal}
                </div>
            });
            let addOrg = '';
            if (self.props.showModals) {
                addOrg = <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateOrganizations.bind(this)}>New Organization
                                <a className="organization-filter button show">
                                    <i className="icon-plus3 icon"/>
                                </a>
                            </div>
            }
            result = <div className="ui dropdown fluid selection organization-dropdown top-5"
                          ref={(dropdownOrganizations) => this.dropdownOrganizations = dropdownOrganizations}>
                <input type="hidden" name="organization" className="organization-dd" />
                <i className="dropdown icon"/>
                <div className="default text">Choose organization</div>
                <div className="menu">
                    {addOrg}
                    { self.props.showModals ? (
                            <div className="divider"></div>
                        ): (
                            ''
                        )}
                    <div className="scrolling menu">
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render () {
        let self = this;
        let organizationsSelect = this.getOrganizationsSelect();
        let selectedOrganization =  this.state.organizations.find(function (org) {
            return org.get('id') == self.state.selectedOrganizationId;
        });
        let subHeader = '';
        if (this.props.showSubHeader) {
            subHeader = <SubHeader
                selectedOrganization={selectedOrganization}
            />;
        }

        return <section className="ui grid nav-mc-bar">

                    <nav className="four column row">
                        <div className="left floated column">
                            <a href="/" className="logo logo-col"/>
                        </div>
                        {this.props.showLinks ? (
                                <div className="right floated column">
                                    <ul className="pull-right" id="menu-site">
                                        <li><a href="https://www.matecat.com/benefits/">Benefits</a></li>
                                        <li><a href="https://www.matecat.com/outsourcing/">Outsource</a></li>
                                        <li><a href="https://www.matecat.com/support-plans/">Plans</a></li>
                                        <li><a href="https://www.matecat.com/about/">About</a></li>
                                        <li><a href="https://www.matecat.com/faq/">FAQ</a></li>
                                        <li><a href="https://www.matecat.com/support/">Support</a></li>
                                        <li><a className="bigred" href="https://www.matecat.com/webinar" target="_blank">Webinar</a></li>
                                    </ul>
                                </div>
                            ) : ('')}

                        { this.props.loggedUser ? (
                                <div className="right floated column">
                                    {organizationsSelect}
                                </div>
                            ) :(
                                ''
                            )}

                    </nav>
                    {subHeader}
                </section>;
    }
}

Header.defaultProps = {
    showSubHeader: true,
    showModals: true,
    showLinks: false,
    loggedUser: true
};

export default Header ;