/**
 * React Component for the editarea.

 */
// var React = require('react');
var ProjectsStore = require('../../stores/ProjectsStore');
var Project = require('./ProjectContainer').default;
var FilterProjects = require("../FilterProjects").default;


class ProjectsContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            projects : [],
        };
        this.renderProjects = this.renderProjects.bind(this);
    }


    renderProjects(projects) {
        this.setState({
            projects: projects,
        });
    }

    onClickFn(event) {
        //Check if the click comes from a Project container
        if ($(event.nativeEvent.target).closest(".card-panel").size() === 0) {
            ManageActions.closeAllJobs();
        }
    }

    componentDidMount() {
        ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
        $('.tooltipped').tooltip({delay: 50});
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.projects !== this.state.projects)
    }

    render() {
        var items = this.state.projects.map((project, i) => (
            <Project
                key={project.get('id')}
                project={project}
                lastActivityFn={this.props.getLastActivity}
                changeStatusFn={this.props.changeStatus}
                changeJobPasswordFn={this.props.changeJobPasswordFn}
                downloadTranslationFn={this.props.downloadTranslationFn}/>
        ));
        if (!items.size) {
            items = <div className="no-results-found"><span>No Project Found</span></div>;
        }

        return <div onClick={this.onClickFn.bind(this)}>
                    {/*<section className="add-project">*/}
                        {/*<a href="/" target="_blank" className="btn-floating btn-large waves-effect waves-light right create-new blue-matecat tooltipped" data-position="bottom" data-delay="50" data-tooltip="Add new project"/>*/}
                    {/*</section>*/}
                    <section className="project-list">
                        <div className="container">
                            <div className="row">
                                <div className="col m12" ref={(container) => this.container = container}>
                                    {items}
                                </div>
                            </div>
                        </div>
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
