
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let JobAnalyzeHeader = require('./JobAnalyzeHeader').default;
let JobTableHeader = require('./JobTableHeader').default;
let ChunkAnalyze = require('./ChunkAnalyze').default;

class JobAnalyze extends React.Component {

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
        return <div className="job ui grid">
                    <div className="job-body sixteen wide column">

                        <div className="ui grid chunks">
                            <div className="chunk-container sixteen wide column">
                                <div className="ui grid analysis">
                                    <JobAnalyzeHeader/>
                                    <JobTableHeader/>
                                    <ChunkAnalyze/>
                                    <ChunkAnalyze/>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>;


    }
}

export default JobAnalyze;
