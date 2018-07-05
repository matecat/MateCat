
class QualitySummaryTable extends React.Component {

    render () {

        return <div className="qr-quality">

            <table className="ui celled table">
                <thead>
                <tr>
                    <th className="four wide qr-issue">Issues</th>
                    <th className="one wide center aligned qr-severity critical">
                        <div className="qr-info">Critical</div>
                        <div className="qr-label">Weight: <b>3</b></div>
                    </th>
                    <th className="one wide center aligned qr-severity major">
                        <div className="qr-info">Major</div>
                        <div className="qr-label">Weight: <b>1</b></div>
                    </th>
                    <th className="one wide center aligned qr-severity enhacement">
                        <div className="qr-info">Enhacement</div>
                        <div className="qr-label">Weight: <b>0.03</b></div>
                    </th>
                    <th className="wide">Total weight</th>
                    <th className="wide">*Tolerated Issues</th>
                    <th className="two wide center aligned">
                        <div className="qr-label">Total Score</div>
                        <div className="qr-info">Good</div>
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><b>Tag issues</b></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>0</td>
                    <td>1.8</td>
                    <td className="positive center aligned">Excellent</td>
                </tr>
                <tr>
                    <td>Translation errors</td>
                    <td> </td>
                    <td>1</td>
                    <td> </td>
                    <td>1</td>
                    <td>1.8</td>
                    <td className="warning center aligned">Poor</td>
                </tr>
                <tr>
                    <td>Terminology and translation consistency</td>
                    <td>1</td>
                    <td></td>
                    <td></td>
                    <td>3</td>
                    <td>0.9</td>
                    <td className="negative center aligned">Fail</td>
                </tr>
                <tr>
                    <td>Language quality</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>0</td>
                    <td>2.6</td>
                    <td className="positive center aligned">Eccelent</td>
                </tr>
                <tr>
                    <td>Style</td>
                    <td></td>
                    <td>1</td>
                    <td>1</td>
                    <td>1.03</td>
                    <td>6.1</td>
                    <td className="positive center aligned">Very good</td>
                </tr>
                </tbody>
            </table>

        </div>
    }
}

export default QualitySummaryTable ;