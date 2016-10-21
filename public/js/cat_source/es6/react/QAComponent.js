
/**
 * React Component for the editarea.

 */
var React = require('react');

class QAComponent extends React.Component {

    constructor(props) {
        super(props);

        this.state = {

        };
    }

    static togglePanel() {
        $('.qa-container').toggleClass("qa-open");
        $('.qa-container').slideToggle()
    }

    componentDidMount() {

    }


    componentWillUnmount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return  <div className="qa-container">
                    
                </div>
    }
}

export default QAComponent ;

