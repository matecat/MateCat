
let JobAnalyzeHeader = require('./JobAnalyzeHeader').default;
let JobTableHeader = require('./JobTableHeader').default;
let ChunkAnalyze = require('./ChunkAnalyze').default;
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeStore = require('../../stores/AnalyzeStore');

class JobAnalyze extends React.Component {

    constructor(props) {
        super(props);
        this.showDetails = this.showDetails.bind(this);
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
                                         chunkInfo={self.props.jobInfo.chunks[i]}
                                         chunksSize={_.size(self.props.jobInfo.chunks)}   />
                }
            }).toList().toJS();
        }
        return '';

    }

    showDetails(idJob) {
        if (idJob == this.props.idJob) {
            let self = this;
            this.container.classList.add('show-details');
            this.scrollElement();
            setTimeout(function () {
                self.container.classList.remove('show-details');
            }, 1000)
        }
    }

    scrollElement() {
        var itemComponent = this.container;
        if (itemComponent) {
            ReactDOM.findDOMNode(itemComponent).scrollIntoView({block: 'end'});
        }
    }

    componentDidUpdate() {
    }

    componentDidMount() {
        AnalyzeStore.addListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails);
    }

    componentWillUnmount() {
        AnalyzeStore.removeListener(AnalyzeConstants.SHOW_DETAILS, this.showDetails);
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    render() {
        return <div className="job ui grid">
                    <div className="job-body sixteen wide column shadow-1">

                        <div className="ui grid chunks">
                            <div className="chunk-container sixteen wide column">
                                <div className="ui grid analysis"
                                     ref={(container) => this.container = container}>
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
