/**
 * React Component for the editarea.

 */
var React = require('react');
let PropTypes = require('prop-types');
var SegmentStore = require('../../stores/SegmentStore');
var Segment = require('./Segment').default;
var SegmentConstants = require('../../constants/SegmentConstants');
class SegmentsContainer extends React.Component {

    constructor(props) {
        super(props);
        console.time("Time: SegmentsContainer Mount/Update"+this.props.fid);
        this.state = {
            segments : [],
            splitGroup: [],
            timeToEdit: config.time_to_edit_enabled
        };
        this.renderSegments = this.renderSegments.bind(this);
        // this.updateSegments = this.updateSegments.bind(this);
        this.updateAllSegments = this.updateAllSegments.bind(this);
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
    // updateSegments(segments, fid) {
    //     if (fid == this.props.fid) {
    //         this.setState({
    //             segments: segments,
    //         });
    //     }
    // }

    updateAllSegments() {
        this.forceUpdate()
    }

    renderSegments(segments, fid) {
        if (fid !== this.props.fid) return;
        let splitGroup =  [];
        this.setState({
            segments: segments,
            splitGroup: splitGroup,
            timeToEdit: config.time_to_edit_enabled,
        });
    }


    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.addListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        // SegmentStore.addListener(SegmentConstants.UPDATE_SEGMENTS, this.updateSegments);
        SegmentStore.addListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
        console.timeEnd("Time: SegmentsContainer Mount/Update"+this.props.fid);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.removeListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        // SegmentStore.removeListener(SegmentConstants.UPDATE_SEGMENTS, this.updateSegments);
        SegmentStore.removeListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (!nextState.segments.equals(this.state.segments) ||
        nextState.splitGroup !== this.state.splitGroup)
    }

    componentWillUpdate() {
        saveSelection();
    }
    componentDidUpdate() {
        restoreSelection();
        console.timeEnd("Time: SegmentsContainer Mount/Update"+this.props.fid);
    }

    render() {
        let items = [];
        let self = this;
        let isReviewImproved = !!(this.props.isReviewImproved);
        this.state.segments.forEach(function (segImmutable) {
            let segment = segImmutable.toJS();
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
                reviewType={self.props.reviewType}
            />;
            items.push(item);
        });
        return <div>{items}</div>;
    }
}

SegmentsContainer.propTypes = {
    segments: PropTypes.array,
    splitGroup: PropTypes.array,
    timeToEdit: PropTypes.string
};

SegmentsContainer.defaultProps = {
    segments: [],
    splitGroup: [],
    timeToEdit: ""
};

export default SegmentsContainer ;

