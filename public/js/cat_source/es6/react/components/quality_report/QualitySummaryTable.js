
class QualitySummaryTable extends React.Component {

    render () {

        return <div className="qr-quality">

            <table className="ui celled table shadow-1">
                <thead>
                <tr>
                    <th className="four wide qr-title qr-issue">Issues</th>
                    <th className="one wide center aligned qr-title qr-severity critical">
                        <div className="qr-info">Critical</div>
                        <div className="qr-label">Weight: <b>3</b></div>
                    </th>
                    <th className="one wide center aligned qr-title qr-severity major">
                        <div className="qr-info">Major</div>
                        <div className="qr-label">Weight: <b>1</b></div>
                    </th>
                    <th className="one wide center aligned qr-title qr-severity enhacement">
                        <div className="qr-info">Enhacement</div>
                        <div className="qr-label">Weight: <b>0.03</b></div>
                    </th>
                    <th className="wide center aligned qr-title">Total weight</th>
                    <th className="wide center aligned qr-title">*Tolerated Issues</th>
                    <th className="two wide center aligned qr-title qr-total-score qr-pass">
                        <div className="qr-label">Total Score</div>
                        <div className="qr-info">Good</div>
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><b>Tag issues</b></td>
                    <td className="center aligned"></td>
                    <td className="center aligned"></td>
                    <td className="center aligned"></td>
                    <td className="right aligned">0</td>
                    <td className="right aligned">1.8</td>
                    <td className="positive center aligned">Excellent</td>
                </tr>
                <tr>
                    <td><b>Translation errors</b></td>
                    <td className="center aligned"> </td>
                    <td className="center aligned">1</td>
                    <td className="center aligned"> </td>
                    <td className="right aligned">1</td>
                    <td className="right aligned">1.8</td>
                    <td className="warning center aligned">Poor</td>
                </tr>
                <tr>
                    <td><b>Terminology and translation consistency</b></td>
                    <td className="center aligned">1</td>
                    <td className="center aligned"></td>
                    <td className="center aligned"></td>
                    <td className="right aligned">3</td>
                    <td className="right aligned">0.9</td>
                    <td className="negative center aligned">Fail</td>
                </tr>
                <tr>
                    <td><b>Language quality</b></td>
                    <td className="center aligned"></td>
                    <td className="center aligned"></td>
                    <td className="center aligned"></td>
                    <td className="right aligned">0</td>
                    <td className="right aligned">2.6</td>
                    <td className="positive center aligned">Eccelent</td>
                </tr>
                <tr>
                    <td><b>Style</b></td>
                    <td className="center aligned"></td>
                    <td className="center aligned">1</td>
                    <td className="center aligned">1</td>
                    <td className="right aligned">1.03</td>
                    <td className="right aligned">6.1</td>
                    <td className="positive center aligned">Very good</td>
                </tr>
                </tbody>
            </table>

        </div>
    }
}

export default QualitySummaryTable ;