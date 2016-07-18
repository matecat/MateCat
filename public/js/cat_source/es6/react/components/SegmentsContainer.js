/**
 * React Component for the editarea.

 */
var SegmentStore = require('../stores/SegmentStore');
var Segment = require('./Segment').default;
class SegmentsContainer extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {}

    componentWillUnmount() {}

    componentWillMount() {

    }

    render() {
        var items = [];
        var self = this;
        this.props.segments.forEach(function (segment) {
            var item = <Segment
                key={segment.sid}
                segment={segment}
                splitAr={slef.props.splitAr}
                splitGroup={self.props.splitGroup}
                timeToEdit={self.props.timeToEdit}
            />;
            items.push(item);
        });
        return <div>{items}</div>;
    }
}

export default SegmentsContainer ;

