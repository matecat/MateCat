import ProductionSummary from "./ProductionSummary";
import QualitySummary from "./QualitySummary";

class JobSummary extends React.Component {

    render () {

        return <div>
            <ProductionSummary/>
            <QualitySummary/>
        </div>
    }
}

export default JobSummary ;