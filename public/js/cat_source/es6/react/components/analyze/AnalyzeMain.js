
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeHeader = require('./AnalyzeHeader').default;
let ProjectAnalyze = require('./ProjectAnalyze').default;

class AnalyzeMain extends React.Component {

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
        return <div className="ui container">
                <div className="project ui grid shadow-1">
                    <div className="sixteen wide column">
                        <div className="analyze-header">
                            <AnalyzeHeader/>
                        </div>

                        <div className="project-body ui grid">
                            <ProjectAnalyze/>
                        </div>
                    </div>
                </div>
            </div>;


    }
}

export default AnalyzeMain;
