/**
 * React Component for the editarea.

 */
var React = require('react');
var ProjectsStore = require('../../stores/ProjectsStore');
var ManageConstants = require('../../constants/ManageConstants');
var Project = require('./ProjectContainer').default;
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
        var items = [];
        this.state.projects.map(function(project, i){
            var item = <Project
                key={i}
                project={project}/>;
            items.push(item);
        });
        return <div className="projects-container">{items}</div>;
    }
}

ProjectsContainer.propTypes = {
    projects: React.PropTypes.array,
};

ProjectsContainer.defaultProps = {
    projects: [],
};

export default ProjectsContainer ;
