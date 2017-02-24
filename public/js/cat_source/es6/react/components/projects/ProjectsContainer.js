// var React = require('react');
let Project = require('./ProjectContainer').default;
let FilterProjects = require("../FilterProjects").default;


class ProjectsContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            projects : [],
            more_projects: true,
            reloading_projects: false,
            organization: null,
        };
        this.renderProjects = this.renderProjects.bind(this);
        this.updateProjects = this.updateProjects.bind(this);
        this.updateOrganization = this.updateOrganization.bind(this);
        this.hideSpinner = this.hideSpinner.bind(this);
        this.showProjectsReloadSpinner = this.showProjectsReloadSpinner.bind(this);
    }


    renderProjects(projects, organization, hideSpinner) {
        let more_projects = true;
        if (hideSpinner) {
            more_projects = this.state.more_projects
        }
        let organizationState = (organization)? organization : this.state.organization;
        this.setState({
            projects: projects,
            more_projects: more_projects,
            reloading_projects: false,
            organization: organizationState,
        });
    }

    updateOrganization(organization) {
        if (organization.get('id') === this.state.organization.get('id')) {
            this.setState({
                organization: organization,
            });
        }
    }

    updateProjects(projects) {
        this.setState({
            projects: projects,
        });
    }

    hideSpinner() {
        this.setState({
            more_projects: false
        });
    }

    showProjectsReloadSpinner() {
        this.setState({
            reloading_projects: true
        });
    }


    openAddMember () {
        ManageActions.openModifyOrganizationModal(this.state.organization.toJS());
    }

    openAddWorkspace() {
        ManageActions.openCreateWorkspaceModal(this.state.organization);
    }

    createNewProject() {
        window.open("/", '_blank');
    }

    getButtonsNoProjects() {
        if (!this.state.organization) return;
        let thereAreWS = (this.state.organization.get("workspaces") && this.state.organization.get("workspaces").size > 0);
        let thereAreMembers = (this.state.organization.get("members") && this.state.organization.get("members").size > 1) || this.state.organization.get('type') === 'personal';
        let containerClass = (!thereAreWS && !thereAreMembers) ? 'three' : ((!thereAreWS || !thereAreMembers) ? 'two' : 'one');
        return <div className="no-results-found">
            <div className={"ui " + containerClass +"  doubling cards"}>
                <div className="ui card button"
                onClick={this.createNewProject.bind(this)}>
                    <div className="content">
                        <div className="header">
                            <div className="add-more">+</div>
                        </div>
                        <div className="description">Add a New Project in the Organization {this.state.organization.get('name')}</div>
                    </div>
                </div>
                {!thereAreMembers ? (
                        <div className="ui card button"
                             onClick={this.openAddMember.bind(this)}>
                            <div className="content">
                                <div className="header">
                                    <div className="add-more">+</div>
                                </div>
                                <div className="description">Add a member in the Organization {this.state.organization.get('name')}</div>
                            </div>
                        </div>
                    ) : ('')}

                {!thereAreWS ? (
                        <div className="ui card button"
                             onClick={this.openAddWorkspace.bind(this)}>
                            <div className="content">
                                <div className="header">
                                    <div className="add-more">+</div>
                                </div>
                                <div className="description">Create a Workspace in the Organization {this.state.organization.get('name')}</div>
                            </div>
                        </div>
                ) : ('')}

            </div>
        </div>;
    }

    componentDidMount() {
        ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
        // ProjectsStore.addListener(ManageConstants.RENDER_ALL_ORGANIZATION_PROJECTS, this.renderAllOrganizationsProjects);
        ProjectsStore.addListener(ManageConstants.UPDATE_PROJECTS, this.updateProjects);
        ProjectsStore.addListener(ManageConstants.NO_MORE_PROJECTS, this.hideSpinner);
        ProjectsStore.addListener(ManageConstants.SHOW_RELOAD_SPINNER, this.showProjectsReloadSpinner);
        OrganizationsStore.addListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
        // ProjectsStore.removeListener(ManageConstants.RENDER_ALL_ORGANIZATION_PROJECTS, this.renderAllOrganizationsProjects);
        ProjectsStore.removeListener(ManageConstants.UPDATE_PROJECTS, this.updateProjects);
        ProjectsStore.removeListener(ManageConstants.NO_MORE_PROJECTS, this.hideSpinner);
        ProjectsStore.removeListener(ManageConstants.SHOW_RELOAD_SPINNER, this.showProjectsReloadSpinner);
        OrganizationsStore.removeListener(ManageConstants.UPDATE_ORGANIZATION, this.updateOrganization);
    }

    componentDidUpdate() {
        let self = this;
        if (!this.state.more_projects) {
            setTimeout(function () {
                $(self.spinner).css("visibility", "hidden");
            }, 3000);
        }
    }
    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.projects !== this.state.projects ||
        nextState.more_projects !== this.state.more_projects ||
        nextState.reloading_projects !== this.state.reloading_projects ||
        nextState.organization !== this.state.organization)
    }

    render() {
        let self = this;
        let projects = this.state.projects;

        let items = projects.map((project, i) => (
            <Project
                key={project.get('id')}
                project={project}
                lastActivityFn={this.props.getLastActivity}
                changeJobPasswordFn={this.props.changeJobPasswordFn}
                downloadTranslationFn={this.props.downloadTranslationFn}
                organization={this.state.organization}/>
        ));

        let spinner = '';
        if (this.state.more_projects && projects.size > 9) {
            spinner = <div className="ui one column shadow-1 grid">
                <div className="one column spinner" style={{height: "100px"}}>
                    <div className="ui active inverted dimmer">
                        <div className="ui medium text loader">Loading more projects</div>
                    </div>
                </div>
            </div>;
        } else if (projects.size > 9) {
            spinner = <div className="ui one column shadow-1 grid" ref={(spinner) => this.spinner = spinner}>
                <div className="one column spinner center aligned">
                        <div className="ui medium header">No more projects</div>
                </div>
            </div>;
        }

        if (!items.size) {
            items = this.getButtonsNoProjects();
            spinner = '';
        }

        var spinnerReloadProjects = '';
        if (this.state.reloading_projects) {
            var spinnerContainer = {
                position: 'absolute',
                height : '100%',
                width : '100%',
                backgroundColor: 'rgba(76, 69, 69, 0.3)',
                top: $(window).scrollTop(),
                left: 0,
                zIndex: 3
            };
            spinnerReloadProjects =<div style={spinnerContainer}>
                    <div className="ui active inverted dimmer">
                        <div className="ui massive text loader">Updating Projects</div>
                    </div>
                </div>;
        }



        return <div>
                    <div className="project-list">
                        <div className="ui container">
                            {spinnerReloadProjects}
                            {items}
                            {spinner}
                        </div>

                    </div>
                </div>;
    }
}


export default ProjectsContainer ;