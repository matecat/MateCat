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

    shouldComponentUpdate(nextProps, nextState) {
        return (nextState.projects !== this.state.projects)
    }

    render() {
        var items = this.state.projects.map((project, i) => (
            <Project
                key={i}
                project={project}/>
        ));
        return <section className="content">
                    <section className="add-project">
                        <a className="btn-floating btn-large waves-effect waves-light right create-new blue-matecat" href="/"><i className="material-icons">add</i></a>
                    </section>
                    <FilterProjects
                        filterFunction={this.props.filterFunction}
                    />
                    <section className="project-list">
                        <div className="container">
                            <div className="row">
                                <div className="col s12" ref={(container) => this.container = container}>
                                    {items}
                                </div>
                            </div>
                        </div>
                    </section>
                </section>;
    }
}

ProjectsContainer.propTypes = {
    projects: React.PropTypes.array,
};

ProjectsContainer.defaultProps = {
    projects: [],
};

export default ProjectsContainer ;
