/**
 * React Component .

 */
var React = require('react');
class SegmentSource extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            source : this.props.segment.segment

        };
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
    }

    decodeTextSource() {
        return this.props.decodeTextFn(this.props.segment, this.props.segment.segment);
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = UI.stripSpans(text);
        }

        var escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
        return escapedSegment;
    }

    componentDidMount() {
        console.log("Mount SegmentSource" + this.props.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentSource" + this.props.sid);
    }

    componentWillMount() {}

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var decoded_text = this.decodeTextSource();
        var escapedSegment = this.createEscapedSegment();
        return (
            <div className={"source item"}
                 tabIndex={0}
                 id={"segment-" + this.props.segment.sid +"-source"}
                 data-original={escapedSegment}
                 dangerouslySetInnerHTML={ this.allowHTML(decoded_text) }/>
        )
    }
}

export default SegmentSource;
