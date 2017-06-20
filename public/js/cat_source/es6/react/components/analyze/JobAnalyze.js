
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
        if (this.props.chunks) {
            let index = 0;
            return this.props.chunks.map(function (files, i) {
                index++;
                let job = self.props.project.get('jobs').find(function (jobElem) {
                    return jobElem.get('password') === i
                });
                if (!_.isUndefined(self.props.jobInfo.chunks[job.get('password')])) {
                    return <ChunkAnalyze key={i}
                                         files={files}
                                         job={job}
                                         project={self.props.project}
                                         total={self.props.total.get(i)}
                                         index={index}
                                         chunkInfo={self.props.jobInfo.chunks[i]}/>
                }
            }).toList().toJS();
        }
        return '';

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
                                    <JobAnalyzeHeader totals={this.props.total}
                                                      project={this.props.project}
                                                      jobInfo={this.props.jobInfo}
                                                      status={this.props.status}/>
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
