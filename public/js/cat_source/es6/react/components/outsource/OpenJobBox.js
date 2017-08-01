
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
            <div className="title-url ui grid">
                <div className="job-url">
                    <a href={window.location.protocol + '//' + window.location.host + this.props.url} target="_blank">
                        {window.location.protocol + '//' + window.location.host + this.props.url}</a>
                </div>
                <button className="ui primary button"
                        onClick={this.openJob.bind(this)}
                        ref={(button) => this.openButton = button }>Open job</button>
            </div>
        </div>;
    }
}

export default OpenJobBox ;