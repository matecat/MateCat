

class OutsourceInfo extends React.Component {


    constructor(props) {
        super(props);
        this.sliderIndex = 0;
        this.startSlider = this.startSlider.bind(this);
        this.timeoutSlider;
    }

    allowHTML(string) {
        return { __html: string };
    }

    openChat() {
        $(document).trigger('openChat');
    }

    startSlider() {
        var i;
        var x = this.slider.getElementsByClassName("customer-box-info");
        clearTimeout(this.timeoutSlider);
        var pointer = this.slider.getElementsByClassName("pointer");
        for (i = 0; i < x.length; i++) {
            x[i].classList.remove("fade-in");
            pointer[i].classList.remove("active");
        }
        this.sliderIndex++;
        if (this.sliderIndex > x.length) {this.sliderIndex = 1}
        x[this.sliderIndex-1].classList.add("fade-in");
        pointer[this.sliderIndex-1].classList.add("active");
        this.timeoutSlider = setTimeout(this.startSlider, 6000);

    }

    slideItem(n) {
        this.sliderIndex = n-1;
        this.startSlider()
    }

    componentDidMount () {
        this.startSlider()
    }

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {
        return <div className="customer-request sixteen wide column">
            <div className="ui grid">
                <div className="customer-box nine wide column" ref={(slider)=> this.slider = slider}>
                    <div className="title-pointer">
                        <h3>Our customer said</h3>
                        <div className="pointers">
                            <div className="pointer active" onClick={this.slideItem.bind(this, 1)}/>
                            <div className="pointer" onClick={this.slideItem.bind(this, 2)}/>
                            <div className="pointer" onClick={this.slideItem.bind(this, 3)}/>
                        </div>
                    </div>
                    <div className="slider-box" >
                        <div className="appendix">
                            <i className="icon-quote-client icon" />
                        </div>
                        <div className="customer-box-info">
                            <div className="customer-text">
                                1 - Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                                labore et dolore magna aliqua. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
                                eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </div>
                            <div className="customer-info">
                                <div className="customer-photo">
                                    <img className="ui circular image" src="../../public/img/outsource-clients/boss-example-client.jpg" />                                </div>
                                <div className="customer-name">
                                    Gina
                                </div>
                                <div className="customer-role">
                                    - Digital Strategist & Project Manager
                                </div>
                            </div>
                            <div className="customer-corporate-logo">
                                <img src="../../public/img/outsource-clients/client-example.png" />
                            </div>
                        </div>
                        <div className="customer-box-info">
                            <div className="customer-text">
                                2 - Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                                labore et dolore magna aliqua. Lorem ipsum dolor sit amet.
                            </div>
                            <div className="customer-info">
                                <div className="customer-photo">
                                    <img className="ui circular image" src="../../public/img/outsource-clients/boss-example-client.jpg" />                                </div>
                                <div className="customer-name">
                                    Franca
                                </div>
                                <div className="customer-role">
                                    - Digital Strategist & Project Manager
                                </div>
                            </div>
                            <div className="customer-corporate-logo">
                                <img src="../../public/img/outsource-clients/client-example.png" />
                            </div>
                        </div>
                        <div className="customer-box-info">
                            <div className="customer-text">
                                3 - Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                                labore et dolore magna aliqua. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                            </div>
                            <div className="customer-info">
                                <div className="customer-photo">
                                    <img className="ui circular image" src="../../public/img/outsource-clients/boss-example-client.jpg" />                                </div>
                                <div className="customer-name">
                                    Paola
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