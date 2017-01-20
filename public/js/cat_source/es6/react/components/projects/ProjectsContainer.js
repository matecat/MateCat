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
            user: null,
            team: null
        };
        this.renderProjects = this.renderProjects.bind(this);
        this.hideSpinner = this.hideSpinner.bind(this);
        this.showProjectsReloadSpinner = this.showProjectsReloadSpinner.bind(this);
        this.filterForUser = this.filterForUser.bind(this);
    }


    renderProjects(projects, team, hideSpinner) {
        let more_projects = true;
        if (hideSpinner) {
            more_projects = this.state.more_projects
        }
        let teamState = (team)? team : this.state.team;
        this.setState({
            projects: projects,
            more_projects: more_projects,
            reloading_projects: false,
            user: null,
            team: teamState
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

    filterForUser(user) {
        this.setState({
            user: user
        });
    }

    componentDidMount() {
        ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
        ProjectsStore.addListener(ManageConstants.NO_MORE_PROJECTS, this.hideSpinner);
        ProjectsStore.addListener(ManageConstants.SHOW_RELOAD_SPINNER, this.showProjectsReloadSpinner);
        TeamsStore.addListener(ManageConstants.CHANGE_USER, this.filterForUser);
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
        ProjectsStore.removeListener(ManageConstants.NO_MORE_PROJECTS, this.hideSpinner);
        ProjectsStore.removeListener(ManageConstants.SHOW_RELOAD_SPINNER, this.showProjectsReloadSpinner);
        TeamsStore.removeListener(ManageConstants.CHANGE_USER, this.filterForUser);

    }

    componentDidUpdate() {
        let self = this;
        if (!this.state.more_projects) {
            setTimeout(function () {
                $(self.spinner).fadeOut();
            }, 3000);
        }
    }
    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.projects !== this.state.projects ||
        nextState.more_projects !== this.state.more_projects ||
        nextState.reloading_projects !== this.state.reloading_projects ||
        nextState.user !== this.state.user)
    }

    render() {
        let self = this;
        let projects = this.state.projects;

        if (this.state.user) {
            projects = this.state.projects.filter(function (project) {
                if (project.get('user').get('id') == self.state.user.get('id')) {
                    return true;
                }
            });
        }
        let items = projects.map((project, i) => (
            <Project
                key={project.get('id')}
                project={project}
                lastActivityFn={this.props.getLastActivity}
                changeStatusFn={this.props.changeStatus}
                changeJobPasswordFn={this.props.changeJobPasswordFn}
                downloadTranslationFn={this.props.downloadTranslationFn}
                team={this.state.team}/>
        ));
        if (!items.size) {
            items = <div className="no-results-found"><span>No Project Found</span></div>;
        }


        let spinner = '';
        if (this.state.more_projects && projects.size > 9) {
            spinner = <div className="row">
                        <div className="manage-spinner" style={{minHeigth: '90px'}}>
                            <div className="col m12 center-align">
                                <div className="preloader-wrapper active">
                                    <div className="spinner-layer spinner-blue-only">
                                        <div className="circle-clipper left">
                                            <div className="circle"></div>
                                        </div>
                                        <div className="gap-patch">
                                            <div className="circle"></div>
                                        </div>
                                        <div className="circle-clipper right">
                                            <div className="circle"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="col m12 center-align">
                            <span>Loading projects</span>
                        </div>
                    </div>;
        } else if (projects > 9) {
            spinner = <div className="row">
                <div className="manage-spinner" style={{minHeight: '90px'}}>
                    <div className="col m12 center-align">
                        <span ref={(spinner) => this.spinner = spinner}>No more projects</span>
                    </div>
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
            var styleSpinner = {
                position: 'absolute',
                width : '300px',
                height : '100px',
                top: $(window).height() / 2,
                left: $(window).width() / 2 - 150,
                zIndex: 2,
                fontWeight: 600
            };
            spinnerReloadProjects =<div style={spinnerContainer}>
                    <div style={styleSpinner}>
                        <div className="col m12 center-align">
                            <div className="preloader-wrapper active">
                                <div className="spinner-layer spinner-blue-only">
                                    <div className="circle-clipper left">
                                        <div className="circle"></div>
                                    </div>
                                    <div className="gap-patch">
                                        <div className="circle"></div>
                                    </div>
                                    <div className="circle-clipper right">
                                        <div className="circle"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="col m12 center-align">
                            <span>Updating projects</span>
                        </div>
                    </div>
                </div>;
        }



        return <div>
                    {/*<section className="add-project">*/}
                        {/*<a href="/" target="_blank" className="btn-floating btn-large waves-effect waves-light right create-new blue-matecat tooltipped" data-position="bottom" data-delay="50" data-tooltip="Add new project"/>*/}
                    {/*</section>*/}
                    <section className="project-list">
                        <div className="container">
                            <div className="row">
                                {spinnerReloadProjects}
                                <div className="col m12" ref={(container) => this.container = container}>
                                    {items}
                                </div>
                            </div>
                        </div>
                        {spinner}
                    </section>
                </div>;
    }
}

ProjectsContainer.propTypes = {
    projects: React.PropTypes.array,
};

ProjectsContainer.defaultProps = {
    projects: [],
};

export default ProjectsContainer ;
