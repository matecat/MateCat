/**
 * React Component for the editarea.

 */
var SegmentStore = require('../../stores/SegmentStore');
var Segment = require('./Segment').default;
var SegmentConstants = require('../../constants/SegmentConstants');
class SegmentsContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            segments : [],
            splitAr: [],
            splitGroup: [],
            fid: ""
        };
        this.renderSegments = this.renderSegments.bind(this);
        this.updateSegments = this.updateSegments.bind(this);
    }

    updateSegments(segments, splitAr, splitGroup, fid) {
        if (fid !== this.props.fid) return;
        this.setState({
            segments: segments,
            splitAr: splitAr,
            splitGroup: splitGroup,
        });
    }

    renderSegments(segments, fid) {
        if (fid !== this.props.fid) return;
        var splitAr = [];
        var splitGroup =  [];
        this.setState({
            segments: segments,
            splitAr: splitAr,
            splitGroup: splitGroup,
            timeToEdit: config.time_to_edit_enabled,
        });
    }


    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.addListener(SegmentConstants.SPLIT_SEGMENT, this.updateSegments);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.removeListener(SegmentConstants.SPLIT_SEGMENT, this.updateSegments);
    }

    componentWillMount() {

    }

    render() {
        var items = [];
        var self = this;
        var isReviewImproved = !!(this.props.isReviewImproved);
        this.state.segments.forEach(function (segment) {
            var splitGroup = segment.split_group || self.state.splitGroup || '';
            var item = <Segment
                key={segment.sid}
                segment={segment}
                splitAr={self.state.splitAr}
                splitGroup={splitGroup}
                timeToEdit={self.props.timeToEdit}
                fid={self.props.fid}
                isReviewImproved={isReviewImproved}
            />;
            items.push(item);
        });
        return <div>{items}</div>;
    }
}

SegmentsContainer.propTypes = {
    segments: React.PropTypes.array,
    splitAr: React.PropTypes.array,
    splitGroup: React.PropTypes.array,
    timeToEdit: React.PropTypes.string
};

SegmentsContainer.defaultProps = {
    segments: [],
    splitAr: [],
    splitGroup: [],
    timeToEdit: ""
};

export default SegmentsContainer ;

