/**
 * React Component .

 */
class SegmentFooter extends React.Component {

    constructor(props) {
        super(props);

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
            <div className="footer toggle"></div>
        )
    }
}

export default SegmentFooter;
