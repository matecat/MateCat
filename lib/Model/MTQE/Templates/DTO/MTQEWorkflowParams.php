<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:50
 *
 */

namespace Model\MTQE\Templates\DTO;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Utils\TaskRunner\Commons\Params;

class MTQEWorkflowParams extends AbstractDaoSilentStruct implements JsonSerializable
{

    public bool $analysis_ignore_100 = false;
    public bool $analysis_ignore_101 = false;
    public bool $confirm_best_quality_mt = true;
    public bool $lock_best_quality_mt = false;
    public int $qe_model_version = 3; //Purfect version 3 is the new default, but we can change it in the future. Version 2 is the old one, which is still supported for simple MtQE workflows (ICE_MT).

    /**
     * Rebuild the parameters from the `mt_qe_workflow_parameters` value of a TM analysis queue element.
     *
     * FastAnalysis queues an instance of this class or null. The value reaches the worker in one of three forms:
     * - the instance itself, when the queue element was built in-process: it is returned as is;
     * - a nested {@see Params}, once the message has crossed the broker: it hydrates a new instance;
     * - null, when the element does not carry the key or FastAnalysis queued none: the defaults apply.
     *
     * @param Params|self|null $value
     *
     * @return self
     */
    public static function fromQueueValue(Params|self|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof Params) {
            return new self($value->toArray());
        }

        return new self();
    }

    /**
     * @inheritDoc
     */
    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize()) ?: '';
    }

}