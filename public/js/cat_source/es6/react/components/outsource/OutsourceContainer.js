let OutsourceConstants = require('../../constants/OutsourceConstants');
let AssignToTranslator = require('./AssignToTranslator').default;
let OutsourceVendor = require('./OutsourceVendor').default;

class OutsourceContainer extends React.Component {


    constructor(props) {
        super(props);
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount () {}

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {
        return <div className="ui grid">
                {(this.props.showTranslatorBox) ? (
                    <AssignToTranslator job={this.props.job}
                                        url={this.props.url}
                                        project={this.props.project}/>
                ) : (null)}

                {(this.props.showTranslatorBox) ? (
                    <div className="divider-or sixteen wide column">
                        <div className="or">
                            OR
                        </div>
                    </div>
                ) : (null)}

                <OutsourceVendor project={this.props.project}
                                 job={this.props.job} />
        </div>;
    }
}
OutsourceContainer.defaultProps = {
    showTranslatorBox: true
};

export default OutsourceContainer ;