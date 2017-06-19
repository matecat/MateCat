
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let JobAnalyzeHeader = require('./JobAnalyzeHeader').default;
let JobTableHeader = require('./JobTableHeader').default;
let ChunkAnalyze = require('./ChunkAnalyze').default;

class JobAnalyze extends React.Component {

    constructor(props) {
        super(props);
    }

    getChunks() {
        let self = this;
        let index = this.props.chunks.size + 1;
        return this.props.chunks.map(function (chunk, i) {
            index--;
            return <ChunkAnalyze key={i}
                                 chunk={chunk}
                                 total={self.props.total.get(i)}
                                 index={index}
                                 chunkInfo={self.props.jobInfo.chunks[i]}/>
        }).reverse();

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
                                    <JobAnalyzeHeader job={this.props.job}
                                                      totals={this.props.total}
                                                      project={this.props.project}
                                                      jobInfo={this.props.jobInfo}/>
                                    <JobTableHeader rates={this.props.jobInfo.rates}/>
                                    {this.getChunks()}
                                </div>
                            </div>

                        </div>

                    </div>
                </div>;


    }
}

export default JobAnalyze;
