
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
            if (this.state.selectedOrganizationId) {
                dropdownOrganizations.dropdown('set selected', '' + this.state.selectedOrganizationId);
                // dropdownOrganizations.dropdown({
                //     onChange: function(value, text, $selectedItem) {
                //         self.changeOrganization(value);
                //     }
                // });
            }
        }
    }

    changeOrganization(event, organization) {
        let selectedOrganization = this.state.organizations.find(function (org) {
            if (org.get("id") === organization.get("id")) {
                return true;
            }
        });
        ManageActions.changeOrganization(selectedOrganization.toJS());
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
        if (this.state.organizations.size > 0) {
            let items = this.state.organizations.map((organization, i) => (
                <div className="item" data-value={organization.get('id')}
                     data-text={organization.get('name')}
                     key={'organization' + organization.get('name') + organization.get('id')}
                     onClick={(e) => this.changeOrganization(e, organization)}>
                        {organization.get('name')}
                    <a className="organization-filter button show right"
                       onClick={(e) => this.openModifyOrganization(e, organization)}>
                        <i className="icon-more_vert icon"/>
                    </a>
                </div>
            ));
            result = <div className="ui dropdown fluid selection organization-dropdown top-5"
                          ref={(dropdownOrganizations) => this.dropdownOrganizations = dropdownOrganizations}>
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Organization</div>
                <div className="menu">
                    <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateOrganizations.bind(this)}>New Organization
                        <a className="organization-filter button show">
                            <i className="icon-plus3 icon"/>
                        </a>
                    </div>
                    <div className="divider"></div>
                    <div className="scrolling menu">
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render () {
        var self = this;
        let organizationsSelect = this.getOrganizationsSelect();
        var selectedOrganization =  this.state.organizations.find(function (org) {
            return org.get('id') == self.state.selectedOrganizationId;
        });
        return <section className="ui grid nav-mc-bar">

                    <nav className="four column row">
                        <div className="left floated column">
                            <a href="/" className="logo logo-col"/>
                        </div>
                        <div className="right floated column">

                            {organizationsSelect}
                        </div>
                    </nav>
                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        searchFn={this.props.searchFn}
                        closeSearchCallback={this.props.closeSearchCallback}
                        selectedOrganization={selectedOrganization}
                        />
                </section>;
    }
}
export default Header ;