<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 23/08/24
 * Time: 17:41
 *
 */

namespace TestHelpers;

use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use ReflectionClass;

class InvocationInspector {

    protected array $parameters = [];

    public function __construct( InvocationOrder $invocation ) {

        $reflectionClass       = new ReflectionClass( $invocation );
        $parentReflectionClass = $reflectionClass->getParentClass();

        $this->parameters = [];

        foreach ( $parentReflectionClass->getProperties() as $p ) {
            $p->setAccessible( true );
            $this->parameters[ $p->getName() ] = $p->getValue( $invocation );
        }

    }

    /**
     * @return array
     */
    public function getInvocations(): array {
        return $this->parameters[ 'invocations' ];
    }

}