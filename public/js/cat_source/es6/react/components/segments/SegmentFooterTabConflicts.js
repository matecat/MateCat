/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabConflicts extends React.Component {

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

        return (

            <div key={"container_" + this.props.code} className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                 id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                </div>
            </div>
        )
    }
}

export default SegmentFooterTabConflicts;