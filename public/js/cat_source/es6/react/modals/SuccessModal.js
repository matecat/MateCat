
class SuccessModal extends React.Component {


    constructor(props) {
        super(props);
    }

    render() {
        return <div className="success-modal">
            {/*<h2>{this.props.title}</h2>*/}
            <p>{this.props.text}</p>
        </div>;
    }
}
SuccessModal.propTypes = {
    title: React.PropTypes.string,
    text: React.PropTypes.string
};


export default SuccessModal ;
