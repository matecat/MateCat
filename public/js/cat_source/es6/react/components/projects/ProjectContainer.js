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
        console.log("Mounted Segment : " + this.props.project.get('id'));
    }

    componentWillUnmount() {
    }

    componentWillUpdate() {}

    componentDidUpdate() {
        console.log("Updated Segment : " + this.props.project.get('id'));
    }

    shouldComponentUpdate(nextProps){
         return nextProps.project !== this.props.project;
    }
    render() {
        console.log("Render Segment : " + this.props.project.get('id'));
        return <div className="project-container">{this.props.project.get('name')}</div>;
    }
}

ProjectContainer.propTypes = {
};

ProjectContainer.defaultProps = {
};

export default ProjectContainer ;
