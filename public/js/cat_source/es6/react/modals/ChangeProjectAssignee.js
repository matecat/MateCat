
class ChangeProjectAssignee extends React.Component {


    constructor(props) {
        super(props);
    }

    render() {
        return <div className="success-modal">

            <div className="image content">
                <div className="description">
                    <form className="ui form">
                        <div className="field">
                            <label>First Name</label>
                            <input type="text" name="Project Name" placeholder="Translated Team es." />
                        </div>
                    </form>
                </div>
            </div>
            <div className="actions">
                <div className="ui black deny button">
                    Nope
                </div>
                <div className="ui positive right labeled icon button">
                    Yep ;)
                    <i className="checkmark icon"/>
                </div>
            </div>
        </div>;
    }
}


export default ChangeProjectAssignee ;
