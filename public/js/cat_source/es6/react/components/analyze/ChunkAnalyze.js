
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let ChunkAnalyzeHeader = require('./ChunkAnalyzeHeader').default;
let ChunkAnalyzeFile = require('./ChunkAnalyzeFile').default;

class ChunkAnalyze extends React.Component {

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

        return <div className="ui grid chunk-analyze-container">
            <ChunkAnalyzeHeader/>
            <ChunkAnalyzeFile/>
            <ChunkAnalyzeFile/>

        </div>;


    }
}

export default ChunkAnalyze;
