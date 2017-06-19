
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let ChunkAnalyzeHeader = require('./ChunkAnalyzeHeader').default;
let ChunkAnalyzeFile = require('./ChunkAnalyzeFile').default;

class ChunkAnalyze extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            showFiles: false
        }
    }

    getFiles() {
        let self = this;
        return this.props.chunk.map(function (file, i) {
            return <ChunkAnalyzeFile file={file} fileInfo={self.props.chunkInfo.files[i]}/>
        });
    }

    showFiles() {
        this.setState({
            showFiles: !this.state.showFiles
        });
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
                                jobInfo={this.props.chunkInfo}
                                showFiles={this.showFiles.bind(this)}/>
            {this.state.showFiles ? (
                this.getFiles()
            ) : ('')}

        </div>;
    }
}

export default ChunkAnalyze;
