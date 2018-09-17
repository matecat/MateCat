import ProductionSummary from "./ProductionSummary";
import QualitySummaryTable from "./QualitySummaryTable";

class JobSummary extends React.Component {

    render () {

        return <div className="qr-production-quality">
            {this.props.jobInfo? (
                <ProductionSummary jobInfo={this.props.jobInfo}/>
            ) : null}

            {this.props.jobInfo? (
                <QualitySummaryTable jobInfo={this.props.jobInfo}/>
            ) : null}

        </div>
    }
}

export default JobSummary ;