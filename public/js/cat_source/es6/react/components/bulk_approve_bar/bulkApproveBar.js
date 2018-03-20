var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');

class BulkApproveBar extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            count: 0,
            segmentsArray: [],
            fid: null
        };

        this.countInBulkElements = this.countInBulkElements.bind(this);
        this.onClickBulk = this.onClickBulk.bind(this);
        this.onClickBack = this.onClickBack.bind(this);
    }

    countInBulkElements(segments, fid) {
        let count = 0,
            segmentsArray = [];
        if (segments && segments.size > 0) {
            segments.map(function (segment) {
                if (segment.get('inBulk')){
                    segmentsArray.push(segment.get('sid'));
                    count++;
                }
            });
        }
        this.setState({
            count: count,
            segmentsArray: segmentsArray,
            fid: parseInt(fid)
        })
    }
    onClickBack(){
        SegmentActions.removeSegmentsOnBulk(this.state.fid);
    }

    onClickBulk(){
        if(this.props.isReview){
            UI.approveFilteredSegments(this.state.segmentsArray).done(response =>{
                this.onClickBack();
            });
        }else{
            UI.translateFilteredSegments(this.state.segmentsArray).done(response =>{
                this.onClickBack();
            });
        }
    }


    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.countInBulkElements);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.countInBulkElements);
    }

    render() {
        return( this.state.count > 0 ? <div className="bulk-approve-bar">
            <button className="btn" onClick={this.onClickBack}> back</button>
            <h1>in Bulk: {this.state.count}</h1>
            <button className="btn" onClick={this.onClickBulk}> {this.props.isReview ? 'Approve all' : 'Translate all'}</button>
        </div> : null)
    }
}

export default BulkApproveBar;