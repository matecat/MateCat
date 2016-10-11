/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabConcordance extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {
        console.log("Mount SegmentFooterConcordance" + this.props.id_segment);

    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooterConcordance" + this.props.id_segment);

    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var html = '';
        if ( config.tms_enabled ) {
            html = <div className="cc-search">
                <div className="input search-source" contentEditable="true" ></div>
                <div className="input search-target" contentEditable="true" ></div>
            </div>;
        } else {
            html = <ul class="graysmall message">
                <li>Concordance is not available when the TM feature is disabled</li>
            </ul>;
        }
        return (

            <div key={"container_" + this.props.code} className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
                <div className="overflow">
                    {html}
                    <div className="results"></div>
                </div>
            </div>
        )
    }
}

export default SegmentFooterTabConcordance;