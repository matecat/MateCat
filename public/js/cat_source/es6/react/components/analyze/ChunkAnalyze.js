
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
        var array = [];
        this.props.files.forEach(function (file, i) {
            array.push(<ChunkAnalyzeFile key={i}
                                     file={file}
                                     fileInfo={self.props.chunkInfo.files[i]}
                                     />);
        });
        return array
    }

    showFiles(e) {
        e.preventDefault();
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
                                showFiles={this.showFiles.bind(this)}
                                chunksSize={this.props.chunksSize}/>

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
