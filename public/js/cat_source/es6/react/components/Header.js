
var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {
        super(props);
        this.state = {
            organizations: [],
            selectedOrganization : null
        };
        this.renderOrganizations = this.renderOrganizations.bind(this);
        this.openModifyOrganization = this.openModifyOrganization.bind(this);
    }

    componentDidMount () {
        $('.organization-dropdown').dropdown();
        OrganizationsStore.addListener(ManageConstants.RENDER_ORGANIZATIONS, this.renderOrganizations);
    }

    componentWillUnmount() {
        OrganizationsStore.removeListener(ManageConstants.RENDER_ORGANIZATIONS, this.renderOrganizations);
    }

    componentDidUpdate() {
        let self = this;

        if (this.state.organizations.size > 0){
            let dropdownOrganizations = $('.organization-dropdown');
            if (!this.state.selectedOrganization) {
                dropdownOrganizations.dropdown('set selected', "0");
                dropdownOrganizations.dropdown({
                    onChange: function(value, text, $selectedItem) {
                        self.changeOrganization(value);
                    }
                });
            }
            // else {
            //     setTimeout(function () {
            //         dropdownOrganizations.dropdown('set selected', "" + self.state.selectedOrganization.get("id"));
            //     }, 100);
            // }
        }
    }

    changeOrganization(value) {
        if (value === 'all') {
            ManageActions.changeOrganization({name: value});
            this.setState({
                selectedOrganization: {name: value, id: value}
            });
            return;
        }
        let selectedOrganization = this.state.organizations.find(function (organization) {
            if (organization.get("id") === parseInt(value)) {
                return true;
            }
        });
        ManageActions.changeOrganization(selectedOrganization.toJS());
        this.setState({
            selectedOrganization: selectedOrganization
        });
    }

    openCreateOrganizations () {
        ManageActions.openCreateOrganizationModal();
    }

    openModifyOrganization (event, organization) {
        event.stopPropagation();
        event.preventDefault();
        ManageActions.openModifyOrganizationModal(organization);
    }

    renderOrganizations(organizations, defaultOrganization) {
        this.setState({
            organizations : organizations,
            selectedOrganization: defaultOrganization
        });
    }

    getOrganizationsSelect() {
        let result = '';
        if (this.state.organizations.size > 0) {
            let items = this.state.organizations.map((organization, i) => (
                <div className="item" data-value={organization.get('id')}
                     data-text={organization.get('name')}
                     key={'organization' + organization.get('name') + organization.get('id')}>
                        {organization.get('name')}
                    <a className="organization-filter button show right"
                       onClick={(e) => this.openModifyOrganization(e, organization)}>
                        <i className="icon-more_vert"/>
                    </a>
                </div>
            ));
            result = <div className="ui dropdown selection fluid organization-dropdown top-5">
                <input type="hidden" name="gender" />
                <i className="dropdown icon"/>
                <div className="default text">Choose Organization</div>
                <div className="menu">
                    <div className="header" style={{cursor: 'pointer'}} onClick={this.openCreateOrganizations.bind(this)}>New Organization
                        <a className="organization-filter button show">
                            <i className="icon-plus3 right"/>
                        </a>
                    </div>
                    <div className="divider"></div>
                    {/*<div className="header">
                        <div className="ui form">
                            <div className="field">
                                <input type="text" name="Project Name" placeholder="Translated Organization es." />
                            </div>
                        </div>
                    </div>
                    <div className="divider"></div>*/}
                    <div className="scrolling menu">
                        {/*<div className="item" data-value='all'*/}
                             {/*data-text='All organizations'>*/}
                            {/*All organizations*/}
                        {/*</div>*/}
                        {items}
                    </div>
                </div>
            </div>;
        }
        return result;
    }

    render () {
        let organizationsSelect = this.getOrganizationsSelect();
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                    <a href="/" className="logo logo-col"/>

                                    <div className="col m2 right">
                                        {organizationsSelect}
                                    </div>
                         
                                        

                                    <div className="col l2 right profile-area">
                                        <ul className="right">
                                            <li>
                                                <a href="" className="right waves-effect waves-light">
                                                    <span id="nome-cognome" className="hide-on-med-and-down">Nome Cognome </span>
                                                    <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </nav>
                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        searchFn={this.props.searchFn}
                        closeSearchCallback={this.props.closeSearchCallback}
                        selectedOrganization={this.state.selectedOrganization}
                        />
                </section>;
    }
}
export default Header ;