/**
 * React Component .

 */
var SegmentStore = require('../stores/SegmentStore');
var SegmentSource = require('../components/SegmentSource').default;
var SegmentTarget = require('../components/SegmentTarget').default;

class SegmentBody extends React.Component {

    constructor(props) {
        super(props);

    }

    componentDidMount() {
        console.log("Mount SegmentBody" + this.props.segment.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentBody" + this.props.segment.sid);
    }

    componentWillMount() {

    }

    render() {
        var status_change_title;
        if ( this.props.segment.status ) {
            status_change_title = UI.statusHandleTitleAttr( this.props.segment.status );
        } else {
            status_change_title = 'Change segment status' ;
        }
        return (
            <div className={"text"}>
                <div className={"wrap"}>
                    <div className={"outersource"}>
                        <SegmentSource segment={this.props.segment} />
                        <div className={"copy"} title="Copy source to target">
                            <a href="#"/>
                            <p>ALT+CTRL+I</p>
                        </div>
                        <SegmentTarget segment={this.props.segment} />

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

