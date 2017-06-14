
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class JobAnalyzeHeader extends React.Component {

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
        return <div className="head-chunk sixteen wide column shadow-1 pad-right-10">
                    <div className="source-target">
                        <div className="source-box">English</div>
                        <div className="in-to">
                            <i className="icon-chevron-right icon"></i>
                        </div>
                        <div className="target-box">Haitian Creole French</div>
                    </div>
                    <div className="job-not-payable">
                        <span id="raw-words">7'845,783</span>
                    </div>
                    <div className="job-payable">
                        <a href="#">
                            <span id="words">5'485,384</span> words
                        </a>
                    </div>
                    <div className="merge ui button"><i className="icon-compress icon"></i> Merge</div>
                    {/*<!--<div className="split ui button"><i className="icon-expand icon"></i> Split</div>-->*/}
                </div>;

    }
}

export default JobAnalyzeHeader;
