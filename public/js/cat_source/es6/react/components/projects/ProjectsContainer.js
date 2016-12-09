/**
 * React Component for the editarea.

 */
// var React = require('react');
var ProjectsStore = require('../../stores/ProjectsStore');
var ManageConstants = require('../../constants/ManageConstants');
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


    componentDidMount() {
        ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.RENDER_PROJECTS, this.renderProjects);
    }

    componentWillUpdate() {}

    componentDidUpdate() {}

    render() {
        var items = this.state.projects.map((project, i) => (
            <Project
                key={i}
                project={project}/>
        ));
        return <div className="content">
                <div className="row">
                    <div className="col s6">
                        <h1>Projects List</h1>
                    </div>
                    <div className="col s3">
                        <FilterProjects
                            filterFunction={this.props.filterFunction}
                        />
                    </div>
                </div>
            <div id="projects">
                    <div className="projects-container" ref={(container) => this.container = container}>
                        {items}
                    </div>
                </div>
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
