/**
 * React Component .

 */
class SegmentHeader extends React.Component {

    constructor(props) {
        super(props);

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
        return (
            <div className={"header toggle"} id={"segment-" + this.props.sid + "-header"}/>
        )
    }
}

export default SegmentHeader;


