/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabRevise extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {

    }

    componentWillUnmount() {

    }

    componentWillMount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {

        return  <div key={"container_" + this.props.code}
                     className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
            <div className="error-type">
                <h3>Select the type of issue</h3>
                <table>
                    <thead>
                    <tr>
                        <th>None</th>
                        <th>Enhancement</th>
                        <th>Error</th>
                        <th><a className="tooltip">?<span>Error that changes the meaning of the segment</span></a></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><input type="radio" name="t1" value="0" /></td>
                        <td><input type="radio" name="t1" value="1" /></td>
                        <td><input type="radio" name="t1" value="2" /></td>
                        <td className="align-left">Tag issues (mismatches, whitespaces)</td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="t2" value="0" /></td>
                        <td><input type="radio" name="t2" value="1" /></td>
                        <td><input type="radio" name="t2" value="2" /></td>
                        <td className="align-left">Translation errors (mistranslation, additions/omissions)</td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="t3" value="0" /></td>
                        <td><input type="radio" name="t3" value="1" /></td>
                        <td><input type="radio" name="t3" value="2" /></td>
                        <td className="align-left">Terminology and translation consistency</td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="t4" value="0" /></td>
                        <td><input type="radio" name="t4" value="1" /></td>
                        <td><input type="radio" name="t4" value="2" /></td>
                        <td className="align-left">Language quality (grammar, punctuation, spelling)</td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="t5" value="0" /></td>
                        <td><input type="radio" name="t5" value="1" /></td>
                        <td><input type="radio" name="t5" value="2" /></td>
                        <td className="align-left">Style (readability, consistent style and tone)</td>
                    </tr>

                    </tbody>
                </table>

            </div>

            <div className="track-changes">
                <h3>Revision (track changes)</h3>
                <p></p>
            </div>
        </div>
    }
}

export default SegmentFooterTabRevise;