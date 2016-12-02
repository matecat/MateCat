/**
 * React Component for the editarea.

 */
var React = require('react');
var ProjectsStore = require('../../stores/ProjectsStore');
var ManageConstants = require('../../constants/ManageConstants');
class ProjectContainer extends React.Component {

    constructor(props) {
        super(props);

    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    componentWillUpdate() {}

    componentDidUpdate() {}

    render() {
        return <div className="project-container">{this.props.project.name}</div>;
    }
}

ProjectContainer.propTypes = {
};

ProjectContainer.defaultProps = {
};

export default ProjectContainer ;
