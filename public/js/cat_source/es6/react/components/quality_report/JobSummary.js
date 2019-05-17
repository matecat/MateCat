import ProductionSummary from "./ProductionSummary";
import QualitySummaryTable from "./QualitySummaryTable";
import QualitySummaryTableOld from "./QualitySummaryTableOldRevise";

class JobSummary extends React.Component {

    render () {

        return <div className="qr-production-quality">
            {this.props.jobInfo? (
                <ProductionSummary jobInfo={this.props.jobInfo}
                                   qualitySummary={this.props.qualitySummary}
                />
            ) : null}

            {this.props.jobInfo? (
            (config.project_type === 'new') ? (
                <QualitySummaryTable jobInfo={this.props.jobInfo}
                                     qualitySummary={this.props.qualitySummary}/>
                ) : (
                <QualitySummaryTableOld jobInfo={this.props.jobInfo} qualitySummary={this.props.qualitySummary}/>
            )

            ) : null}

        </div>
    }
}

export default JobSummary ;