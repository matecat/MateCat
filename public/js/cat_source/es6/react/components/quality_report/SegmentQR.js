
class SegmentQR extends React.Component {

    render () {

        return <div className="qr-single-segment">

            <table className="ui celled table">

                <thead>
                <tr>
                    <th className="three wide">
                        <div className="segment id">{this.props.segment.get("sid")}</div>
                    </th>
                    <th className="wide">
                        <div className="segment-production">
                            <div className="word-speed">Words speed: <b>7"</b></div>
                            <div className="time-edit">Time to edit: <b>53"</b></div>
                            <div className="pee">PEE: <b>30%</b></div>
                        </div>
                    </th>
                    <th className="two wide">
                        <div className="qr-label">Segment status</div>
                        <div className="qr-info status-translated">Translated</div>
                    </th>
                </tr>
                </thead>

                <tbody>
                <tr>
                    <td>Source</td>
                    <td><b>Tag issues</b></td>
                    <td>Words: <b>10</b></td>
                </tr>
                <tr>
                    <td>Suggestion</td>
                    <td>Translation errors</td>
                    <td>Public TM <span>97%</span></td>

                </tr>
                <tr>
                    <td>Translate</td>
                    <td>Terminology and translation consistency</td>
                    <td>ICE Match (Modified) </td>
                </tr>
                <tr>
                    <td>Revise</td>
                    <td>Terminology and translation consistency</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Automated QA</td>
                    <td>
                        <div className="qr-issues-list">
                            <div className="qr-issue automated">
                                <i className="icon-3dglasses icon" />
                                <div className="qr-error">Tag mismatch <b>(2)</b></div>
                            </div>
                        </div>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td>Human QA</td>
                    <td>
                        <div className="qr-issues-list">
                            <div className="qr-issue human">
                                <div className="qr-error"><b>Language quality</b></div>
                                <div className="sub-type-error">Subtype </div>
                                <div className="severity">Critical</div>
                            </div>
                        </div>
                    </td>
                    <td></td>
                </tr>
                </tbody>

            </table>

        </div>
    }
}

export default SegmentQR ;