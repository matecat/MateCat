/**
 * React Component .

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentActions = require('../../actions/SegmentActions');


class SegmentHeader extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            autopropagated: this.props.autopropagated,
            percentage: '',
            classname: '',
            createdBy: '',
            visible: false
        };
        this.changePercentuage = this.changePercentuage.bind(this);
        this.hideHeader = this.hideHeader.bind(this);

    }

    changePercentuage(sid, perc, className, createdBy) {
        if (this.props.sid == sid) {
            this.setState({
                percentage: perc,
                classname: className,
                createdBy: createdBy,
                visible: true,
                autopropagated: false
            });
        }
    }

    hideHeader(sid) {
        if (this.props.sid == sid) {
            this.setState({
                autopropagated: false,
                visible: false
            });
        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_HEADER, this.changePercentuage);
        SegmentStore.addListener(SegmentConstants.HIDE_SEGMENT_HEADER, this.hideHeader);

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_HEADER, this.changePercentuage);
        SegmentStore.removeListener(SegmentConstants.HIDE_SEGMENT_HEADER, this.hideHeader);
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.autopropagated) {
            this.setState({
                autopropagated: true
            });
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var autopropagated = '';
        var percentageHtml = '';
        if (this.state.autopropagated) {
            autopropagated = <span className="repetition">Autopropagated</span>;
        } else if (this.state.visible && this.state.percentage != '') {
            percentageHtml = <h2 title={"Created by " + this.state.createdBy}
                                 className={" visible percentuage " + this.state.classname}>{this.state.percentage}</h2>;

        }

        return (
            <div className="header toggle" id={"segment-" + this.props.sid + "-header"}>
                {autopropagated}
                {percentageHtml}
            </div>
        )
    }
}

export default SegmentHeader;


