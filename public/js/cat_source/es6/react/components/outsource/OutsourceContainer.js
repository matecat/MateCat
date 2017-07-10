let OutsourceConstants = require('../../constants/OutsourceConstants');
let AssignToTranslator = require('./AssignToTranslator').default;

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
            <AssignToTranslator job={this.props.job}
                                url={this.props.url}
                                project={this.props.project}/>
            <div className="divider-or sixteen wide column">
                <div className="or">
                    OR
                </div>
            </div>
            <div className="outsource-to-translated sixteen wide column">
                <div className="payment-service">
                    <div className="service-box">
                        <div className="service project-management">Outsource: Project Management </div>
                        <div className="service translation"> + Translation </div>
                        <div className="service revision"> + Revision</div>
                    </div>
                    <div className="fiducial-logo">
                        <div className="translated-logo">Guaranteed by
                            <img className="logo-t" src="/public/img/logo_translated.png" />
                        </div>
                    </div>
                </div>
                <div className="payment-details-box shadow-1">
                    <div className="translator-job-details">
                        <div className="translator-details-box">
                            <div className="ui list left">
                                <div className="item">Esmeralda <b>by Translated</b></div>
                                <div className="item"><b>8 years of experience</b></div>
                                <div className="item">
                                    <div className="ui mini star rating" data-rating="4" data-max-rating="5" />
                                </div>
                            </div>
                            <div className="ui list right">
                                <div className="item"><b>24.638</b> words translated last 12 months</div>
                                <div className="item"><b>General, Marketing, Design</b></div>
                                {/*<div className="item">Esmeralda <b>by Translated</b></div>*/}
                            </div>
                        </div>
                        <div className="job-details-box">
                            <div className="source-target-outsource st-details">
                                <div className="source-box">Spanish</div>
                                <div className="in-to">
                                    <i className="icon-chevron-right icon" />
                                </div>
                                <div className="target-box">Haitian Creole French</div>
                            </div>
                            <div className="job-payment">
                                <div className="not-payable">2,574,135 words</div>
                                <div className="payable">1,285,722 words</div>
                            </div>
                        </div>
                        <div className="job-price">€400</div>
                    </div>
                    <div className="revision-box">
                        <div className="add-revision">
                            <div className="ui checkbox">
                                <input type="checkbox" />
                                <label>Add Revision</label>
                            </div>
                        </div>
                        <div className="job-price">€400</div>
                    </div>
                    <div className="delivery-order">
                        <div className="delivery-box">
                            <label>Delivery date:</label>
                            <div className="delivery-date">15 August</div>
                            <span>at</span>
                            <div className="delivery-time">11:00 AM</div>
                            <div className="gmt-button">
                                <div className="ui button">
                                    (GMT +2)
                                </div>
                            </div>
                            <div className="need-it-faster">
                                <a className="faster">Need it faster?</a>
                            </div>
                        </div>
                    </div>
                    <div className="order-box-outsource">
                        <div className="outsource-price">
                            €372.234
                        </div>
                        <div className="select-value">
                            <a className="value">about €0.96 / word</a>
                        </div>
                    </div>
                    <div className="order-button-outsource">
                        <a className="open-order ui green button">Order now</a>
                    </div>
                </div>
                <div className="easy-pay-box">
                    <h4 className="easy-pay">Easy payments</h4>
                    <p>Pay a single monthly invoice within 30 days of receipt</p>
                </div>
                <div className="customer-request sixteen wide column">
                    <div className="ui grid">
                        <div className="customer-box eight wide column">
                            <div className="title-pointer">
                                <h3>Our customer said</h3>
                                <div className="pointers">
                                    <div className="pointer active"/>
                                    <div className="pointer"/>
                                    <div className="pointer"/>
                                </div>
                            </div>
                            <div className="slider-box">
                                <div className="appendix">
                                    "
                                </div>
                                <div className="customer-box-info">
                                    <div className="customer-text">
                                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                                        labore et dolore magna aliqua. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
                                        eiusmod tempor incididunt ut labore et dolore magna aliqua.
                                    </div>
                                    <div className="customer-info">
                                        <div className="customer-photo">
                                            <img className="ui medium circular tiny image" src="../../public/img/outsource-clients/boss-example-client.jpg" />
                                        </div>
                                        <div className="customer-name">
                                            Elena
                                        </div>
                                        <div className="customer-role">
                                            - Digital Strategist & Project Manager
                                        </div>
                                    </div>
                                    <div className="customer-corporate-logo">
                                        <img src="../../public/img/outsource-clients/client-example.png" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="request-box eight wide column">
                        </div>
                    </div>
                </div>
            </div>
        </div>;
    }
}
OutsourceContainer.defaultProps = {
    showTranslatorBox: true
};

export default OutsourceContainer ;