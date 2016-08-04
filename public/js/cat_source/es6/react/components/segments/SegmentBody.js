/**
 * React Component .

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentSource = require('./SegmentSource').default;
var SegmentTarget = require('./SegmentTarget').default;

class SegmentBody extends React.Component {

    constructor(props) {
        super(props);

    }

    statusHandleTitleAttr(status) {
        status = status.toUpperCase();
        return status.charAt(0) + status.slice(1).toLowerCase()  + ', click to change it';
    }

    componentDidMount() {
        console.log("Mount SegmentBody" + this.props.segment.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentBody" + this.props.segment.sid);
    }

    componentWillMount() {}

    render() {
        var status_change_title;
        if ( this.props.segment.status ) {
            status_change_title = this.statusHandleTitleAttr( this.props.segment.status );
        } else {
            status_change_title = 'Change segment status' ;
        }
        return (
            <div className="text">
                <div className="wrap">
                    <div className="outersource">
                        <SegmentSource
                            segment={this.props.segment}
                            decodeTextFn={this.props.decodeTextFn}
                        />
                        <div className="copy" title="Copy source to target">
                            <a href="#"/>
                            <p>ALT+CTRL+I</p>
                        </div>
                        <SegmentTarget
                            segment={this.props.segment}
                            isReviewImproved={this.props.isReviewImproved}
                            enableTagProjection={this.props.enableTagProjection}
                            decodeTextFn={this.props.decodeTextFn}
                            tagModesEnabled={this.props.tagModesEnabled}
                        />

                    </div>
                </div>
                <div className="status-container">
                    <a href="#" title={status_change_title}
                       className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}/>
                </div>
            </div>
        )
    }
}

export default SegmentBody;

