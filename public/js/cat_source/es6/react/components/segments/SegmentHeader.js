/**
 * React Component .

 */
class SegmentHeader extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            autopropagated: this.props.autopropagated,
            percentuage: ''
        };

    }

    componentDidMount() {
        console.log("Mount SegmentHeader" + this.props.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentHeader" + this.props.sid);
    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var autopropagated = '';
        if (this.state.autopropagated) {
            autopropagated = <span className="repetition">Autopropagated</span>;
        }
        return (
            <div className="header toggle" id={"segment-" + this.props.sid + "-header"}>
                {autopropagated}
                <h2 title="" className="percentuage"></h2>
            </div>
        )
    }
}

export default SegmentHeader;


