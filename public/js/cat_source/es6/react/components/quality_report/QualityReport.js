import Header from "./Header";
import JobSummary from "./JobSummary";
import SegmentsDetails from "./SegmentsDetails";
import ReactDom from "react-dom";

class QualityReport extends React.Component {


    render () {

        return <div>
            <Header/>
            <JobSummary/>
            <SegmentsDetails/>
            </div>
    }
}

export default QualityReport ;

ReactDom.render(React.createElement(QualityReport), document.getElementById('qr-root'));