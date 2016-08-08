/**
 * React Component for the editarea.

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var Segment = require('./Segment').default;
var SegmentConstants = require('../../constants/SegmentConstants');
class SegmentsContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            segments : [],
            splitGroup: [],
            fid: ""
        };
        this.renderSegments = this.renderSegments.bind(this);
        this.updateSegments = this.updateSegments.bind(this);
        this.splitSegments = this.splitSegments.bind(this);
    }

    splitSegments(segments, splitGroup, fid) {
        if (fid == this.props.fid) {
            this.setState({
                segments: segments,
                splitGroup: splitGroup
            });
        }
    }
    updateSegments(segments, fid) {
        if (fid == this.props.fid) {
            this.setState({
                segments: segments,
            });
        }
    }

    renderSegments(segments, fid) {
        if (fid !== this.props.fid) return;
        var splitGroup =  [];
        this.setState({
            segments: segments,
            splitGroup: splitGroup,
            timeToEdit: config.time_to_edit_enabled,
        });
    }


    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.addListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.addListener(SegmentConstants.UPDATE_SEGMENTS, this.updateSegments);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.removeListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.removeListener(SegmentConstants.UPDATE_SEGMENTS, this.updateSegments);
    }

    componentWillMount() {

    }

    render() {
        var items = [];
        var self = this;
        var isReviewImproved = !!(this.props.isReviewImproved);
        this.state.segments.forEach(function (segment) {
            var item = <Segment
                key={segment.sid}
                segment={segment}
                timeToEdit={self.state.timeToEdit}
                fid={self.props.fid}
                isReviewImproved={isReviewImproved}
                enableTagProjection={self.props.enableTagProjection}
                decodeTextFn={self.props.decodeTextFn}
                tagModesEnabled={self.props.tagModesEnabled}
                speech2textEnabledFn={self.props.speech2textEnabledFn}
            />;
            items.push(item);
        });
        return <div>{items}</div>;
    }
}

SegmentsContainer.propTypes = {
    segments: React.PropTypes.array,
    splitGroup: React.PropTypes.array,
    timeToEdit: React.PropTypes.string
};

SegmentsContainer.defaultProps = {
    segments: [],
    splitGroup: [],
    timeToEdit: ""
};

export default SegmentsContainer ;

