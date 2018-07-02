import Header from "./Header";
import JobSummary from "./JobSummary";
import SegmentsDetails from "./SegmentsDetails";
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