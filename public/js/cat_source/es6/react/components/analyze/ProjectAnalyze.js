
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let JobAnalyze = require('./JobAnalyze').default;

class ProjectAnalyze extends React.Component {

    constructor(props) {
        super(props);
    }



    componentDidUpdate() {
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    render() {
        return <div className="jobs sixteen wide column">
            <JobAnalyze/>
            <JobAnalyze/>
        </div>;


    }
}

export default ProjectAnalyze;
