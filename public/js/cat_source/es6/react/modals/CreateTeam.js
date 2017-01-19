
class CreateTeam extends React.Component {


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
                                <div className="field">
                                    <label>Last Name</label>
                                    <input type="email" name="email" placeholder="example@mail.com" />
                                </div>
                                <div className="field">
                                    <div className="ui checkbox">
                                        <input type="checkbox" tabindex="0" className="hidden" />
                                        <label>I agree to the Terms and Conditions</label>
                                    </div>
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


export default CreateTeam ;