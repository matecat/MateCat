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

    // renderAllOrganizationsProjects(projects, organizations, hideSpinner) {
    //     let more_projects = true;
    //     if (hideSpinner) {
    //         more_projects = this.state.more_projects
    //     }
    //     this.setState({
    //         projects: projects,
    //         more_projects: more_projects,
    //         reloading_projects: false,
    //         organization: null,
    //     });
    // }

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
        if (!items.size) {
            items = <div className="no-results-found"><span>No Project Found</span></div>;
        }


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
                <div className="one column spinner center aligned" style={{height: "100px"}}>
                        <div className="ui medium header" style={{ marginTop: "20px"}}>No more projects</div>
                </div>
            </div>;
        }

        if (!items.size) {
            items = <div className="no-results-found"><span>No Project Found</span></div>;
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
                zIndex: 2
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
