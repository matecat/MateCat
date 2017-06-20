
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let ChunkAnalyzeHeader = require('./ChunkAnalyzeHeader').default;
let ChunkAnalyzeFile = require('./ChunkAnalyzeFile').default;
let CSSTransitionGroup = React.addons.CSSTransitionGroup;

class ChunkAnalyze extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            showFiles: false
        }
    }

    getFiles() {
        let self = this;
        return this.props.files.map(function (file, i) {
            return <ChunkAnalyzeFile key={i} file={file} fileInfo={self.props.chunkInfo.files[i]}/>
        });
    }

    showFiles(e) {
        e.preventDefault();
        this.setState({
            showFiles: !this.state.showFiles
        });
    }

    getTranslateUrl() {
        let chunk_id = this.props.job.get('id') + '-' + this.props.index ;
        return '/translate/'+this.props.project.get('project_slug')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    openOutsourceModal() {
        ModalsActions.openOutsourceModal(this.props.project.toJS(), this.props.job.toJS(), this.getTranslateUrl(), false, false, false);
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

        return <div className="ui grid chunk-analyze-container">
            <ChunkAnalyzeHeader index ={this.props.index}
                                total={this.props.total}
                                openOutsourceModalFn={this.openOutsourceModal.bind(this)}
                                jobInfo={this.props.chunkInfo}
                                showFiles={this.showFiles.bind(this)}/>

                <CSSTransitionGroup component="div" className="ui grid"
                    transitionName="transition"
                    transitionEnterTimeout={500}
                    transitionLeaveTimeout={500}
                >
                    {this.state.showFiles ? (this.getFiles()): (null)}
                </CSSTransitionGroup>



        </div>;
    }
}

export default ChunkAnalyze;
