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
            scrollTo: null,
            window: {
                width: 0,
                height: 0,
            },
            sideOpen: false
        };
        this.renderSegments = this.renderSegments.bind(this);
        this.updateAllSegments = this.updateAllSegments.bind(this);
        this.splitSegments = this.splitSegments.bind(this);
        this.updateWindowDimensions = this.updateWindowDimensions.bind(this);
        this.scrollToSegment = this.scrollToSegment.bind(this);
        this.openSide = this.openSide.bind(this);
        this.closeSide = this.closeSide.bind(this);
    }

    splitSegments(segments, splitGroup) {
        this.setState({
            segments: segments,
            splitGroup: splitGroup
        });
    }

    openSide() {
        this.setState({sideOpen: true});
    }

    closeSide() {
        this.setState({sideOpen: false});
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

    scrollToSegment(sid) {
        this.setState({scrollTo: sid});
    }

    getIndexToScroll() {
        if ( !this.state.scrollTo ) return null;
        return this.state.segments.findIndex( (segment, index) => {
            if (this.state.scrollTo.toString().indexOf("-") === -1) {
                return parseInt(segment.get('sid')) === parseInt(this.state.scrollTo);
            } else {
                return segment.get('sid') === this.state.scrollTo;
            }
        });
    }

    getSegmentByIndex(index) {
        return this.state.segments.get(index);
    }

    getSegments() {
        let items = [];
        let self = this;
        let isReviewExtended = !!(this.props.isReviewExtended);
        let currentFileId = 0;
        this.state.segments.forEach(function (segImmutable) {
            let segment = segImmutable.toJS();
            let item = <Segment
                key={segment.sid}
                segment={segment}
                timeToEdit={self.state.timeToEdit}
                fid={self.props.fid}
                isReview={self.props.isReview}
                isReviewExtended={isReviewExtended}
                reviewType={self.props.reviewType}
                enableTagProjection={self.props.enableTagProjection}
                decodeTextFn={self.props.decodeTextFn}
                tagLockEnabled={self.state.tagLockEnabled}
                tagModesEnabled={self.props.tagModesEnabled}
                speech2textEnabledFn={self.props.speech2textEnabledFn}
                setLastSelectedSegment={self.setLastSelectedSegment.bind(self)}
                setBulkSelection={self.setBulkSelection.bind(self)}
                sideOpen={self.state.sideOpen}
            />;
            if ( segment.id_file !== currentFileId ) {
                item = <React.Fragment>
                    <ul className="projectbar" data-job={"job-"+ segment.id_file}>
                        <li className="filename">
                            <h2 title={segment.filename}>{segment.filename}</h2>
                        </li>
                        <li style={{textAlign:'center', textIndent:'-20px'}}>
                            <strong/> [<span className="source-lang">{config.source_rfc}</span>]] >
                            <strong/> [<span className="target-lang">{config.target_rfc}</span>]
                        </li>
                        <li className="wordcounter">Payable Words: <strong>{config.fileCounter[segment.id_file].TOTAL_FORMATTED}</strong>
                        </li>
                    </ul>
                    {item}
                    </React.Fragment>
            }
            currentFileId = segment.id_file;
        items.push(item);
        });
        return items;
    }

    getCommentsPadding(index, segment) {
        if ( index === 0 ) {
            let segment1 = this.getSegmentByIndex(1);
            let segment2 = this.getSegmentByIndex(2);

            if ( segment.get('openComments') ) {
                let comments = CommentsStore.getCommentsBySegment(segment.get('original_sid'));
                if (index === 0 && comments.length === 0)
                    return 110;
                else  if (index === 0 && comments.length > 0)
                    return 270;
            } else if ( segment1.get('openComments') ) {
                let comments = CommentsStore.getCommentsBySegment(segment1.get('original_sid'));
                if (comments.length === 0)
                    return 100;
                else  if (comments.length > 0)
                    return 150;
            } else if ( segment2.get('openComments') ) {
                let comments = CommentsStore.getCommentsBySegment(segment2.get('original_sid'));
                if (comments.length > 0)
                    return 100;
            }
        }
        return 0;
    }

    getSegmentHeight(index) {
        let segment = this.getSegmentByIndex(index);
        let itemHeigth = 0;
        let commentsPadding = this.getCommentsPadding(index, segment);
        if (segment.get('opened')) {
            let $segment= $('#segment-' + segment.get('sid'));
            if ( $segment.length && $segment.hasClass('opened') ) {
                itemHeigth = $segment.outerHeight() + 20;
            } else {
                itemHeigth = 300;
            }
        } else {
            let source = $('#hiddenHtml .source');
            source.html(segment.get('decoded_source'));
            const sourceHeight = source.outerHeight();


            let target = $('#hiddenHtml .targetarea');
            target.html(segment.get('decoded_translation'));
            const targetHeight = target.closest('.target').outerHeight();

            source.html('');
            target.html('');
            itemHeigth = Math.max(sourceHeight + 10, targetHeight + 10, 87) ;
        }
        let preiousFileId = (index === 0) ? 0 : this.getSegmentByIndex(index-1).get('id_file');
        //If is the first segment of a file add the file header
        if ( preiousFileId !== segment.get('id_file')) {
            itemHeigth = itemHeigth + 47;
        }
        return itemHeigth + commentsPadding;
    }

    componentDidMount() {
        this.updateWindowDimensions();
        window.addEventListener('resize', this.updateWindowDimensions);
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.addListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.addListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
        SegmentStore.addListener(SegmentConstants.SCROLL_TO_SEGMENT, this.scrollToSegment);
        SegmentStore.addListener(SegmentConstants.OPEN_SIDE, this.openSide);
        SegmentStore.addListener(SegmentConstants.CLOSE_SIDE, this.closeSide);
    }

    componentWillUnmount() {
        window.removeEventListener('resize', this.updateWindowDimensions);
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.renderSegments);
        SegmentStore.removeListener(SegmentConstants.SPLIT_SEGMENT, this.splitSegments);
        SegmentStore.removeListener(SegmentConstants.UPDATE_ALL_SEGMENTS, this.updateAllSegments);
        SegmentStore.removeListener(SegmentConstants.SCROLL_TO_SEGMENT, this.scrollToSegment);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (!nextState.segments.equals(this.state.segments) ||
        nextState.splitGroup !== this.state.splitGroup ||
        nextState.tagLockEnabled !== this.state.tagLockEnabled ||
        nextState.window !== this.state.window ||
        nextState.scrollTo !== this.state.scrollTo ||
        nextState.sideOpen !== this.state.sideOpen )
    }

    updateWindowDimensions()  {
        let data = {};
        data.width = window.innerWidth;
        data.height = window.innerHeight;

        this.setState({
            window: data
        })
    };

    componentDidCatch(e){
        console.log("React component Error", e);
    }

    componentWillUpdate() {
        saveSelection();
    }
    componentDidUpdate() {
        restoreSelection();
        if (this.state.scrollTo !== null) {
            this.setState({
                scrollTo: null
            })
        }
    }

    render() {
        let scrollTo = this.getIndexToScroll();
        let items = this.getSegments();
        let width = this.state.window.width;
        return <VirtualList
            width={width}
            height={this.state.window.height-79}
            style={{overflowX: 'hidden'}}
            estimatedItemSize={80}
            overscanCount={2}
            itemCount={items.length}
            itemSize={(index)=>this.getSegmentHeight(index)}
            scrollToAlignment="center"
            scrollToIndex={scrollTo}
            onScroll={(number, event) => {
                let scrollTop = $(event.target).scrollTop();
                let scrollBottom = $(event.target).prop('scrollHeight') - (scrollTop + $(event.target).height());
                if ( scrollBottom < 900 ) {
                    UI.getMoreSegments('after');
                } else if( scrollTop < 900 ) {
                    UI.getMoreSegments('before');
                }
            } }
            renderItem={({index, style}) => {
                let styleCopy = Object.assign({}, style);
                if ( index === 0 ) {
                    let segment = this.getSegmentByIndex(index);
                    let segment1 = this.getSegmentByIndex(1);
                    let segment2 = this.getSegmentByIndex(2);

                    if ( segment.get('openComments') ) {
                        let comments = CommentsStore.getCommentsBySegment(segment.get('original_sid'));
                        if (index === 0 && comments.length === 0)
                            styleCopy.marginTop = '110px';
                        else  if (index === 0 && comments.length > 0)
                            styleCopy.marginTop = '270px';
                    } else if ( segment1.get('openComments') ) {
                        let comments = CommentsStore.getCommentsBySegment(segment1.get('original_sid'));
                        if (comments.length === 0)
                            styleCopy.marginTop = '40px';
                        else  if (comments.length > 0)
                            styleCopy.marginTop = '100px';
                    } else if ( segment2.get('openComments') ) {
                        let comments = CommentsStore.getCommentsBySegment(segment2.get('original_sid'));
                        if (comments.length === 0)
                            styleCopy.marginTop = '20px';
                        else  if (comments.length > 0)
                            styleCopy.marginTop = '50px';
                    }
                }
                return <div key={index} style={styleCopy}>
                    {items[index]}
                </div>;
            }}
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

