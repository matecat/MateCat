let OutsourceConstants = require('../../constants/OutsourceConstants');
let AssignToTranslator = require('./AssignToTranslator').default;
let OutsourceVendor = require('./OutsourceVendor').default;

class OutsourceContainer extends React.Component {


    constructor(props) {
        super(props);
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
    }

    allowHTML(string) {
        return { __html: string };
    }

    handleDocumentClick(evt)  {
        evt.stopPropagation();
        const area = ReactDOM.findDOMNode(this.container);

        if (this.container && !area.contains(evt.target) && !$(evt.target).hasClass('open-view-more')) {
            this.props.onClickOutside(evt)
        }
    }

    componentDidMount () {
        let self = this;
        setTimeout(function () {
            window.addEventListener('click', self.handleDocumentClick)
        }, 200)
    }

    componentWillUnmount() {
        window.addEventListener('click', self.handleDocumentClick)
    }

    componentDidUpdate() {}

    render() {
        return <div className="ui grid"
        ref={(container) => this.container = container}>
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
                                 job={this.props.job}
                                 extendedView={!this.props.showTranslatorBox}/>
        </div>;
    }
}
OutsourceContainer.defaultProps = {
    showTranslatorBox: true
};

export default OutsourceContainer ;