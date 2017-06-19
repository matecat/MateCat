
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class ChunkAnalyzeFile extends React.Component {

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
        var file = this.props.file.toJS();
        return <div className="chunk-detail sixteen wide column shadow-1 pad-right-10">
            <div className="left-box">
                <i className="icon-make-group icon"></i>
                <div className="file-title-details">
                    {this.props.fileInfo.filename}
                    {/*(<span className="f-details-number">2</span>)*/}
                </div>
            </div>
            <div className="single-analysis">
                <div className="single total">{this.props.fileInfo.file_raw_word_count}</div>
                <div className="single payable-words">{file.TOTAL_PAYABLE[1]}</div>
                <div className="single new">{file.NEW[1]}</div>
                <div className="single repetition">{file.REPETITIONS[1]}</div>
                <div className="single internal-matches">{file.INTERNAL_MATCHES[1]}</div>
                <div className="single p-50-74">{file.TM_50_74[1]}</div>
                <div className="single p-75-84">{file.TM_75_84[1]}</div>
                <div className="single p-84-94">{file.TM_85_94[1]}</div>
                <div className="single p-95-99">{file.TM_95_99[1]}</div>
                <div className="single tm-100">{file.TM_100[1]}</div>
                <div className="single tm-public">{file.TM_100_PUBLIC[1]}</div>
                <div className="single tm-context">{file.ICE[1]}</div>
                <div className="single machine-translation">{file.MT[1]}</div>
            </div>
        </div>;


    }
}

export default ChunkAnalyzeFile;
