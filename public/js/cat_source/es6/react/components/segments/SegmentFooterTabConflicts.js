/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabConflicts extends React.Component {

    constructor(props) {
        var code = 'tm';
        var tab_class ='matches';
        var label = 'Translation Matches';
    }

    is_enabled(sid) {
        if (this.props.sid == sid) {
            return true;
        }
    }
    tab_markup(sid) {
        if (this.props.sid == sid) {
            if (config.mt_enabled) {
                return this.label;
            }
            else {
                return this.label + " (No MT) ";
            }
        }
    }
    is_hidden(sid) {
        return false;
    }

    componentDidMount() {
        console.log("Mount SegmentFooter" + this.props.sid);

    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooter" + this.props.sid);

    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {


        return (
            <div>
                <div className="overflow"></div>
                <div className="engine-errors"></div>
            </div>
        )
    }
}

export default SegmentFooterTabConflicts;
