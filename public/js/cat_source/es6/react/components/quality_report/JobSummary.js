import ProductionSummary from "./ProductionSummary";
import QualitySummaryTable from "./QualitySummaryTable";

class JobSummary extends React.Component {

    render () {

        return <div className="qr-production-quality">
                <ProductionSummary jobInfo={this.props.jobInfo}/>
                <QualitySummaryTable jobInfo={this.props.jobInfo}/>
            </div>
    }
}

export default JobSummary ;