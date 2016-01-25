<?php

class AbstractModelSubject implements SplSubject {

    /**
     * @var SplObserver[]
     */
    private $observers = array();
    private $named_obsevers = array();

    public function attach( SplObserver $observer ) {
        array_push( $this->observers, $observer);
    }

    public function detach( SplObserver $observer ) {
        // TODO:
    }

    public function attachNamed($name, SplObserver $observer) {
        if ( !array_key_exists($name) ) {
            $this->named_obsevers[$name] = array();
        }

        array_push( $this->named_obsevers[$name], $observer );
    }

    public function notify() {
        foreach($this->observers as $k => $v) {
            $v->update($this);
        }

    }
}