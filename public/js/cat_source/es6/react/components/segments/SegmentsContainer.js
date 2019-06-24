/**
 * React Component for the editarea.

 */
var React = require('react');
let PropTypes = require('prop-types');
var SegmentStore = require('../../stores/SegmentStore');
let Segment = require('./Segment').default;
var SegmentConstants = require('../../constants/SegmentConstants');
import VirtualList from 'react-tiny-virtual-list';
const Immutable = require('immutable');


class SegmentsContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            segments : Immutable.fromJS([]),
            splitGroup: [],
            timeToEdit: config.time_to_edit_enabled,
            window: {
                width: 0,
                height: 0,
            }
        };
        this.renderSegments = this.renderSegments.bind(this);
        this.updateAllSegments = this.updateAllSegments.bind(this);
        this.splitSegments = this.splitSegments.bind(this);
        this.updateWindowDimensions = this.updateWindowDimensions.bind(this);
    }

    splitSegments(segments, splitGroup) {
        this.setState({
            segments: segments,
            splitGroup: splitGroup
        });
    }

    updateAllSegments() {
        this.forceUpdate();
    }

    renderSegments(segments) {
        let splitGroup =  [];
        this.setState({
            segments: segments,
            splitGroup: splitGroup,
            timeToEdit: config.time_to_edit_enabled,
        });

    }

    setLastSelectedSegment(sid) {
        this.lastSelectedSegment = {
            sid: sid
        };
    }

    setBulkSelection(sid, fid) {
        if ( _.isUndefined(this.lastSelectedSegment) ) {
            this.lastSelectedSegment = {
                sid: sid
            };
        }
        let from = Math.min(sid, this.lastSelectedSegment.sid);
        let to = Math.max(sid, this.lastSelectedSegment.sid);
        this.lastSelectedSegment = {
            sid: sid
        };
        SegmentActions.setBulkSelectionInterval(from, to, fid);
    }

    getSegments() {
        let items = [];
        let self = this;
        let isReviewExtended = !!(this.props.isReviewExtended);
        this.state.segments.forEach(function (segImmutable) {
            let segment = segImmutable.toJS();
            let item = <Segment
                key={segment.sid}
                segment={segment}
                timeToEdit={self.state.timeToEdit}
                fid={self.props.fid}
                isReviewExtended={isReviewExtended}
                enableTagProjection={self.props.enableTagProjection}
                decodeTextFn={self.props.decodeTextFn}
                tagLockEnabled={self.state.tagLockEnabled}
                tagModesEnabled={self.props.tagModesEnabled}
                speech2textEnabledFn={self.props.speech2textEnabledFn}
                reviewType={self.props.reviewType}
                setLastSelectedSegment={self.setLastSelectedSegment.bind(self)}
                setBulkSelection={self.setBulkSelection.bind(self)}
            />;
            items.push(item);
        });
        return items;
    }

    componentDidMount() {
        this.updateWindowDimensions();
        window.addEventListener('resize', this.updateWindowDimensions);
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.addListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.addListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
    }

    componentWillUnmount() {
        window.removeEventListener('resize', this.updateWindowDimensions);
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.removeListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.removeListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (!nextState.segments.equals(this.state.segments) ||
        nextState.splitGroup !== this.state.splitGroup ||
        nextState.tagLockEnabled !== this.state.tagLockEnabled ||
        nextState.window !== this.state.window)
    }

    updateWindowDimensions()  {
        let data = {};
        data.width = window.innerWidth;
        data.height = window.innerHeight;

        this.setState({
            window: data
        })
    };

    componentWillUpdate() {
        saveSelection();
    }
    componentDidUpdate() {
        restoreSelection();
    }

    render() {
        let items = this.getSegments();
        return <VirtualList
            width={this.state.window.width}
            height={this.state.window.height-120}
            style={{overflowX: 'hidden'}}
            estimatedItemSize={80}
            overscanCount={2}
            itemCount={items.length}
            itemSize={87}
            scrollToAlignment="center"
            onScroll={(number, event) => {
                let scrollTop = $(event.target).scrollTop();
                let scrollBottom = $(event.target).prop('scrollHeight') - (scrollTop + $(event.target).height());
                if ( scrollBottom < 900 ) {
                    UI.getMoreSegments('after');
                } else if( scrollTop < 900 ) {
                    UI.getMoreSegments('before');
                }
            } }
            renderItem={({index, style}) =>
                <div key={index} style={style}>
                    {items[index]}
                </div>
            }
        >
        </VirtualList>


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

