<?php

namespace Utils\Contribution;

final readonly class ContributionContexts
{
    public function __construct(
        public ?string $segment = null,
        public ?string $context_before = null,
        public ?string $context_after = null,
    ) {
    }

    /**
     * @param array{segment?: ?string, context_before?: ?string, context_after?: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            segment: $data['segment'] ?? null,
            context_before: $data['context_before'] ?? null,
            context_after: $data['context_after'] ?? null,
        );
    }
}
