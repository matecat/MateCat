
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabIssues extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {}

    componentWillUnmount() {}

    componentWillMount() {}

    allowHTML(string) {
        return { __html: string };
    }

    render() {

        return  <div key={"container_" + this.props.code}
                     className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
            Issues
        </div>
    }
}

export default SegmentFooterTabIssues;