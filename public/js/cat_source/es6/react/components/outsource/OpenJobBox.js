
class OpenJobBox extends React.Component {


    constructor(props) {
        super(props);
    }

    openJob() {
        return this.props.url;
    }

    componentDidMount () {}

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {

        return <div className="open-job-box">
            <div className="title">
                Open job:
            </div>
            <div className="title-url">
                <a className="job-url"
                   href={this.openJob()} target="_blank">
                    {window.location.protocol + '//' + window.location.host + this.props.url}
                </a>
                <a className="ui primary button"
                   href={this.openJob()} target="_blank">Open job</a>
            </div>
        </div>;
    }
}

export default OpenJobBox ;