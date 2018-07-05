import ProductionSummary from "./ProductionSummary";
import QualitySummaryTable from "./QualitySummaryTable";

class JobSummary extends React.Component {

    render () {

        return <div className="qr-production-quality">
                <ProductionSummary/>
                <QualitySummaryTable/>
            </div>
    }
}

export default JobSummary ;