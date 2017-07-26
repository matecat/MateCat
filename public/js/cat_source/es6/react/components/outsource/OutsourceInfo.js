

class OutsourceInfo extends React.Component {


    constructor(props) {
        super(props);
    }

    allowHTML(string) {
        return { __html: string };
    }

    openChat() {
        $(document).trigger('openChat');
    }

    componentDidMount () {}

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {
        return <div className="customer-request sixteen wide column">
            <div className="ui grid">
                <div className="customer-box nine wide column">
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
                            <i className="icon-quote-client icon" />
                        </div>
                        <div className="customer-box-info">
                            <div className="customer-text">
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                                labore et dolore magna aliqua. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
                                eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </div>
                            <div className="customer-info">
                                <div className="customer-photo">
                                    <img className="ui circular image" src="../../public/img/outsource-clients/boss-example-client.jpg" />                                </div>
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
                <div className="request-box seven wide column">
                    <div className="title-request">
                        <h3>Have an specific request?</h3>
                    </div>
                    <div className="request-info-box">
                        <div className="mobile-mail-box">
                            <div className="ui relaxed divided list">
                                <div className="item call">
                                    <i className="big icon-phone2 middle aligned icon" />
                                    <div className="content">
                                        <div className="header">Call us</div>
                                        <a className="description" href="tel:+390690254001">+39 06 90 254 001</a>
                                    </div>
                                </div>
                                <div className="item send-email">
                                    <i className="big icon-envelope-o middle aligned icon" />
                                    <div className="content">
                                        <div className="header">Sent an e-mail at</div>
                                        <a className="description">info@matecat.com</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="account-box">

                            <div className="ui relaxed divided list">
                                <div className="item open-chat">
                                    <i className="big icon-uniE970 middle aligned icon" />
                                    <div className="content">
                                        <div className="header">Talk with us
                                            <span className="online"> (On line)</span>
                                            {/*<span className="offline"> (Off line)</span>*/}
                                        </div>
                                        <div className="ui button intercom-button">
                                            <div className="sign online-item"/>
                                            Open chat
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>;
    }
}

export default OutsourceInfo ;