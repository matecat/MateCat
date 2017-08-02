
class OpenJobBox extends React.Component {


    constructor(props) {
        super(props);
    }

    openJob() {
        window.open(this.props.url, "_blank")
    }

    componentDidMount () {}

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {

        return <div className="open-job-box">
            <div className="title">
                Open job
            </div>
            <div className="title-url">
                <div className="job-url">
                    {window.location.protocol + '//' + window.location.host + this.props.url}
                </div>
                <button className="ui primary button"
                        onClick={this.openJob.bind(this)}
                        ref={(button) => this.openButton = button }>Open job</button>
            </div>
        </div>;
    }
}

export default OpenJobBox ;